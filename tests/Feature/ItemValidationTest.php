<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_is_required_when_listing_type_is_sell()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('items.store'), [
            'title' => 'My Book',
            'listing_type' => 'sell',
            // price omitted
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_exchange_preference_is_required_when_listing_type_is_exchange()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('items.store'), [
            'title' => 'My Book',
            'listing_type' => 'exchange',
            // exchange_preference omitted
        ]);

        $response->assertSessionHasErrors('exchange_preference');
    }

    public function test_price_and_exchange_preference_required_when_listing_type_is_both()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('items.store'), [
            'title' => 'My Book',
            'listing_type' => 'both',
            // missing both
        ]);

        $response->assertSessionHasErrors(['price', 'exchange_preference']);
    }
}
