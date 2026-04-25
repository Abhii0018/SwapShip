<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user() ?? $this->resolveGuestSeller();
        $actorId = $actor->id;

        $myListings = Item::query()
            ->with('images')
            ->where('user_id', $actorId)
            ->latest()
            ->get();

        $sentRequests = ExchangeRequest::query()
            ->with(['item', 'receiver'])
            ->where('sender_id', $actorId)
            ->latest()
            ->get();

        $receivedRequests = ExchangeRequest::query()
            ->with(['item', 'sender'])
            ->where('receiver_id', $actorId)
            ->latest()
            ->get();

        $activeExchanges = ExchangeRequest::query()
            ->with(['item', 'sender', 'receiver', 'shipment'])
            ->whereIn('status', ['Accepted', 'In Progress'])
            ->where(function ($q) use ($actorId) {
                $q->where('sender_id', $actorId)->orWhere('receiver_id', $actorId);
            })
            ->latest()
            ->get();

        $completedExchanges = ExchangeRequest::query()
            ->with(['item', 'sender', 'receiver'])
            ->where('status', 'Completed')
            ->where(function ($q) use ($actorId) {
                $q->where('sender_id', $actorId)->orWhere('receiver_id', $actorId);
            })
            ->latest()
            ->get();

        $recentNotifications = collect()
            ->merge($sentRequests->map(fn ($r) => [
                'text' => 'Request sent for '.$r->item?->title,
                'time' => optional($r->created_at)->diffForHumans(),
                'ts' => optional($r->created_at)?->timestamp ?? 0,
            ]))
            ->merge($receivedRequests->whereIn('status', ['Accepted', 'Rejected'])->map(fn ($r) => [
                'text' => 'Request '.$r->status.' for '.$r->item?->title,
                'time' => optional($r->updated_at)->diffForHumans(),
                'ts' => optional($r->updated_at)?->timestamp ?? 0,
            ]))
            ->merge(Shipment::query()
                ->whereIn('exchange_request_id', $activeExchanges->pluck('id'))
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($s) => [
                    'text' => 'Shipment update: '.$s->status,
                    'time' => optional($s->updated_at)->diffForHumans(),
                    'ts' => optional($s->updated_at)?->timestamp ?? 0,
                ]))
            ->sortByDesc('ts')
            ->take(8)
            ->values();

        $stats = [
            'listed_items' => $myListings->count(),
            'active_exchanges' => ExchangeRequest::whereIn('status', ['Pending', 'Accepted', 'In Progress'])
                ->where(fn ($q) => $q->where('sender_id', $actorId)->orWhere('receiver_id', $actorId))
                ->count(),
            'completed_exchanges' => $completedExchanges->count(),
        ];

        return view('dashboard', compact(
            'stats',
            'myListings',
            'sentRequests',
            'receivedRequests',
            'activeExchanges',
            'completedExchanges',
            'recentNotifications'
        ));
    }

    protected function resolveGuestSeller(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'guest-seller@swapship.local'],
            [
                'name' => 'Guest Seller',
                'password' => Hash::make('guest-seller-password'),
            ]
        );
    }
}
