<?php

return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'razorpay'),
    'platform_fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 1),
    'platform_fee_flat' => (float) env('PLATFORM_FEE_FLAT', 2),
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
];
