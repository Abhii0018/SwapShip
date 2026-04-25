<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_razorpay_webhook_marks_order_paid_after_signature_verification(): void
    {
        config()->set('payments.razorpay.webhook_secret', 'webhook_secret_123');

        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $item = Item::create([
            'user_id' => $seller->id,
            'title' => 'Webhook Item',
            'description' => 'Webhook test item',
            'category' => 'Mobiles',
            'condition' => 'new',
            'item_age' => '1 month',
            'type' => 'sell',
            'price' => 1000,
            'location' => 'Delhi',
        ]);
        $exchange = ExchangeRequest::create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'status' => 'In Progress',
        ]);

        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => 'A',
            'receiver_address' => 'B',
            'status' => 'Order Placed',
            'provider' => 'mock',
            'awb_number' => 'MOCK-WEBHOOK-1',
        ]);

        $order = Order::create([
            'shipment_id' => $shipment->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'payment_method' => 'escrow',
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_abc123',
            'item_amount' => 1000,
            'shipping_amount' => 99,
            'platform_fee' => 35,
            'total_amount' => 1134,
            'payment_status' => 'pending',
        ]);

        $payload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_12345',
                        'order_id' => 'order_abc123',
                        'status' => 'captured',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'webhook_secret_123');

        $response = $this->call(
            'POST',
            route('webhooks.payments.razorpay'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertOk()->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pay_12345', $order->payment_reference);
        $this->assertNotNull($order->paid_at);
    }
}
