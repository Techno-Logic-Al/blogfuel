<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Free Generation Quota
    |--------------------------------------------------------------------------
    |
    | Each verified user can generate this many articles before paid access is
    | required to continue using the app.
    |
    */

    'free_generations' => (int) env('BLOGFUEL_FREE_GENERATIONS', 5),

    /*
    |--------------------------------------------------------------------------
    | Guest Preview Quota
    |--------------------------------------------------------------------------
    |
    | Guests can use this many trial generations before registration is
    | required. The generated draft stays in session until the visitor signs
    | in and publishes it.
    |
    */

    'guest_free_generations' => (int) env('BLOGFUEL_GUEST_FREE_GENERATIONS', 1),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | These plans expect recurring Stripe Price IDs created in your Stripe
    | dashboard. The optional price labels are purely for the UI and should
    | match the actual amounts configured in Stripe.
    |
    */

    'plans' => [
        'monthly' => [
            'label' => 'Pro Monthly',
            'eyebrow' => 'Unlimited access',
            'price_label' => env('STRIPE_MONTHLY_PRICE_LABEL', '£19 / month'),
            'description' => 'Unlimited article generation, publishing, and sharing.',
            'capabilities' => [
                'Includes GPT-5.4',
                'Flexible monthly billing',
            ],
            'cta' => 'Choose monthly',
            'stripe_price_id' => env('STRIPE_PRICE_MONTHLY'),
            'badge' => null,
        ],
        'annual' => [
            'label' => 'Pro Annual',
            'eyebrow' => 'Unlimited access',
            'price_label' => env('STRIPE_ANNUAL_PRICE_LABEL', '£190 / year'),
            'description' => 'Unlimited article generation, publishing, and sharing.',
            'capabilities' => [
                'Includes GPT-5.4',
                'Save £38 compared with monthly',
            ],
            'cta' => 'Choose annual',
            'stripe_price_id' => env('STRIPE_PRICE_ANNUAL'),
            'badge' => env('STRIPE_ANNUAL_BADGE', 'Best overall value'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | One-Time Credit Packs
    |--------------------------------------------------------------------------
    |
    | These offers expect one-time Stripe Price IDs. They add article credits
    | to the account without creating a recurring subscription.
    |
    */

    'credit_packs' => [
        'pack_25' => [
            'label' => '25-Article Pack',
            'eyebrow' => 'No subscription',
            'price_label' => env('STRIPE_PACK_25_PRICE_LABEL', '£12 one-off'),
            'description' => '25 article generations for occasional publishing.',
            'capabilities' => [
                'Use GPT-5 mini and GPT-5.2',
                'No auto-renewal',
            ],
            'cta' => 'Buy 25 articles',
            'credits' => 25,
            'stripe_price_id' => env('STRIPE_PRICE_PACK_25'),
            'badge' => null,
        ],
        'pack_100' => [
            'label' => '100-Article Pack',
            'eyebrow' => 'No subscription',
            'price_label' => env('STRIPE_PACK_100_PRICE_LABEL', '£39 one-off'),
            'description' => '100 article generations for higher-volume writing.',
            'capabilities' => [
                'Use GPT-5 mini and GPT-5.2',
                'No auto-renewal',
            ],
            'cta' => 'Buy 100 articles',
            'credits' => 100,
            'stripe_price_id' => env('STRIPE_PRICE_PACK_100'),
            'badge' => env('STRIPE_PACK_100_BADGE', 'Best pack value'),
        ],
    ],

];
