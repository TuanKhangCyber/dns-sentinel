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

    'dns' => [
        'rdap_url' => env('DNS_RDAP_URL', 'https://rdap.org/domain/'),
        'geo_url' => env('DNS_GEO_URL', 'https://ipwho.is/'),
        'public_ip_url' => env('DNS_PUBLIC_IP_URL', 'https://api.ipify.org'),
        'cache_ttl_seconds' => (int) env('DNS_CACHE_TTL_SECONDS', 300),
        'http_timeout_seconds' => (int) env('DNS_HTTP_TIMEOUT_SECONDS', 5),
        'response_max_bytes' => (int) env('DNS_HTTP_RESPONSE_MAX_BYTES', 1024 * 1024),
        'traceroute_enabled' => filter_var(env('DNS_TRACEROUTE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'traceroute_binary' => env('DNS_TRACEROUTE_BINARY', PHP_OS_FAMILY === 'Windows' ? 'C:\\Windows\\System32\\TRACERT.EXE' : 'traceroute'),
        'traceroute_timeout_seconds' => (int) env('DNS_TRACEROUTE_TIMEOUT_SECONDS', 35),
        'traceroute_max_output_bytes' => (int) env('DNS_TRACEROUTE_MAX_OUTPUT_BYTES', 65536),
    ],

];
