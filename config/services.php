<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => env('OPENAI_TIMEOUT', 25),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
        'timeout' => env('STRIPE_TIMEOUT', 30),
    ],

    'recaptcha' => [
        'enterprise' => [
            'enabled' => env('RECAPTCHA_ENTERPRISE_ENABLED', false),
            'site_key' => env('RECAPTCHA_ENTERPRISE_SITE_KEY'),
            'api_key' => env('RECAPTCHA_ENTERPRISE_API_KEY'),
            'project_id' => env('RECAPTCHA_ENTERPRISE_PROJECT_ID'),
            'base_url' => env('RECAPTCHA_ENTERPRISE_BASE_URL', 'https://recaptchaenterprise.googleapis.com/v1'),
            'minimum_score' => env('RECAPTCHA_ENTERPRISE_MIN_SCORE', 0.5),
            'timeout' => env('RECAPTCHA_ENTERPRISE_TIMEOUT', 10),
        ],
    ],

];
