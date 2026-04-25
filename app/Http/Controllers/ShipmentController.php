<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SmsAuditLog;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\ShippingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $shipments = Shipment::with([
            'exchangeRequest.item',
            'events' => fn ($q) => $q->latest(),
            'order.deliveryOtps' => fn ($q) => $q->latest(),
            'order.smsAuditLogs' => fn ($q) => $q->latest(),
            'order.buyer',
        ])
            ->whereHas('exchangeRequest', function ($query) use ($user) {
                $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->latest()
            ->paginate(10);

        return view('shipments.index', compact('shipments', 'user'));
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $this->authorizeShipmentAccess($request, $shipment);
        $this->authorizeSellerAction($request, $shipment);

        $validated = $request->validate([
            'status' => 'required|in:Order Placed,Picked Up,In Transit,Delivered',
        ]);

        $shipment->loadMissing('order');
        $order = $shipment->order;
        $requestedStatus = (string) $validated['status'];
        $requiresUpfrontFirst = $requestedStatus !== 'Order Placed';
        if (
            $requiresUpfrontFirst
            && $order
            && $order->payment_method === 'escrow'
            && (
                ! $order->upfront_paid_at
                || ((float) ($order->remaining_amount ?? 0) > 0.0001 && ! $order->remaining_paid_at && $requestedStatus === 'Delivered')
            )
        ) {
            return back()->with('error', 'Buyer must complete upfront payment before shipment can move beyond Order Placed.');
        }

        $shipment->update($validated);

        if ($validated['status'] === 'Delivered') {
            $shipment->exchangeRequest->update(['status' => 'Completed']);
        }

        return back()->with('success', 'Shipment status updated.');
    }

    public function schedulePickup(Request $request, Shipment $shipment, ShippingService $shippingService)
    {
        $this->authorizeShipmentAccess($request, $shipment);
        $this->authorizeSellerAction($request, $shipment);
        $shipment->loadMissing('order');
        if ($shipment->order && $shipment->order->payment_method === 'escrow' && ! $shipment->order->upfront_paid_at) {
            return back()->with('error', 'Upfront payment is required before pickup can be scheduled.');
        }

        $shipment->loadMissing('exchangeRequest.sender', 'exchangeRequest.receiver');
        $sender = $shipment->exchangeRequest?->sender;
        $receiver = $shipment->exchangeRequest?->receiver;
        if (! $sender || ! $receiver || ! filled($sender->phone) || ! filled($sender->address) || ! filled($receiver->phone) || ! filled($receiver->address)) {
            return back()->with('error', 'Pickup cannot be scheduled until both users have phone and address in profile.');
        }

        $shippingService->schedulePickup($shipment);

        return back()->with('success', 'Pickup scheduled successfully.');
    }

    public function simulateEvent(Request $request, Shipment $shipment, ShippingService $shippingService)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'status_code' => 'required|in:picked_up,in_transit,out_for_delivery,delivered',
        ]);

        $shippingService->processWebhook($shipment->provider ?: 'mock', [
            'awb_number' => $shipment->awb_number,
            'status_code' => $validated['status_code'],
            'status_label' => ucwords(str_replace('_', ' ', $validated['status_code'])),
            'occurred_at' => now()->toDateTimeString(),
            'source' => 'manual_simulation',
        ]);

        return back()->with('success', 'Simulated shipment event processed.');
    }

    public function initiatePayment(Request $request, Shipment $shipment)
    {
        $this->authorizeShipmentAccess($request, $shipment);
        $this->authorizeSellerAction($request, $shipment);

        $validated = $request->validate([
            'payment_method' => 'required|in:escrow,cod',
            'negotiated_item_amount' => 'nullable|numeric|min:1',
            'upfront_amount' => 'nullable|numeric|min:1',
        ]);

        $shipment->loadMissing('exchangeRequest.item', 'exchangeRequest.sender', 'exchangeRequest.receiver');
        $exchange = $shipment->exchangeRequest;
        if (! $exchange) {
            return back()->with('error', 'Exchange record missing for this shipment.');
        }
        $baseItemAmount = (float) ($exchange->item?->price ?? 0);
        $itemAmount = (float) ($validated['negotiated_item_amount'] ?? $baseItemAmount);
        $shippingAmount = $this->estimateShippingCharge((string) ($exchange->sender?->address ?? ''), (string) ($exchange->receiver?->address ?? ''));
        $platformFee = $this->calculatePlatformFee($itemAmount);
        $totalAmount = $itemAmount + $shippingAmount + $platformFee;
        $upfrontAmount = (float) ($validated['upfront_amount'] ?? $totalAmount);

        if ($validated['payment_method'] === 'escrow') {
            if ($upfrontAmount > $totalAmount) {
                return back()->with('error', 'Upfront amount cannot be greater than total payable.');
            }
            if ($upfrontAmount < 1) {
                return back()->with('error', 'Upfront amount must be at least INR 1.');
            }
        } else {
            $upfrontAmount = 0.0;
        }

        $remainingAmount = max(0, round($totalAmount - $upfrontAmount, 2));
        $requiresSecondPayment = $validated['payment_method'] === 'escrow' && $remainingAmount > 0;

        $order = Order::query()->updateOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'buyer_id' => $exchange->sender_id,
                'seller_id' => $exchange->receiver_id,
                'payment_method' => $validated['payment_method'],
                'gateway' => config('payments.default_gateway', 'razorpay'),
                'item_amount' => $itemAmount,
                'negotiated_item_amount' => $itemAmount,
                'shipping_amount' => $shippingAmount,
                'platform_fee' => $platformFee,
                'total_amount' => $totalAmount,
                'upfront_amount' => $upfrontAmount,
                'remaining_amount' => $remainingAmount,
                'second_payment_required_before_otp' => $requiresSecondPayment,
                'payment_status' => 'pending',
                'paid_at' => null,
                'upfront_paid_at' => null,
                'remaining_paid_at' => null,
                'payment_reference' => null,
                'upfront_payment_reference' => null,
                'remaining_payment_reference' => null,
                'gateway_order_id' => null,
                'upfront_gateway_order_id' => null,
                'remaining_gateway_order_id' => null,
                'settlement_status' => 'pending',
                'collected_at' => null,
                'released_at' => null,
                'delivery_verified_at' => null,
            ]
        );

        return back()->with('success', 'Payment section created. Order #'.$order->id.' is now active.');
    }

    public function generateDeliveryOtp(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        abort_unless($user, 403);
        if (! $user->isAdmin()) {
            abort(403);
        }

        $shipment->loadMissing('order');
        if (! $shipment->order) {
            return back()->with('error', 'Create payment order before generating OTP.');
        }

        $plainCode = (string) random_int(100000, 999999);
        $otp = $shipment->order->deliveryOtps()->create([
            'code' => '',
            'code_hash' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(15),
            'generated_for_user_id' => $shipment->order->buyer_id,
            'sms_sent_at' => now(),
        ]);

        SmsAuditLog::query()->create([
            'order_id' => $shipment->order->id,
            'phone' => (string) ($shipment->order->buyer?->phone ?? ''),
            'channel' => 'sms',
            'status' => 'sent',
            'message' => 'Your SwapShip delivery OTP is '.$plainCode.'. Do not share with anyone.',
            'meta' => [
                'delivery_otp_id' => $otp->id,
                'shipment_id' => $shipment->id,
            ],
        ]);

        return back()->with('success', 'Delivery OTP generated and sent to buyer phone.');
    }

    public function verifyDeliveryOtp(Request $request, Shipment $shipment)
    {
        $this->authorizeShipmentAccess($request, $shipment);
        $this->authorizeBuyerAction($request, $shipment);

        $validated = $request->validate([
            'otp_code' => 'required|string|size:6',
        ]);
        $shipment->loadMissing('order.deliveryOtps', 'exchangeRequest');
        $order = $shipment->order;
        if (! $order) {
            return back()->with('error', 'Order not found for this shipment.');
        }
        if (
            $order->payment_method === 'escrow'
            && (float) ($order->remaining_amount ?? 0) > 0.0001
            && ! $order->remaining_paid_at
        ) {
            return back()->with('error', 'Remaining amount must be paid before OTP verification and rider handover.');
        }

        $otp = $order->deliveryOtps()->whereNull('verified_at')->latest()->first();
        if (! $otp) {
            return back()->with('error', 'No active OTP found.');
        }
        if ($otp->expires_at && now()->greaterThan($otp->expires_at)) {
            return back()->with('error', 'OTP expired. Generate a new OTP.');
        }
        if ($otp->attempts >= 3) {
            return back()->with('error', 'OTP locked after multiple failed attempts. Generate a new OTP.');
        }

        $matches = false;
        if ($otp->code_hash) {
            $matches = Hash::check($validated['otp_code'], $otp->code_hash);
        } elseif ($otp->code !== '') {
            $matches = hash_equals($otp->code, $validated['otp_code']);
        }

        if (! $matches) {
            $otp->increment('attempts');
            return back()->with('error', 'Invalid OTP.');
        }

        $otp->update(['verified_at' => now()]);
        $order->update([
            'delivery_verified_at' => now(),
            'payment_status' => $order->payment_method === 'cod' ? 'collected' : $order->payment_status,
            'collected_at' => $order->payment_method === 'cod' ? now() : $order->collected_at,
            'settlement_status' => 'released',
            'released_at' => now(),
        ]);
        $shipment->update([
            'status' => 'Delivered',
            'status_code' => 'delivered',
            'status_label' => 'Delivered',
        ]);
        if ($shipment->exchangeRequest) {
            $shipment->exchangeRequest->update(['status' => 'Completed']);
        }
        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'delivered',
            'event_label' => 'Delivered (OTP verified)',
            'raw_payload' => ['source' => 'otp_verification', 'order_id' => $order->id],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'OTP verified. Delivery and settlement completed.');
    }

    protected function estimateShippingCharge(string $from, string $to): float
    {
        if ($from !== '' && $to !== '' && strcasecmp(trim($from), trim($to)) === 0) {
            return 49.0;
        }

        return 99.0;
    }

    protected function calculatePlatformFee(float $itemAmount): float
    {
        $percent = (float) config('payments.platform_fee_percent', 0);
        $flat = (float) config('payments.platform_fee_flat', 0);

        return round(($itemAmount * $percent / 100) + $flat, 2);
    }

    protected function authorizeShipmentAccess(Request $request, Shipment $shipment): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($user->isAdmin()) {
            return;
        }

        $shipment->loadMissing('exchangeRequest');
        $exchange = $shipment->exchangeRequest;
        abort_unless($exchange, 404);
        abort_unless(in_array($user->id, [$exchange->sender_id, $exchange->receiver_id], true), 403);
    }

    protected function authorizeSellerAction(Request $request, Shipment $shipment): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($user->isAdmin()) {
            return;
        }

        $shipment->loadMissing('exchangeRequest');
        $exchange = $shipment->exchangeRequest;
        abort_unless($exchange, 404);
        // Seller is receiver/item owner in exchange flow.
        abort_unless($user->id === $exchange->receiver_id, 403);
    }

    protected function authorizeBuyerAction(Request $request, Shipment $shipment): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($user->isAdmin()) {
            return;
        }

        $shipment->loadMissing('exchangeRequest');
        $exchange = $shipment->exchangeRequest;
        abort_unless($exchange, 404);
        // Buyer is sender/request initiator in exchange flow.
        abort_unless($user->id === $exchange->sender_id, 403);
    }
}
