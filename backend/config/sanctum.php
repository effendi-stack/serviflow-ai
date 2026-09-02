<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173,127.0.0.1')),
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'verify_csrf_token' => ValidateCsrfToken::class,
        'encrypt_cookies' => EncryptCookies::class,
    ],
];
