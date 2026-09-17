<?php

$frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:9000'), '/');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([
        $frontend,
        'http://localhost:9000',
        'http://127.0.0.1:9000',
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
