<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Connect Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the Stripe Connect integration for Express accounts.
    | All operations are performed in test mode only for development.
    |
    */

    'connect' => [
        'test_secret_key' => env('STRIPE_TEST_SECRET_KEY'),
        'test_webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),

        // هنا جعلنا القيم تُقرأ من الـ .env وفي حال عدم وجودها تأخذ القيمة الافتراضية
        'test_mode' => env('STRIPE_TEST_MODE', true),

        'countries_supported' => ['US', 'CA', 'GB', 'DE', 'FR', 'ES', 'IT', 'AU'],
        'currencies_supported' => ['USD', 'CAD', 'GBP', 'EUR', 'AUD'],
    ],
];
