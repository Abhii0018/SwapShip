<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Message;
use App\Models\User;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExchangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user && $request->session()->has('actor_user_id')) {
            $user = User::query()->find((int) $request->session()->get('actor_user_id'));
        }
        if (! $user) {
            return view('exchanges.index', [
                'sentRequests' => collect(),
                'receivedRequests' => collect(),
            ]);
        }

        $sentRequests = ExchangeRequest::with('item', 'receiver')
            ->where('sender_id', $user->id)
            ->latest()
            ->get();

        $receivedRequests = ExchangeRequest::with('item', 'sender')
            ->where('receiver_id', $user->id)
            ->latest()
            ->get();

        return view('exchanges.index', compact('sentRequests', 'receivedRequests'));
    }

    public function store(Request $request, Item $item)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Please login to request an item.');
        }

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Complete your profile before requesting an item.');
        }

        $senderId = $user->id;
        abort_if($item->user_id === $senderId, 422, 'You cannot request your own item.');

        $hasActiveRequest = ExchangeRequest::query()
            ->where('sender_id', $senderId)
            ->where('item_id', $item->id)
            ->whereNotIn('status', ['Rejected', 'Completed'])
            ->latest()
            ->first();
        if ($hasActiveRequest) {
            return redirect()
                ->route('chat.index', $hasActiveRequest)
                ->with('success', 'You already have an active request for this item. Redirected to chat.');
        }

        $exchange = ExchangeRequest::create([
            'sender_id' => $senderId,
            'receiver_id' => $item->user_id,
            'item_id' => $item->id,
            'status' => 'Pending',
        ]);

        return redirect()->route('chat.index', $exchange)->with('success', 'Exchange request sent.');
    }

    public function startChat(Request $request, Item $item): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Please login to start chat.');
        }

        if (! $user->hasCompletedProfile()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Complete your profile before starting chat.');
        }

        abort_if($item->user_id === $user->id, 422, 'You cannot start chat on your own item.');

        $conversation = ExchangeRequest::query()
            ->where('item_id', $item->id)
            ->whereNotIn('status', ['Rejected', 'Completed'])
            ->where(function ($q) use ($user, $item) {
                $q->where(function ($inner) use ($user, $item) {
                    $inner->where('sender_id', $user->id)
                        ->where('receiver_id', $item->user_id);
                })->orWhere(function ($inner) use ($user, $item) {
                    $inner->where('sender_id', $item->user_id)
                        ->where('receiver_id', $user->id);
                });
            })
            ->latest()
            ->first();

        if (! $conversation) {
            $conversation = ExchangeRequest::create([
                'sender_id' => $user->id,
                'receiver_id' => $item->user_id,
                'item_id' => $item->id,
                'status' => 'Pending',
            ]);
        }

        return redirect()->route('chat.index', $conversation);
    }

    public function show(ExchangeRequest $exchangeRequest): View
    {
        abort_unless(request()->user(), 403);
        abort_unless(
            in_array(request()->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $exchangeRequest->load('item', 'sender', 'receiver', 'messages.sender');

        return view('exchanges.show', compact('exchangeRequest'));
    }

    public function updateStatus(Request $request, ExchangeRequest $exchangeRequest, ShippingService $shippingService)
    {
        abort_unless($request->user()->id === $exchangeRequest->receiver_id, 403);

        $validated = $request->validate([
            'status' => 'required|in:Accepted,Rejected,In Progress,Completed',
        ]);

        if ($validated['status'] === 'Accepted') {
            $missing = $this->missingContactFields($exchangeRequest);
            if (! empty($missing)) {
                return back()->with('error', 'Before accepting, both users must add phone and address in profile.');
            }
        }

        $exchangeRequest->update(['status' => $validated['status']]);

        if ($validated['status'] === 'Accepted') {
            $exchangeRequest->update(['receiver_confirmed_at' => now()]);
            $this->tryStartShipment($exchangeRequest, $shippingService);
        } elseif ($validated['status'] === 'Rejected') {
            $exchangeRequest->update([
                'sender_confirmed_at' => null,
                'receiver_confirmed_at' => null,
            ]);
        }

        return back()->with('success', 'Exchange status updated.');
    }

    public function confirm(Request $request, ExchangeRequest $exchangeRequest, ShippingService $shippingService)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true), 403);
        if (! in_array($exchangeRequest->status, ['Accepted', 'In Progress'], true)) {
            return back()->with('error', 'Exchange can be confirmed only after acceptance.');
        }

        $missing = $this->missingContactFields($exchangeRequest);
        if (! empty($missing)) {
            return back()->with('error', 'Before confirming, both users must add phone and address in profile.');
        }

        if ($user->id === $exchangeRequest->sender_id) {
            $exchangeRequest->update(['sender_confirmed_at' => now()]);
        }
        if ($user->id === $exchangeRequest->receiver_id) {
            $exchangeRequest->update(['receiver_confirmed_at' => now()]);
        }

        $this->tryStartShipment($exchangeRequest, $shippingService);

        return back()->with('success', 'Exchange confirmed.');
    }

    public function generateDemoData(ShippingService $shippingService)
    {
        $seller = User::query()->firstOrCreate(
            ['email' => 'demo-seller@swapship.local'],
            [
                'name' => 'Demo Seller',
                'password' => Hash::make('demo-password'),
            ]
        );

        $buyer = User::query()->firstOrCreate(
            ['email' => 'demo-buyer@swapship.local'],
            [
                'name' => 'Demo Buyer',
                'password' => Hash::make('demo-password'),
            ]
        );

        if (! $seller->address) {
            $seller->update(['address' => 'Old Delhi, Delhi, India']);
        }
        if (! $buyer->address) {
            $buyer->update(['address' => 'New Delhi, Delhi, India']);
        }

        $item = Item::query()->create([
            'user_id' => $seller->id,
            'title' => 'Demo iPhone 13 - Great Condition',
            'description' => 'Demo listing created for shipping flow tests.',
            'category' => 'Mobiles',
            'condition' => 'like new',
            'item_age' => '10 months old',
            'type' => 'both',
            'exchange_preference' => 'Android flagship phone',
            'price' => 42000,
            'location' => 'Delhi NCR',
            'notes' => 'Demo data record',
        ]);

        $exchange = ExchangeRequest::query()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'status' => 'In Progress',
        ]);

        Message::query()->create([
            'exchange_request_id' => $exchange->id,
            'sender_id' => $buyer->id,
            'body' => 'Hi, I am interested in exchanging for this item.',
        ]);
        Message::query()->create([
            'exchange_request_id' => $exchange->id,
            'sender_id' => $seller->id,
            'body' => 'Sure, request accepted. Let us proceed with shipment.',
        ]);

        $shipment = $shippingService->createShipmentForExchange($exchange);
        $shippingService->processWebhook($shipment->provider ?: 'mock', [
            'awb_number' => $shipment->awb_number,
            'status_code' => 'picked_up',
            'status_label' => 'Picked Up',
            'occurred_at' => now()->subHours(8)->toDateTimeString(),
        ]);
        $shippingService->processWebhook($shipment->provider ?: 'mock', [
            'awb_number' => $shipment->awb_number,
            'status_code' => 'in_transit',
            'status_label' => 'In Transit',
            'occurred_at' => now()->subHours(3)->toDateTimeString(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Demo exchange data generated successfully.');
    }

    protected function tryStartShipment(ExchangeRequest $exchangeRequest, ShippingService $shippingService): void
    {
        $exchangeRequest->refresh();
        if ($exchangeRequest->sender_confirmed_at && $exchangeRequest->receiver_confirmed_at) {
            $shippingService->createShipmentForExchange($exchangeRequest);
            $exchangeRequest->update(['status' => 'In Progress']);
        }
    }

    protected function missingContactFields(ExchangeRequest $exchangeRequest): array
    {
        $exchangeRequest->loadMissing(['sender', 'receiver']);
        $missing = [];
        foreach (['sender', 'receiver'] as $role) {
            $user = $exchangeRequest->{$role};
            if (! $user) {
                $missing[] = $role.':missing-user';
                continue;
            }
            if (! filled($user->phone)) {
                $missing[] = $role.':phone';
            }
            if (! filled($user->address)) {
                $missing[] = $role.':address';
            }
        }

        return $missing;
    }
}
