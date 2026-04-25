<?php

namespace Tests\Feature;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_buyer_cannot_initiate_payment_for_shipment(): void
    {
        [$buyer, $seller, $shipment] = $this->makeShipmentContext();

        $this->actingAs($seller)
            ->post(route('shipments.initiate-payment', $shipment), [
                'payment_method' => 'escrow',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('orders', [
            'shipment_id' => $shipment->id,
        ]);
    }

    public function test_unrelated_user_cannot_update_shipment_status(): void
    {
        [, , $shipment] = $this->makeShipmentContext();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patch(route('shipments.update-status', $shipment), [
                'status' => 'In Transit',
            ])
            ->assertForbidden();
    }

    public function test_non_admin_cannot_simulate_shipment_event(): void
    {
        [$buyer, , $shipment] = $this->makeShipmentContext();

        $this->actingAs($buyer)
            ->post(route('shipments.simulate-event', $shipment), [
                'status_code' => 'picked_up',
            ])
            ->assertForbidden();
    }

    private function makeShipmentContext(): array
    {
        $buyer = User::factory()->create([
            'phone' => '9999911111',
            'address' => 'New Delhi, India',
        ]);
        $seller = User::factory()->create([
            'phone' => '9999922222',
            'address' => 'Old Delhi, India',
        ]);

        $item = Item::create([
            'user_id' => $seller->id,
            'title' => 'Secure Test Item',
            'description' => 'Item for security tests',
            'category' => 'Mobiles',
            'condition' => 'used',
            'item_age' => '1 year old',
            'type' => 'sell',
            'price' => 20000,
            'location' => 'Delhi',
        ]);

        $exchange = ExchangeRequest::create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'status' => 'In Progress',
            'sender_confirmed_at' => now(),
            'receiver_confirmed_at' => now(),
        ]);

        $shipment = Shipment::create([
            'exchange_request_id' => $exchange->id,
            'sender_address' => $buyer->address,
            'receiver_address' => $seller->address,
            'status' => 'Order Placed',
            'provider' => 'mock',
            'awb_number' => 'MOCK-SECURE-001',
        ]);

        return [$buyer, $seller, $shipment];
    }
}
