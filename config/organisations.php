<?php

return [
    'self_service_provisioning' => (bool) env('ORGANISATION_SELF_SERVICE_PROVISIONING', false),

    'platform_domain' => parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',

    'public_domain' => env(
        'ORGANISATION_PUBLIC_DOMAIN',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',
    ),

    'reserved_public_subdomains' => [
        'admin',
        'api',
        'app',
        'assets',
        'auth',
        'cdn',
        'demo',
        'mail',
        'platform',
        'static',
        'status',
        'support',
        'www',
    ],
];
