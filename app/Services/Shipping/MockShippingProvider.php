<?php

namespace App\Services\Shipping;

use App\Models\ExchangeRequest;
use App\Models\Shipment;
use Illuminate\Support\Str;

class MockShippingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function createShipment(ExchangeRequest $exchangeRequest): array
    {
        $awb = 'MOCK-'.strtoupper(Str::random(10));
        $trackingBase = $this->config['tracking_base_url'] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        return [
            'provider' => 'mock',
            'awb_number' => $awb,
            'tracking_url' => rtrim($trackingBase, '/').'?awb='.$awb,
            'label_url' => null,
            'status' => 'Order Placed',
            'status_code' => 'order_placed',
            'status_label' => 'Order Placed',
            'estimated_delivery_at' => now()->addDays(4),
            'pickup_scheduled_at' => null,
            'meta' => [
                'source' => 'mock_provider',
                'exchange_request_id' => $exchangeRequest->id,
            ],
        ];
    }

    public function schedulePickup(Shipment $shipment): array
    {
        return [
            'pickup_scheduled_at' => now()->addHours(8),
            'status' => 'Picked Up',
            'status_code' => 'picked_up',
            'status_label' => 'Picked Up',
            'meta' => array_merge((array) $shipment->meta, ['pickup_confirmation' => 'mock-confirmed']),
        ];
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        return [
            'awb_number' => (string) ($payload['awb_number'] ?? ''),
            'status_code' => (string) ($payload['status_code'] ?? 'in_transit'),
            'status_label' => (string) ($payload['status_label'] ?? 'In Transit'),
            'status' => (string) ($payload['status'] ?? $payload['status_label'] ?? 'In Transit'),
            'occurred_at' => $payload['occurred_at'] ?? now(),
            'raw_payload' => $payload,
        ];
    }
}
