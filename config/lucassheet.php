<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access token
    |--------------------------------------------------------------------------
    |
    | APP_ACCESS_TOKEN is the simplest option. For production, prefer setting
    | APP_ACCESS_TOKEN_HASH to the sha256 hash of the token and leave the plain
    | token out of the server environment.
    |
    */
    'access_token' => env('APP_ACCESS_TOKEN'),
    'access_token_hash' => env('APP_ACCESS_TOKEN_HASH'),
];
