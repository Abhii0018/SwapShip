<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\ExchangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_filters_by_price_range_and_distance(): void
    {
        $owner = User::factory()->create();

        Item::create([
            'user_id' => $owner->id,
            'title' => 'Nearby Phone',
            'description' => 'Nearby listing',
            'category' => 'Mobiles',
            'condition' => 'like new',
            'item_age' => '6 months',
            'type' => 'sell',
            'price' => 12000,
            'location' => 'New Delhi, India',
            'location_lat' => 28.6139,
            'location_lng' => 77.2090,
        ]);

        Item::create([
            'user_id' => $owner->id,
            'title' => 'Far Laptop',
            'description' => 'Far listing',
            'category' => 'Laptops',
            'condition' => 'used',
            'item_age' => '1 year',
            'type' => 'sell',
            'price' => 50000,
            'location' => 'Mumbai, India',
            'location_lat' => 19.0760,
            'location_lng' => 72.8777,
        ]);

        $response = $this->get(route('items.index', [
            'min_price' => 10000,
            'max_price' => 15000,
            'distance_km' => 5,
            'user_lat' => 28.6139,
            'user_lng' => 77.2090,
        ]));

        $response->assertOk();
        $response->assertSee('Nearby Phone');
        $response->assertDontSee('Far Laptop');
    }

    public function test_user_can_save_and_delete_search(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('saved-searches.store'), [
            'name' => 'Delhi Phones',
            'filters' => [
                'category' => 'Mobiles',
                'location' => 'New Delhi',
                'min_price' => '5000',
                'max_price' => '20000',
            ],
        ])->assertSessionHas('success');

        $saved = SavedSearch::query()->first();
        $this->assertNotNull($saved);
        $this->assertSame('Delhi Phones', $saved->name);

        $this->actingAs($user)->delete(route('saved-searches.destroy', $saved))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_recommended_first_prioritizes_relevant_matches(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        $viewerItem = Item::create([
            'user_id' => $viewer->id,
            'title' => 'My Phone Listing',
            'description' => 'history',
            'category' => 'Mobiles',
            'condition' => 'like new',
            'item_age' => '3 months',
            'type' => 'sell',
            'price' => 11000,
            'location' => 'New Delhi, India',
            'location_lat' => 28.6139,
            'location_lng' => 77.2090,
        ]);

        ExchangeRequest::create([
            'sender_id' => $viewer->id,
            'receiver_id' => $owner->id,
            'item_id' => $viewerItem->id,
            'status' => 'Pending',
        ]);

        Item::create([
            'user_id' => $owner->id,
            'title' => 'Suggested Mobile Match',
            'description' => 'good match',
            'category' => 'Mobiles',
            'condition' => 'used',
            'item_age' => '1 year',
            'type' => 'sell',
            'price' => 12000,
            'location' => 'New Delhi, India',
            'location_lat' => 28.6141,
            'location_lng' => 77.2092,
        ]);

        Item::create([
            'user_id' => $owner->id,
            'title' => 'Less Relevant Furniture',
            'description' => 'less match',
            'category' => 'Furniture',
            'condition' => 'used',
            'item_age' => '2 years',
            'type' => 'sell',
            'price' => 45000,
            'location' => 'Mumbai, India',
            'location_lat' => 19.0760,
            'location_lng' => 72.8777,
        ]);

        $response = $this->actingAs($viewer)->get(route('items.index', [
            'recommended_first' => 1,
            'user_lat' => 28.6139,
            'user_lng' => 77.2090,
        ]));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Suggested Mobile Match',
            'Less Relevant Furniture',
        ]);
        $response->assertSee('Matches your category');
        $response->assertSee('Near you');
    }
}
