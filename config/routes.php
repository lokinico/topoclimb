<?php

/**
 * Configuration des routes de l'application
 */

return [
    // Routes publiques
    [
        'method' => 'GET',
        'path' => '/',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'index'
    ],

    // Routes d'authentification
    [
        'method' => 'GET',
        'path' => '/login',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'loginForm'
    ],
    [
        'method' => 'POST',
        'path' => '/login',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'login',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/logout',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'logout',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/register',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'registerForm'
    ],
    [
        'method' => 'POST',
        'path' => '/register',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'register',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Routes pour la récupération de mot de passe
    [
        'method' => 'GET',
        'path' => '/forgot-password',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'forgotPasswordForm'
    ],
    [
        'method' => 'POST',
        'path' => '/forgot-password',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'forgotPassword',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/reset-password',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'resetPasswordForm'
    ],
    [
        'method' => 'POST',
        'path' => '/reset-password',
        'controller' => \TopoclimbCH\Controllers\AuthController::class,
        'action' => 'resetPassword',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Route profil (protégée par authentification)
    [
        'method' => 'GET',
        'path' => '/profile',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'profile',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Routes pour les régions et sites (publiques)
    [
        'method' => 'GET',
        'path' => '/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'show'
    ],

    // Routes des sites
    [
        'method' => 'GET',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'show'
    ],
    [
        'method' => 'GET',
        'path' => '/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\ModeratorMiddleware::class]
    ],

    // Routes pour les secteurs
    [
        'method' => 'GET',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'show'
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'update',
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'delete',
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'delete',
    ],
    // Routes API pour AJAX et intégrations
    [
        'method' => 'GET',
        'path' => '/api/sectors/{id}/routes',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'getRoutes'
    ],
    // Routes pour les voies
    [
        'method' => 'GET',
        'path' => '/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'show'
    ],
    [
        'method' => 'GET',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'update',
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'delete',
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'delete',
    ],

    // Routes pour log ascent (ascension)
    [
        'method' => 'GET',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'logAscent',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'storeAscent',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    [
        'method' => 'GET',
        'path' => '/profile',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'profile',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/settings',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'settings',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Routes statiques
    [
        'method' => 'GET',
        'path' => '/about',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'about'
    ],
    [
        'method' => 'GET',
        'path' => '/contact',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'contact'
    ],
    [
        'method' => 'GET',
        'path' => '/privacy',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'privacy'
    ],
    [
        'method' => 'GET',
        'path' => '/terms',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'terms'
    ],

    // Route newsletter
    [
        'method' => 'POST',
        'path' => '/newsletter',
        'controller' => \TopoclimbCH\Controllers\NewsletterController::class,
        'action' => 'subscribe',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Routes erreurs
    [
        'method' => 'GET',
        'path' => '/404',
        'controller' => \TopoclimbCH\Controllers\ErrorController::class,
        'action' => 'notFound'
    ],
    [
        'method' => 'GET',
        'path' => '/403',
        'controller' => \TopoclimbCH\Controllers\ErrorController::class,
        'action' => 'forbidden'
    ],

    // Routes CRUD pour les régions (avec permissions)
    [
        'method' => 'GET',
        'path' => '/regions/create',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-climbing-data']
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-climbing-data']
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-climbing-data']
        ]
    ],
    [
        'method' => 'PUT',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-climbing-data']
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'destroy',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-climbing-data']
        ]
    ],

    // Routes API pour AJAX et intégrations
    [
        'method' => 'GET',
        'path' => '/api/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'apiIndex'
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/search',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'search'
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/weather',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'weather'
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/events',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'events'
    ],

    // Routes d'export
    [
        'method' => 'GET',
        'path' => '/regions/{id}/export',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'export'
    ],

    // Routes spécifiques pour fonctionnalités avancées
    [
        'method' => 'GET',
        'path' => '/regions/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'sectors'
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/statistics',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'statistics'
    ],

    // Routes pour l'administration (si nécessaire)
    [
        'method' => 'GET',
        'path' => '/admin/regions',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'regions',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],

    // Routes pour les médias des régions (upload/suppression)
    [
        'method' => 'POST',
        'path' => '/regions/{id}/media',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'uploadMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/regions/{id}/media/{mediaId}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'deleteMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],

    // Route pour tester l'API météo (développement)
    [
        'method' => 'GET',
        'path' => '/debug/weather-test',
        'controller' => \TopoclimbCH\Controllers\DebugController::class,
        'action' => 'weatherTest'
    ],

    // ===== ROUTES MANQUANTES AJOUTÉES =====

    // Routes pour les sites (edit manquant)
    [
        'method' => 'GET',
        'path' => '/sites/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/sites/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Routes pour les guides d'escalade (books)
    [
        'method' => 'GET',
        'path' => '/books',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'show'
    ],
    [
        'method' => 'GET',
        'path' => '/books/create',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/books/create',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/books/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}/add-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'addSector',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/books/{id}/add-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'storeSector',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}/remove-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'removeSector',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/books/{id}/remove-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'destroySector',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Routes pour les commentaires et favoris des routes
    [
        'method' => 'GET',
        'path' => '/routes/{id}/comments',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'comments'
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/comments',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'storeComment',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}/favorite',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'favorite',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/favorite',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'toggleFavorite',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Routes pour les alertes
    [
        'method' => 'GET',
        'path' => '/alerts',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/create',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts/create',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/{id}/confirm',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'confirm',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts/{id}/confirm',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'processConfirm',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Route d'administration
    [
        'method' => 'GET',
        'path' => '/admin',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

];
