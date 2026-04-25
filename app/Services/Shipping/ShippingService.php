<?php

namespace App\Services\Shipping;

use App\Models\ExchangeRequest;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use RuntimeException;

class ShippingService
{
    public function createShipmentForExchange(ExchangeRequest $exchangeRequest): Shipment
    {
        $provider = $this->provider();
        $payload = $provider->createShipment($exchangeRequest);

        $shipment = Shipment::query()->updateOrCreate(
            ['exchange_request_id' => $exchangeRequest->id],
            [
                'sender_address' => $exchangeRequest->sender->address ?? 'Address not set',
                'receiver_address' => $exchangeRequest->receiver->address ?? 'Address not set',
                'provider' => $payload['provider'] ?? config('shipping.default_provider'),
                'awb_number' => $payload['awb_number'] ?? null,
                'tracking_url' => $payload['tracking_url'] ?? null,
                'label_url' => $payload['label_url'] ?? null,
                'status' => $payload['status'] ?? 'Order Placed',
                'status_code' => $payload['status_code'] ?? 'order_placed',
                'status_label' => $payload['status_label'] ?? 'Order Placed',
                'estimated_delivery_at' => $payload['estimated_delivery_at'] ?? null,
                'pickup_scheduled_at' => $payload['pickup_scheduled_at'] ?? null,
                'meta' => $payload['meta'] ?? [],
            ]
        );

        $this->recordEvent(
            $shipment,
            $shipment->status_code ?? 'order_placed',
            $shipment->status_label ?? $shipment->status,
            $payload
        );

        return $shipment;
    }

    public function schedulePickup(Shipment $shipment): Shipment
    {
        $provider = $this->provider($shipment->provider ?: null);
        $payload = $provider->schedulePickup($shipment);

        $shipment->update([
            'pickup_scheduled_at' => $payload['pickup_scheduled_at'] ?? now(),
            'status' => $payload['status'] ?? $shipment->status,
            'status_code' => $payload['status_code'] ?? $shipment->status_code,
            'status_label' => $payload['status_label'] ?? $shipment->status_label,
            'meta' => $payload['meta'] ?? $shipment->meta,
        ]);

        $this->recordEvent(
            $shipment,
            $shipment->status_code ?? 'picked_up',
            $shipment->status_label ?? $shipment->status,
            $payload
        );
        $this->syncExchangeStatus($shipment);

        return $shipment;
    }

    public function processWebhook(string $providerKey, array $payload): ?Shipment
    {
        $provider = $this->provider($providerKey);
        $normalized = $provider->normalizeWebhookPayload($payload);
        $awb = trim((string) ($normalized['awb_number'] ?? ''));
        if ($awb === '') {
            return null;
        }

        $shipment = Shipment::query()->where('awb_number', $awb)->first();
        if (! $shipment) {
            return null;
        }

        $statusCode = (string) ($normalized['status_code'] ?? $shipment->status_code ?? 'in_transit');
        $statusLabel = (string) ($normalized['status_label'] ?? $normalized['status'] ?? $statusCode);
        $status = $this->mapStatusCodeToDisplayStatus($statusCode);

        $shipment->update([
            'status' => $status,
            'status_code' => $statusCode,
            'status_label' => $statusLabel,
            'meta' => array_merge((array) $shipment->meta, ['last_webhook' => now()->toDateTimeString()]),
        ]);

        $this->recordEvent(
            $shipment,
            $shipment->status_code ?? 'updated',
            $shipment->status_label ?? $shipment->status,
            $normalized['raw_payload'] ?? $payload,
            $normalized['occurred_at'] ?? now()
        );
        $this->syncExchangeStatus($shipment);

        return $shipment;
    }

    public function syncExchangeStatus(Shipment $shipment): void
    {
        $statusCode = (string) ($shipment->status_code ?? '');
        if (in_array($statusCode, ['picked_up', 'in_transit', 'out_for_delivery'], true)) {
            $shipment->exchangeRequest()->update(['status' => 'In Progress']);
        }
        if ($statusCode === 'delivered') {
            $shipment->exchangeRequest()->update(['status' => 'Completed']);
        }
    }

    protected function recordEvent(Shipment $shipment, string $code, string $label, mixed $payload, mixed $occurredAt = null): void
    {
        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'event_code' => $code,
            'event_label' => $label,
            'raw_payload' => $payload,
            'occurred_at' => $occurredAt ?: now(),
        ]);
    }

    protected function provider(?string $providerKey = null): ShippingProviderInterface
    {
        $key = $providerKey ?: config('shipping.default_provider', 'mock');
        $providerConfig = config('shipping.providers.'.$key);
        if (! is_array($providerConfig) || empty($providerConfig['class'])) {
            throw new RuntimeException('Shipping provider not configured: '.$key);
        }
        $class = $providerConfig['class'];

        return new $class($providerConfig);
    }

    protected function mapStatusCodeToDisplayStatus(string $statusCode): string
    {
        $map = (array) config('shipping.status_map', []);

        return (string) ($map[$statusCode] ?? 'In Transit');
    }
}
