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

    'hemis' => [
        'client_id' => env('HEMIS_CLIENT_ID'),
        'client_secret' => env('HEMIS_CLIENT_SECRET'),
        'redirect_uri' => env('REDIRECT_URL'),
        'url' => env('HEMIS_URL'),
        'api_key' => env('HEMIS_API_KEY'),
        'employee_api_url' => env('HEMIS_EMPLOYEE_API_URL', 'https://student.karsu.uz/rest/v1/data/employee-list'),
    ],

];
