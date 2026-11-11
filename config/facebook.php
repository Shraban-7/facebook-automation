<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta / Facebook Graph API Credentials & Settings
    |--------------------------------------------------------------------------
    |
    | Official Meta Graph API and Messenger Platform configuration.
    | All calls must use official Graph API endpoints and respect rate limits.
    |
    */

    'app_id' => env('FACEBOOK_APP_ID', ''),

    'app_secret' => env('FACEBOOK_APP_SECRET', ''),

    'redirect_uri' => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/oauth/facebook/callback'),

    'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', 'fb-automation-verify-token'),

    'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),

    'graph_base_url' => env('FACEBOOK_GRAPH_BASE_URL', 'https://graph.facebook.com'),

    'oauth_base_url' => env('FACEBOOK_OAUTH_BASE_URL', 'https://www.facebook.com'),

    /*
    |--------------------------------------------------------------------------
    | Required OAuth Scopes (Minimal Privilege Principle)
    |--------------------------------------------------------------------------
    */
    'scopes' => [
        'pages_show_list',
        'pages_manage_posts',
        'pages_read_engagement',
        'pages_messaging',
        'pages_manage_metadata',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Messaging Window Policy
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_user_hourly' => (int) env('FACEBOOK_RATE_LIMIT_HOURLY', 180),
        'usage_header_threshold_percent' => (int) env('FACEBOOK_USAGE_THRESHOLD_PERCENT', 80),
    ],

    'messaging' => [
        'standard_window_hours' => 24,
        'default_cooldown_seconds' => 300,
    ],

];
