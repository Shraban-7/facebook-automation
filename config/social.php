<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Platform Social Automation Configuration
    |--------------------------------------------------------------------------
    |
    | All API versions, OAuth scopes, rate limits, and circuit breaker settings
    | are driven by environment variables so platform updates never require
    | hard-coded constant changes.
    |
    */

    'use_new_pipeline' => (bool) env('SOCIAL_USE_NEW_PIPELINE', true),

    'circuit_breaker' => [
        'failure_threshold' => (int) env('SOCIAL_CIRCUIT_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('SOCIAL_CIRCUIT_COOLDOWN_SECONDS', 120),
    ],

    'publishing' => [
        'backoff_seconds' => [30, 120, 600, 1800, 7200],
        'stuck_timeout_minutes' => 10,
    ],

    'retention_days' => [
        'api_call_logs' => (int) env('SOCIAL_LOG_RETENTION_DAYS', 30),
        'webhook_events' => (int) env('SOCIAL_WEBHOOK_RETENTION_DAYS', 60),
    ],

    'providers' => [
        'facebook' => [
            'enabled' => true,
            'name' => 'Facebook Pages',
            'client_id' => env('META_APP_ID', env('FACEBOOK_APP_ID', '')),
            'client_secret' => env('META_APP_SECRET', env('FACEBOOK_APP_SECRET', '')),
            'redirect_uri' => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/facebook/callback'),
            'api_version' => env('META_GRAPH_VERSION', env('FACEBOOK_GRAPH_VERSION', 'v21.0')),
            'base_url' => env('FACEBOOK_GRAPH_BASE_URL', 'https://graph.facebook.com'),
            'oauth_url' => env('FACEBOOK_OAUTH_BASE_URL', 'https://www.facebook.com'),
            'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', 'fb-automation-verify-token')),
            'scopes' => [
                'pages_show_list',
                'pages_manage_posts',
                'pages_read_engagement',
                'pages_messaging',
                'pages_manage_metadata',
            ],
            'rate' => [
                'per_minute' => 120,
                'per_hour' => (int) env('FACEBOOK_RATE_LIMIT_HOURLY', 180),
            ],
        ],

        'instagram' => [
            'enabled' => true,
            'name' => 'Instagram Business',
            'client_id' => env('META_APP_ID', env('FACEBOOK_APP_ID', '')),
            'client_secret' => env('META_APP_SECRET', env('FACEBOOK_APP_SECRET', '')),
            'redirect_uri' => env('INSTAGRAM_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/instagram/callback'),
            'api_version' => env('META_GRAPH_VERSION', env('FACEBOOK_GRAPH_VERSION', 'v21.0')),
            'base_url' => env('FACEBOOK_GRAPH_BASE_URL', 'https://graph.facebook.com'),
            'oauth_url' => env('FACEBOOK_OAUTH_BASE_URL', 'https://www.facebook.com'),
            'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', 'fb-automation-verify-token')),
            'scopes' => [
                'instagram_basic',
                'instagram_content_publish',
                'instagram_manage_comments',
                'instagram_manage_messages',
                'pages_show_list',
                'pages_read_engagement',
            ],
            'daily_publish_limit' => 50,
            'rate' => [
                'per_minute' => 60,
                'per_hour' => 180,
            ],
        ],

        'threads' => [
            'enabled' => true,
            'name' => 'Threads',
            'client_id' => env('THREADS_APP_ID', env('META_APP_ID', env('FACEBOOK_APP_ID', ''))),
            'client_secret' => env('THREADS_APP_SECRET', env('META_APP_SECRET', env('FACEBOOK_APP_SECRET', ''))),
            'redirect_uri' => env('THREADS_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/threads/callback'),
            'api_version' => env('THREADS_API_VERSION', 'v1.0'),
            'base_url' => env('THREADS_BASE_URL', 'https://graph.threads.net'),
            'oauth_url' => 'https://threads.net',
            'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', 'fb-automation-verify-token')),
            'scopes' => [
                'threads_basic',
                'threads_content_publish',
                'threads_manage_replies',
                'threads_read_replies',
            ],
            'rate' => [
                'per_minute' => 60,
                'per_hour' => 180,
            ],
        ],

        'linkedin' => [
            'enabled' => true,
            'name' => 'LinkedIn',
            'client_id' => env('LINKEDIN_CLIENT_ID', ''),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET', ''),
            'redirect_uri' => env('LINKEDIN_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/linkedin/callback'),
            'api_version' => env('LINKEDIN_API_VERSION', '202501'),
            'base_url' => env('LINKEDIN_BASE_URL', 'https://api.linkedin.com'),
            'oauth_url' => 'https://www.linkedin.com',
            'scopes' => [
                'openid',
                'profile',
                'w_member_social',
                'w_organization_social',
                'r_organization_social',
            ],
            'rate' => [
                'per_minute' => 60,
                'per_hour' => 150,
            ],
        ],

        'youtube' => [
            'enabled' => true,
            'name' => 'YouTube',
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => env('YOUTUBE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/youtube/callback'),
            'api_version' => 'v3',
            'base_url' => env('YOUTUBE_BASE_URL', 'https://www.googleapis.com'),
            'oauth_url' => 'https://accounts.google.com',
            'daily_quota' => (int) env('YOUTUBE_DAILY_QUOTA', 10000),
            'upload_quota_cost' => 1600,
            'scopes' => [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/youtube.force-ssl',
                'https://www.googleapis.com/auth/yt-analytics.readonly',
            ],
            'rate' => [
                'per_minute' => 60,
                'per_hour' => 500,
            ],
        ],

        'x' => [
            'enabled' => (bool) env('X_PROVIDER_ENABLED', false),
            'name' => 'X (Twitter)',
            'client_id' => env('X_CLIENT_ID', ''),
            'client_secret' => env('X_CLIENT_SECRET', ''),
            'redirect_uri' => env('X_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/x/callback'),
            'api_version' => '2',
            'base_url' => 'https://api.x.com',
            'oauth_url' => 'https://x.com',
            'scopes' => ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
            'rate' => ['per_minute' => 30, 'per_hour' => 100],
        ],

        'tiktok' => [
            'enabled' => (bool) env('TIKTOK_PROVIDER_ENABLED', false),
            'name' => 'TikTok',
            'client_id' => env('TIKTOK_CLIENT_KEY', ''),
            'client_secret' => env('TIKTOK_CLIENT_SECRET', ''),
            'redirect_uri' => env('TIKTOK_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/tiktok/callback'),
            'api_version' => 'v2',
            'base_url' => 'https://open.tiktokapis.com',
            'oauth_url' => 'https://www.tiktok.com',
            'scopes' => ['user.info.basic', 'video.publish', 'video.upload'],
            'rate' => ['per_minute' => 30, 'per_hour' => 100],
        ],

        'pinterest' => [
            'enabled' => (bool) env('PINTEREST_PROVIDER_ENABLED', false),
            'name' => 'Pinterest',
            'client_id' => env('PINTEREST_APP_ID', ''),
            'client_secret' => env('PINTEREST_APP_SECRET', ''),
            'redirect_uri' => env('PINTEREST_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/social/oauth/pinterest/callback'),
            'api_version' => 'v5',
            'base_url' => 'https://api.pinterest.com',
            'oauth_url' => 'https://www.pinterest.com',
            'scopes' => ['boards:read', 'pins:read', 'pins:write', 'user_accounts:read'],
            'rate' => ['per_minute' => 60, 'per_hour' => 200],
        ],
    ],

];
