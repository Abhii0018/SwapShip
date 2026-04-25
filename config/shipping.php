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
];
