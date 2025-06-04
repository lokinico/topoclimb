<?php

// config/middleware.php - Configuration des middlewares

return [
    // Middlewares globaux (appliqués à toutes les routes)
    'global' => [
        \TopoclimbCH\Middleware\LogRequestMiddleware::class,
        \TopoclimbCH\Middleware\MaintenanceMiddleware::class,
    ],

    // Groupes de middlewares
    'groups' => [
        'web' => [
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
        ],

        'api' => [
            \TopoclimbCH\Middleware\ThrottleMiddleware::class,
            \TopoclimbCH\Middleware\CorsMiddleware::class,
        ],

        'admin' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
        ]
    ],

    // Alias des middlewares
    'aliases' => [
        'auth' => \TopoclimbCH\Middleware\AuthMiddleware::class,
        'admin' => \TopoclimbCH\Middleware\AdminMiddleware::class,
        'csrf' => \TopoclimbCH\Middleware\CsrfMiddleware::class,
        'cors' => \TopoclimbCH\Middleware\CorsMiddleware::class,
        'api.auth' => \TopoclimbCH\Middleware\ApiAuthMiddleware::class,
        'api.throttle' => \TopoclimbCH\Middleware\ThrottleMiddleware::class,
        'maintenance' => \TopoclimbCH\Middleware\MaintenanceMiddleware::class,
        'log.requests' => \TopoclimbCH\Middleware\LogRequestMiddleware::class,
    ],

    // Configuration spécifique des middlewares
    'config' => [
        'throttle' => [
            'max_attempts' => 60,
            'decay_minutes' => 1
        ],

        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'supports_credentials' => false
        ]
    ]
];
