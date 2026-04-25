<?php

namespace Tests\Feature;

use App\Models\DeliveryOtp;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SmsAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExchangeOtpPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_requires_both_users_contact_details(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: false);

        $response = $this->actingAs($receiver)->patch(route('exchanges.update-status', $exchange), [
            'status' => 'Accepted',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('shipments', [
            'exchange_request_id' => $exchange->id,
        ]);
    }

    public function test_shipment_starts_only_after_both_parties_confirm(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);

        $this->actingAs($receiver)->patch(route('exchanges.update-status', $exchange), [
            'status' => 'Accepted',
        ])->assertSessionHas('success');

        $exchange->refresh();
        $this->assertNotNull($exchange->receiver_confirmed_at);
        $this->assertNull($exchange->sender_confirmed_at);
        $this->assertDatabaseMissing('shipments', [
            'exchange_request_id' => $exchange->id,
        ]);

        $this->actingAs($sender)->patch(route('exchanges.confirm', $exchange))
            ->assertSessionHas('success');

        $exchange->refresh();
        $this->assertNotNull($exchange->sender_confirmed_at);
        $this->assertDatabaseHas('shipments', [
            'exchange_request_id' => $exchange->id,
        ]);
        $this->assertSame('In Progress', $exchange->status);
    }

    public function test_payment_initiation_creates_order_with_fee_breakdown_and_pending_state(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);
        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $sender->address,
            'receiver_address' => $receiver->address,
            'status' => 'Order Placed',
            'provider' => 'mock',
            'awb_number' => 'MOCK-123456',
        ]);

        $this->actingAs($sender)->post(route('shipments.initiate-payment', $shipment), [
            'payment_method' => 'escrow',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'shipment_id' => $shipment->id,
            'payment_method' => 'escrow',
            'payment_status' => 'pending',
            'settlement_status' => 'pending',
        ]);
    }

    public function test_buyer_can_complete_escrow_payment_from_checkout(): void
    {
        config()->set('payments.razorpay.key_secret', 'test_secret');
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);
        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $sender->address,
            'receiver_address' => $receiver->address,
            'status' => 'Order Placed',
            'provider' => 'mock',
            'awb_number' => 'MOCK-PAYREF',
        ]);
        $order = Order::create([
            'shipment_id' => $shipment->id,
            'buyer_id' => $sender->id,
            'seller_id' => $receiver->id,
            'payment_method' => 'escrow',
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_razorpay_123',
            'item_amount' => 1000,
            'shipping_amount' => 99,
            'platform_fee' => 35,
            'total_amount' => 1134,
            'payment_status' => 'pending',
        ]);

        $paymentId = 'pay_razorpay_999';
        $signature = hash_hmac('sha256', 'order_razorpay_123|'.$paymentId, 'test_secret');

        $this->actingAs($sender)->post(route('payments.pay', $order), [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => 'order_razorpay_123',
            'razorpay_signature' => $signature,
        ])
            ->assertRedirect(route('shipments.index'))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->payment_reference);
    }

    public function test_admin_can_generate_otp_with_sms_audit_log(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);
        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $sender->address,
            'receiver_address' => $receiver->address,
            'status' => 'Order Placed',
            'provider' => 'mock',
            'awb_number' => 'MOCK-654321',
        ]);
        $order = Order::create([
            'shipment_id' => $shipment->id,
            'buyer_id' => $sender->id,
            'seller_id' => $receiver->id,
            'payment_method' => 'cod',
            'item_amount' => 1200,
            'shipping_amount' => 99,
            'total_amount' => 1299,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('shipments.generate-otp', $shipment))
            ->assertSessionHas('success');

        $otp = DeliveryOtp::query()->where('order_id', $order->id)->latest()->first();
        $this->assertNotNull($otp);
        $this->assertNotNull($otp->code_hash);
        $this->assertNotNull($otp->sms_sent_at);
        $this->assertDatabaseHas('sms_audit_logs', [
            'order_id' => $order->id,
            'status' => 'sent',
        ]);
    }

    public function test_verify_otp_completes_delivery_and_settlement_for_cod(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);
        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $sender->address,
            'receiver_address' => $receiver->address,
            'status' => 'In Transit',
            'provider' => 'mock',
            'awb_number' => 'MOCK-OTP111',
            'status_code' => 'in_transit',
            'status_label' => 'In Transit',
        ]);
        $order = Order::create([
            'shipment_id' => $shipment->id,
            'buyer_id' => $sender->id,
            'seller_id' => $receiver->id,
            'payment_method' => 'cod',
            'item_amount' => 5000,
            'shipping_amount' => 99,
            'total_amount' => 5099,
            'payment_status' => 'pending',
        ]);
        DeliveryOtp::create([
            'order_id' => $order->id,
            'code' => '',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'generated_for_user_id' => $sender->id,
        ]);

        $this->actingAs($receiver)->post(route('shipments.verify-otp', $shipment), [
            'otp_code' => '123456',
        ])->assertSessionHas('success');

        $order->refresh();
        $shipment->refresh();
        $exchange->refresh();

        $this->assertNotNull($order->delivery_verified_at);
        $this->assertSame('collected', $order->payment_status);
        $this->assertSame('released', $order->settlement_status);
        $this->assertSame('Delivered', $shipment->status);
        $this->assertSame('Completed', $exchange->status);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'event_code' => 'delivered',
        ]);
    }

    public function test_otp_locks_after_three_failed_attempts(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);
        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $sender->address,
            'receiver_address' => $receiver->address,
            'status' => 'In Transit',
            'provider' => 'mock',
            'awb_number' => 'MOCK-LOCK99',
        ]);
        $order = Order::create([
            'shipment_id' => $shipment->id,
            'buyer_id' => $sender->id,
            'seller_id' => $receiver->id,
            'payment_method' => 'cod',
            'item_amount' => 1000,
            'shipping_amount' => 99,
            'total_amount' => 1099,
        ]);
        $otp = DeliveryOtp::create([
            'order_id' => $order->id,
            'code' => '',
            'code_hash' => Hash::make('999999'),
            'attempts' => 2,
            'expires_at' => now()->addMinutes(10),
            'generated_for_user_id' => $sender->id,
        ]);

        $this->actingAs($receiver)->post(route('shipments.verify-otp', $shipment), [
            'otp_code' => '000000',
        ])->assertSessionHas('error');
        $otp->refresh();
        $this->assertSame(3, $otp->attempts);

        $this->actingAs($receiver)->post(route('shipments.verify-otp', $shipment), [
            'otp_code' => '999999',
        ])->assertSessionHas('error');
    }

    public function test_duplicate_exchange_request_for_same_item_is_blocked(): void
    {
        [$sender, $receiver, $exchange] = $this->makeExchange(withContact: true);

        $response = $this->actingAs($sender)->post(route('exchanges.store', $exchange->item), []);
        $response->assertRedirect(route('chat.index', $exchange))
            ->assertSessionHas('success');

        $count = ExchangeRequest::query()
            ->where('sender_id', $sender->id)
            ->where('item_id', $exchange->item_id)
            ->count();
        $this->assertSame(1, $count);
    }

    private function makeExchange(bool $withContact): array
    {
        $sender = User::factory()->create([
            'phone' => $withContact ? '9999911111' : null,
            'address' => $withContact ? 'New Delhi, India' : null,
        ]);
        $receiver = User::factory()->create([
            'phone' => $withContact ? '9999922222' : null,
            'address' => $withContact ? 'Old Delhi, India' : null,
        ]);
        $item = Item::create([
            'user_id' => $receiver->id,
            'title' => 'Test Phone',
            'description' => 'A test listing',
            'category' => 'Mobiles',
            'condition' => 'like new',
            'item_age' => '6 months old',
            'type' => 'sell',
            'price' => 10000,
            'location' => 'Delhi',
        ]);
        $exchange = ExchangeRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'item_id' => $item->id,
            'status' => 'Pending',
        ]);

        return [$sender, $receiver, $exchange];
    }
}
