<?php

return [
    'default_provider' => env('SHIPPING_DEFAULT_PROVIDER', 'mock'),
    'webhook_token' => env('SHIPPING_WEBHOOK_TOKEN'),

    'providers' => [
        'mock' => [
            'class' => App\Services\Shipping\MockShippingProvider::class,
            'tracking_base_url' => env('MOCK_SHIPPING_TRACKING_BASE_URL', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        ],
    ],

    'status_map' => [
        'order_placed' => 'Order Placed',
        'pickup_scheduled' => 'Order Placed',
        'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit',
        'out_for_delivery' => 'In Transit',
        'delivered' => 'Delivered',
        'failed' => 'In Transit',
        'cancelled' => 'Order Placed',
    ],

    'geocoding' => [
        'nominatim_user_agent' => env('NOMINATIM_USER_AGENT', 'SwapShip/1.0 (contact@swapship.local)'),
        'ors_api_key' => env('ORS_API_KEY', ''),
        'cache_ttl_seconds' => (int) env('GEOCODING_CACHE_TTL', 86400),
    ],

    'tracking' => [
        'eta_hours_default' => (int) env('SHIPPING_ETA_HOURS', 72),
        'poll_interval_seconds' => (int) env('SHIPPING_POLL_INTERVAL', 8),
    ],
];
