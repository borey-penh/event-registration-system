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

    /*
    | Auth0-brokered social login. When AUTH0_* is configured, the Google and
    | Facebook buttons route through the Auth0 tenant instead of talking to
    | those providers directly — no Google/Facebook developer apps needed,
    | Auth0's own development keys are used (fine for testing; production
    | should switch the connections to your own keys in the Auth0 dashboard).
    */

    'auth0' => [
        'domain' => env('AUTH0_DOMAIN'), // e.g. dev-xxxx.us.auth0.com
        'client_id' => env('AUTH0_CLIENT_ID'),
        'client_secret' => env('AUTH0_CLIENT_SECRET'),
    ],

    /*
    | Candidate social login (Google / Facebook).
    |
    | The callback redirect URI is built per-request from the origin the SPA
    | is actually served from (localhost, LAN IP, or tunnel), so one set of
    | credentials works everywhere — register each origin's callback URL in
    | the provider console.
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    ],

];
