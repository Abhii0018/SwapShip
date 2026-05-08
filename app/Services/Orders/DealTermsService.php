<?php

namespace App\Services\Orders;

use App\Models\ExchangeRequest;
use App\Models\Order;
use App\Models\Shipment;

class DealTermsService
{
    /**
     * Create or update the Order representing the agreed deal terms for a shipment.
     * Shared by ShipmentController::initiatePayment and the dedicated deal-terms page.
     *
     * @param array{payment_method: string, negotiated_item_amount?: float|null, upfront_amount?: float|null} $data
     */
    public function applyForShipment(Shipment $shipment, array $data): Order
    {
        $shipment->loadMissing('exchangeRequest.item', 'exchangeRequest.sender', 'exchangeRequest.receiver');
        $exchange = $shipment->exchangeRequest;
        if (! $exchange) {
            throw new \RuntimeException('Exchange record missing for this shipment.');
        }

        $baseItemAmount = (float) ($exchange->item?->price ?? 0);
        $itemAmount = (float) ($data['negotiated_item_amount'] ?? $baseItemAmount);
        $shippingAmount = $this->estimateShippingCharge(
            (string) ($exchange->sender?->address ?? ''),
            (string) ($exchange->receiver?->address ?? '')
        );
        $platformFee = $this->calculatePlatformFee($itemAmount);
        $totalAmount = round($itemAmount + $shippingAmount + $platformFee, 2);
        $upfrontAmount = (float) ($data['upfront_amount'] ?? $totalAmount);

        if ($data['payment_method'] === 'escrow') {
            if ($upfrontAmount > $totalAmount) {
                throw new \InvalidArgumentException('Upfront amount cannot be greater than total payable.');
            }
            if ($upfrontAmount < 1) {
                throw new \InvalidArgumentException('Upfront amount must be at least INR 1.');
            }
        } else {
            $upfrontAmount = 0.0;
        }

        $remainingAmount = max(0, round($totalAmount - $upfrontAmount, 2));
        $requiresSecondPayment = $data['payment_method'] === 'escrow' && $remainingAmount > 0;

        return Order::query()->updateOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'buyer_id' => $exchange->sender_id,
                'seller_id' => $exchange->receiver_id,
                'payment_method' => $data['payment_method'],
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
    }

    public function estimatedTotalForExchange(ExchangeRequest $exchange, ?float $negotiated = null): array
    {
        $exchange->loadMissing('item', 'sender', 'receiver');
        $base = (float) ($exchange->item?->price ?? 0);
        $itemAmount = $negotiated !== null ? (float) $negotiated : $base;
        $shippingAmount = $this->estimateShippingCharge(
            (string) ($exchange->sender?->address ?? ''),
            (string) ($exchange->receiver?->address ?? '')
        );
        $platformFee = $this->calculatePlatformFee($itemAmount);
        $total = round($itemAmount + $shippingAmount + $platformFee, 2);

        return [
            'item_amount' => $itemAmount,
            'shipping_amount' => $shippingAmount,
            'platform_fee' => $platformFee,
            'total_amount' => $total,
        ];
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
}
