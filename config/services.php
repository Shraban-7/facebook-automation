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

    'facebook' => [
        'client_id' => env('META_APP_ID', env('FACEBOOK_APP_ID')),
        'client_secret' => env('META_APP_SECRET', env('FACEBOOK_APP_SECRET')),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/oauth/facebook/callback'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN')),
    ],

    'instagram' => [
        'client_id' => env('META_APP_ID', env('FACEBOOK_APP_ID')),
        'client_secret' => env('META_APP_SECRET', env('FACEBOOK_APP_SECRET')),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/instagram/callback'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN')),
    ],

    'threads' => [
        'client_id' => env('THREADS_APP_ID', env('META_APP_ID', env('FACEBOOK_APP_ID'))),
        'client_secret' => env('THREADS_APP_SECRET', env('META_APP_SECRET', env('FACEBOOK_APP_SECRET'))),
        'redirect' => env('THREADS_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/threads/callback'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN')),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/linkedin/callback'),
    ],

    'youtube' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('YOUTUBE_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/youtube/callback'),
    ],

    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/x/callback'),
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/tiktok/callback'),
    ],

    'pinterest' => [
        'client_id' => env('PINTEREST_APP_ID'),
        'client_secret' => env('PINTEREST_APP_SECRET'),
        'redirect' => env('PINTEREST_REDIRECT_URI', env('APP_URL').'/social/accounts/oauth/pinterest/callback'),
    ],

];
