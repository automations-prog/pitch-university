<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'customer_api' => [
        'domain' => env('CUSTOMER_API_DOMAIN'),
        'key' => env('CUSTOMER_API_KEY'),
        'location_id' => env('CUSTOMER_API_LOCATION_ID'),
        'page_size' => env('CUSTOMER_API_PAGE_SIZE', 50),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'realtime_model' => env('OPENAI_REALTIME_MODEL', 'gpt-realtime'),
        'realtime_voice' => env('OPENAI_REALTIME_VOICE', 'verse'),
        'roleplay_daily_limit' => (int) env('OPENAI_ROLEPLAY_DAILY_LIMIT', 20),
    ],

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        // Speaks the roleplay consumer instead of OpenAI's voice. OpenAI
        // still listens and decides what the consumer says.
        'enabled' => (bool) env('ELEVENLABS_ROLEPLAY_ENABLED', false),
        'model' => env('ELEVENLABS_MODEL', 'eleven_flash_v2_5'),
        // Comma-separated voice ids. A consumer gets a voice of their
        // gender; with none of it, they keep OpenAI's voice.
        'voices' => [
            'female' => array_values(array_filter(explode(',', (string) env('ELEVENLABS_VOICES_FEMALE', '')))),
            'male' => array_values(array_filter(explode(',', (string) env('ELEVENLABS_VOICES_MALE', '')))),
        ],
    ],

];
