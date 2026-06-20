<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── Stripe payment gateway ────────────────────────────────────────────────
    'stripe' => [
        'key'             => env('STRIPE_SECRET_KEY'),        // sk_live_xxx / sk_test_xxx
        'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),    // whsec_xxx (from Stripe dashboard)
        'currency'        => env('STRIPE_CURRENCY', 'USD'),   // default invoice currency
        // Frontend URLs Stripe redirects the user back to after checkout
        'success_url'     => env('STRIPE_SUCCESS_URL', env('FRONTEND_URL') . '/billing/success'),
        'cancel_url'      => env('STRIPE_CANCEL_URL',  env('FRONTEND_URL') . '/billing/cancel'),
    ],

    // ── WHMCS integration ─────────────────────────────────────────────────────
    'whmcs' => [
        'url'           => env('WHMCS_API_URL'),           // https://billing.yourdomain.com
        'identifier'    => env('WHMCS_IDENTIFIER'),
        'secret'        => env('WHMCS_SECRET'),
        'access_key'    => env('WHMCS_ACCESS_KEY', ''),
        // Department IDs — match your WHMCS department setup
        'dept_technical' => env('WHMCS_DEPT_TECHNICAL', 1),
        'dept_billing'   => env('WHMCS_DEPT_BILLING', 2),
        'dept_sales'     => env('WHMCS_DEPT_SALES', 3),
        'dept_general'   => env('WHMCS_DEPT_GENERAL', 4),
    ],

];
