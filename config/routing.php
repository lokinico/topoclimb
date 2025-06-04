<?php

// config/routing.php - Configuration du routage


return [
    // Cache des routes
    'cache_enabled' => $_ENV['APP_ENV'] === 'production',
    'cache_path' => storage_path('framework/routes.cache'),

    // URL de base
    'base_url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'force_https' => $_ENV['APP_ENV'] === 'production',
    'default_domain' => $_ENV['APP_DOMAIN'] ?? 'localhost',

    // API
    'api_version' => 'v1',
    'api_rate_limit' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000
    ],

    // Domaines supportés
    'supported_domains' => [
        'topoclimb.ch',
        'api.topoclimb.ch',
        'admin.topoclimb.ch',
        'www.topoclimb.ch'
    ],

    // Model binding automatique
    'route_model_binding' => true,

    // Middlewares globaux
    'global_middlewares' => [
        'log.requests',
        'maintenance'
    ],

    // Groupes de middlewares
    'middleware_groups' => [
        'web' => [
            'csrf',
            'auth:optional'
        ],
        'api' => [
            'api.throttle:60,1',
            'api.cors',
            'api.auth'
        ],
        'admin' => [
            'auth',
            'admin',
            'csrf'
        ]
    ],

    // Contraintes par défaut
    'default_constraints' => [
        'id' => '\d+',
        'slug' => '[a-z0-9\-]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'locale' => '(fr|en|de)'
    ],

    // Redirections permanentes
    'permanent_redirects' => [
        '/secteur/{id}' => '/climbing/sectors/{id}',
        '/voie/{id}' => '/climbing/routes/{id}',
        '/ascension/{id}' => '/users/ascents/{id}',
        '/topo/{id}' => '/climbing/guides/{id}'
    ],

    // Routes conditionnelles
    'conditional_routes' => [
        'development' => [
            '/dev/*',
            '/debug/*'
        ],
        'admin' => [
            '/admin/*'
        ]
    ]
];
