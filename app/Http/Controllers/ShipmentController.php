<?php

namespace App\Http\Controllers;

use App\Events\ShipmentTracked;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SmsAuditLog;
use App\Services\Orders\DealTermsService;
use App\Services\Shipping\GeocodingService;
use App\Services\Shipping\ShippingService;
use App\Services\Shipping\TrackingPositionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

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

        $this->broadcastTrackingUpdate($shipment);

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
        $this->broadcastTrackingUpdate($shipment->fresh() ?? $shipment);

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

        $this->broadcastTrackingUpdate($shipment->fresh() ?? $shipment);

        return back()->with('success', 'Simulated shipment event processed.');
    }

    public function initiatePayment(Request $request, Shipment $shipment, DealTermsService $dealTermsService)
    {
        $this->authorizeShipmentAccess($request, $shipment);
        $this->authorizeSellerAction($request, $shipment);

        $validated = $request->validate([
            'payment_method' => 'required|in:escrow,cod',
            'negotiated_item_amount' => 'nullable|numeric|min:1',
            'upfront_amount' => 'nullable|numeric|min:1',
        ]);

        try {
            $order = $dealTermsService->applyForShipment($shipment, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

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

        $this->broadcastTrackingUpdate($shipment);

        return back()->with('success', 'OTP verified. Delivery and settlement completed.');
    }

    public function track(Request $request, Shipment $shipment): View
    {
        $this->authorizeShipmentAccess($request, $shipment);

        $shipment->loadMissing([
            'exchangeRequest.sender',
            'exchangeRequest.receiver',
            'exchangeRequest.item',
            'events' => fn ($q) => $q->latest('occurred_at'),
            'order',
        ]);

        $trackingData = $this->buildTrackingData($shipment);

        return view('shipments.track', [
            'shipment' => $shipment,
            'tracking' => $trackingData,
            'pollIntervalSeconds' => max(3, (int) config('shipping.tracking.poll_interval_seconds', 8)),
            'pusherKey' => (string) config('broadcasting.connections.pusher.key', ''),
            'pusherCluster' => (string) config('broadcasting.connections.pusher.options.cluster', ''),
        ]);
    }

    public function trackState(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeShipmentAccess($request, $shipment);

        $shipment->loadMissing([
            'exchangeRequest.sender',
            'exchangeRequest.receiver',
            'events' => fn ($q) => $q->latest('occurred_at')->limit(8),
        ]);

        $data = $this->buildTrackingData($shipment);
        $data['events'] = $shipment->events->map(fn ($event) => [
            'code' => (string) $event->event_code,
            'label' => (string) $event->event_label,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ])->values();

        return response()->json($data);
    }

    /**
     * Build the payload consumed by both the track view and the JSON state
     * endpoint. Always returns a non-null array even if geocoding fails.
     *
     * @return array<string, mixed>
     */
    protected function buildTrackingData(Shipment $shipment): array
    {
        $exchange = $shipment->exchangeRequest;
        $senderAddress = (string) ($shipment->sender_address ?: $exchange?->sender?->address ?? '');
        $receiverAddress = (string) ($shipment->receiver_address ?: $exchange?->receiver?->address ?? '');

        $meta = (array) ($shipment->meta ?? []);

        $senderCoords = $this->coordsFromMeta($meta, 'sender');
        $receiverCoords = $this->coordsFromMeta($meta, 'receiver');
        $routePolyline = isset($meta['route_polyline']) && is_array($meta['route_polyline'])
            ? $meta['route_polyline']
            : null;

        if (! $senderCoords || ! $receiverCoords || $routePolyline === null) {
            $geocoder = app(GeocodingService::class);
            $metaChanged = false;

            if (! $senderCoords && $senderAddress !== '') {
                $senderCoords = $geocoder->geocodeAddress($senderAddress);
                if ($senderCoords) {
                    $meta['sender_lat'] = $senderCoords['lat'];
                    $meta['sender_lng'] = $senderCoords['lng'];
                    $metaChanged = true;
                }
            }

            if (! $receiverCoords && $receiverAddress !== '') {
                $receiverCoords = $geocoder->geocodeAddress($receiverAddress);
                if ($receiverCoords) {
                    $meta['receiver_lat'] = $receiverCoords['lat'];
                    $meta['receiver_lng'] = $receiverCoords['lng'];
                    $metaChanged = true;
                }
            }

            if ($routePolyline === null && $senderCoords && $receiverCoords) {
                $route = $geocoder->getRoute($senderCoords, $receiverCoords);
                if ($route) {
                    $routePolyline = $route['polyline'];
                    $meta['route_polyline'] = $route['polyline'];
                    $meta['route_distance_m'] = $route['distance_m'];
                    $meta['route_duration_s'] = $route['duration_s'];
                    $metaChanged = true;
                }
            }

            if ($metaChanged) {
                try {
                    $shipment->forceFill(['meta' => $meta])->save();
                } catch (Throwable $exception) {
                    Log::warning('Failed to persist shipment tracking meta', [
                        'shipment_id' => $shipment->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $position = app(TrackingPositionCalculator::class)
            ->compute($shipment, $senderCoords, $receiverCoords, $routePolyline);

        return [
            'shipment_id' => (int) $shipment->id,
            'awb_number' => (string) ($shipment->awb_number ?? ''),
            'provider' => (string) ($shipment->provider ?? ''),
            'status_code' => $position['status_code'],
            'status_label' => $position['status_label'],
            'status_display' => (string) ($shipment->status ?? $position['status_label']),
            'progress' => $position['progress'],
            'eta' => $position['eta'],
            'sender' => $senderCoords ? [
                'address' => $senderAddress,
                'lat' => $senderCoords['lat'],
                'lng' => $senderCoords['lng'],
            ] : ['address' => $senderAddress, 'lat' => null, 'lng' => null],
            'receiver' => $receiverCoords ? [
                'address' => $receiverAddress,
                'lat' => $receiverCoords['lat'],
                'lng' => $receiverCoords['lng'],
            ] : ['address' => $receiverAddress, 'lat' => null, 'lng' => null],
            'current_position' => $position['position_lat'] !== null && $position['position_lng'] !== null ? [
                'lat' => $position['position_lat'],
                'lng' => $position['position_lng'],
            ] : null,
            'route_polyline' => $routePolyline,
            'route_distance_km' => isset($meta['route_distance_m'])
                ? round(((float) $meta['route_distance_m']) / 1000, 1)
                : null,
            'pickup_scheduled_at' => $shipment->pickup_scheduled_at?->toIso8601String(),
            'estimated_delivery_at' => $shipment->estimated_delivery_at?->toIso8601String(),
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    protected function coordsFromMeta(array $meta, string $prefix): ?array
    {
        $lat = $meta[$prefix.'_lat'] ?? null;
        $lng = $meta[$prefix.'_lng'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * Best-effort broadcast of a shipment status change. Pusher failures
     * must never break the underlying flow that triggered them.
     */
    protected function broadcastTrackingUpdate(Shipment $shipment): void
    {
        try {
            event(new ShipmentTracked($shipment));
        } catch (Throwable $exception) {
            Log::warning('Failed to broadcast ShipmentTracked event', [
                'shipment_id' => $shipment->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
