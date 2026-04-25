<?php

namespace App\Services\Shipping;

use App\Models\ExchangeRequest;
use App\Models\Shipment;

interface ShippingProviderInterface
{
    public function createShipment(ExchangeRequest $exchangeRequest): array;

    public function schedulePickup(Shipment $shipment): array;

    public function normalizeWebhookPayload(array $payload): array;
}
