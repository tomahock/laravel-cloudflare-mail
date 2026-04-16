<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Account ID
    |--------------------------------------------------------------------------
    |
    | Your Cloudflare account ID. Found in the Cloudflare dashboard URL or
    | under Account > Overview.
    |
    */
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API Token
    |--------------------------------------------------------------------------
    |
    | API token with Email Service send permissions. Create one at
    | Cloudflare Dashboard > My Profile > API Tokens.
    |
    */
    'api_token' => env('CLOUDFLARE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The Cloudflare API base URL. You should not need to change this.
    |
    */
    'base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for the API response.
    |
    */
    'timeout' => env('CLOUDFLARE_MAIL_TIMEOUT', 30),
];
