<?php

/**
 * Configuration des routes de l'application
 */

return [
    // ========== ROUTES DEMO & PREVIEW (PUBLIC) ==========
    // Pages de démonstration pour utilisateurs non-connectés
    [
        'method' => 'GET',
        'path' => '/demo',
        'controller' => \TopoclimbCH\Controllers\DemoController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/demo/regions',
        'controller' => \TopoclimbCH\Controllers\DemoController::class,
        'action' => 'regions'
    ],
    [
        'method' => 'GET',
        'path' => '/demo/sites',
        'controller' => \TopoclimbCH\Controllers\DemoController::class,
        'action' => 'sites'
    ],
    [
        'method' => 'GET',
        'path' => '/demo/sectors',
        'controller' => \TopoclimbCH\Controllers\DemoController::class,
        'action' => 'sectors'
    ],
    [
        'method' => 'GET',
        'path' => '/demo/routes',
        'controller' => \TopoclimbCH\Controllers\DemoController::class,
        'action' => 'routes'
    ],
    
    // Aperçus ultra-limités pour public
    [
        'method' => 'GET',
        'path' => '/preview/region/{id}',
        'controller' => \TopoclimbCH\Controllers\PreviewController::class,
        'action' => 'region'
    ],
    [
        'method' => 'GET',
        'path' => '/preview/blocked',
        'controller' => \TopoclimbCH\Controllers\PreviewController::class,
        'action' => 'blocked'
    ],

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

    // Routes pour les régions et sites (contrôle d'accès hiérarchique)
    [
        'method' => 'GET',
        'path' => '/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
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
        'method' => 'GET',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'show'
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Routes des sites
    [
        'method' => 'GET',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\ModeratorMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'show',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{region_id}/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'createFromRegion',
        'middlewares' => [\TopoclimbCH\Middleware\ModeratorMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // Routes pour les secteurs
    [
        'method' => 'GET',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'show',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/{site_id}/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'createFromSite',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
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
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'PUT',
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
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
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'show',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/test/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'testCreate'
    ],
    [
        'method' => 'GET',
        'path' => '/test/routes/create-auth',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'testCreateAuth',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{sector_id}/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'createFromSector',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
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
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'PUT',
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
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

    // Routes pour ascensions (général)
    [
        'method' => 'GET',
        'path' => '/ascents',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents/create',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/ascents',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents/{id}',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'show',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'edit',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'PUT',
        'path' => '/ascents/{id}',
        'controller' => \TopoclimbCH\Controllers\AscentController::class,
        'action' => 'update',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\CsrfMiddleware::class]
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
        'path' => '/api/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiIndex'
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/{region_id}/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiByRegion'
    ],
    [
        'method' => 'GET',
        'path' => '/api/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'apiIndex'
    ],
    [
        'method' => 'GET',
        'path' => '/api/sites/{site_id}/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'apiBySite'
    ],
    [
        'method' => 'GET',
        'path' => '/api/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'apiIndex'
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
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'update',
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
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/books/create',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'show',
        'middlewares' => [\TopoclimbCH\Middleware\AccessControlMiddleware::class]
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

    // ===== ROUTES ESSENTIELLES MANQUANTES POUR APPLICATION D'ESCALADE COMPLÈTE =====

    // **1. PROFILS UTILISATEURS ET SOCIAL**
    // Pages développement futur : Profils grimpeurs détaillés, statistiques personnelles, followers
    [
        'method' => 'GET',
        'path' => '/users',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'index'  // Liste publique des grimpeurs actifs
    ],
    [
        'method' => 'GET', 
        'path' => '/users/{id}',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'show'  // Profil public : stats, ascensions récentes, photos
    ],
    [
        'method' => 'GET',
        'path' => '/users/{id}/ascents',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'ascents'  // Carnet d'ascensions détaillé avec filtres
    ],
    [
        'method' => 'GET',
        'path' => '/users/{id}/photos',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'photos'  // Galerie photos du grimpeur
    ],
    [
        'method' => 'GET',
        'path' => '/users/{id}/statistics',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'statistics'  // Statistiques avancées : grades, types, régions
    ],

    // **2. CARNETS D'ASCENSIONS (LOGBOOK)**
    // Pages développement futur : Import/export carnets, analyse progression, objectifs
    [
        'method' => 'GET',
        'path' => '/logbook',
        'controller' => \TopoclimbCH\Controllers\LogbookController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Mon carnet personnel
    ],
    [
        'method' => 'POST',
        'path' => '/logbook/import',
        'controller' => \TopoclimbCH\Controllers\LogbookController::class,
        'action' => 'import',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Import GPS, fichiers
    ],
    [
        'method' => 'GET',
        'path' => '/logbook/export',
        'controller' => \TopoclimbCH\Controllers\LogbookController::class,
        'action' => 'export',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Export PDF, Excel
    ],
    [
        'method' => 'GET',
        'path' => '/logbook/analytics',
        'controller' => \TopoclimbCH\Controllers\LogbookController::class,
        'action' => 'analytics',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Graphiques progression
    ],

    // **3. GALERIES PHOTOS ET MÉDIAS**
    // Pages développement futur : Upload batch, reconnaissance IA, géolocalisation
    [
        'method' => 'GET',
        'path' => '/photos',
        'controller' => \TopoclimbCH\Controllers\PhotoController::class,
        'action' => 'index'  // Galerie publique communautaire
    ],
    [
        'method' => 'POST',
        'path' => '/photos/upload',
        'controller' => \TopoclimbCH\Controllers\PhotoController::class,
        'action' => 'upload',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Upload avec tags auto
    ],
    [
        'method' => 'GET',
        'path' => '/photos/{id}',
        'controller' => \TopoclimbCH\Controllers\PhotoController::class,
        'action' => 'show'  // Vue détaillée avec EXIF, commentaires
    ],
    [
        'method' => 'GET',
        'path' => '/photos/routes/{routeId}',
        'controller' => \TopoclimbCH\Controllers\PhotoController::class,
        'action' => 'byRoute'  // Photos spécifiques à une voie
    ],

    // **4. SYSTÈME D'ÉVALUATION ET REVIEWS**  
    // Pages développement futur : Notes détaillées, critères multiples, modération
    [
        'method' => 'GET',
        'path' => '/routes/{id}/reviews',
        'controller' => \TopoclimbCH\Controllers\ReviewController::class,
        'action' => 'index'  // Avis et notes communautaires
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/reviews',
        'controller' => \TopoclimbCH\Controllers\ReviewController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Ajouter note/avis
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/reviews',
        'controller' => \TopoclimbCH\Controllers\ReviewController::class,
        'action' => 'sectorReviews'  // Avis sur secteur complet
    ],

    // **5. PLANIFICATION ET ÉVÉNEMENTS**
    // Pages développement futur : Calendrier intégré, météo prédictive, groupes
    [
        'method' => 'GET',
        'path' => '/events',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'index'  // Événements escalade communautaires
    ],
    [
        'method' => 'GET',
        'path' => '/events/create',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'create',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Organiser sortie
    ],
    [
        'method' => 'POST',
        'path' => '/events/create',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'store',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/events/{id}/join',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'join',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Participer événement
    ],

    // **6. MÉTÉO ET CONDITIONS**
    // Pages développement futur : Prévisions spécialisées, conditions rocher, webcams
    [
        'method' => 'GET',
        'path' => '/weather',
        'controller' => \TopoclimbCH\Controllers\WeatherController::class,
        'action' => 'index'  // Météo générale Suisse escalade
    ],
    [
        'method' => 'GET',
        'path' => '/weather/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\WeatherController::class,
        'action' => 'region'  // Météo spécifique région
    ],
    [
        'method' => 'GET',
        'path' => '/api/weather/current',
        'controller' => \TopoclimbCH\Controllers\WeatherController::class,
        'action' => 'apiCurrent'  // API météo pour secteurs
    ],
    [
        'method' => 'GET',
        'path' => '/conditions',
        'controller' => \TopoclimbCH\Controllers\ConditionsController::class,
        'action' => 'index'  // État des sites (sec, humide, équipé)
    ],
    [
        'method' => 'POST',
        'path' => '/conditions/{siteId}/report',
        'controller' => \TopoclimbCH\Controllers\ConditionsController::class,
        'action' => 'report',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Signaler conditions
    ],

    // **7. ÉQUIPEMENT ET MATÉRIEL**
    // Pages développement futur : Comparateur prix, tests matériel, recommandations IA
    [
        'method' => 'GET',
        'path' => '/gear',
        'controller' => \TopoclimbCH\Controllers\GearController::class,
        'action' => 'index'  // Catalogue équipement escalade
    ],
    [
        'method' => 'GET',
        'path' => '/gear/routes/{id}/required',
        'controller' => \TopoclimbCH\Controllers\GearController::class,
        'action' => 'routeRequirements'  // Matériel requis par voie
    ],
    [
        'method' => 'GET',
        'path' => '/gear/calculator',
        'controller' => \TopoclimbCH\Controllers\GearController::class,
        'action' => 'calculator'  // Calculateur matériel pour sortie
    ],

    // **MEDIA UPLOAD**
    [
        'method' => 'GET',
        'path' => '/media/upload',
        'controller' => \TopoclimbCH\Controllers\MediaController::class,
        'action' => 'uploadForm',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/media/{id}',
        'controller' => \TopoclimbCH\Controllers\MediaController::class,
        'action' => 'deleteApi',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // **8. FORMATIONS ET SÉCURITÉ**
    // Pages développement futur : Cours en ligne, certifications, quiz sécurité
    [
        'method' => 'GET',
        'path' => '/training',
        'controller' => \TopoclimbCH\Controllers\TrainingController::class,
        'action' => 'index'  // Formations escalade disponibles
    ],
    [
        'method' => 'GET',
        'path' => '/safety',
        'controller' => \TopoclimbCH\Controllers\SafetyController::class,
        'action' => 'index'  // Guide sécurité escalade
    ],
    [
        'method' => 'GET',
        'path' => '/safety/emergency',
        'controller' => \TopoclimbCH\Controllers\SafetyController::class,
        'action' => 'emergency'  // Procédures urgence, contacts secours
    ],

    // **9. DÉCOUVERTE ET EXPLORATION**
    // Pages développement futur : Algorithme recommandation, quiz préférences
    [
        'method' => 'GET',
        'path' => '/discover',
        'controller' => \TopoclimbCH\Controllers\DiscoverController::class,
        'action' => 'index'  // Découverte personnalisée sites/voies
    ],
    [
        'method' => 'GET',
        'path' => '/discover/random',
        'controller' => \TopoclimbCH\Controllers\DiscoverController::class,
        'action' => 'random'  // Voie/secteur aléatoire
    ],
    [
        'method' => 'GET',
        'path' => '/discover/nearby',
        'controller' => \TopoclimbCH\Controllers\DiscoverController::class,
        'action' => 'nearby',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Sites proches géoloc
    ],

    // **10. STATISTIQUES ET CLASSEMENTS**
    // Pages développement futur : Leaderboards, défis mensuels, badges
    [
        'method' => 'GET',
        'path' => '/stats',
        'controller' => \TopoclimbCH\Controllers\StatsController::class,
        'action' => 'index'  // Statistiques globales plateforme
    ],
    [
        'method' => 'GET',
        'path' => '/leaderboards',
        'controller' => \TopoclimbCH\Controllers\LeaderboardController::class,
        'action' => 'index'  // Classements grimpeurs par catégorie
    ],
    [
        'method' => 'GET',
        'path' => '/achievements',
        'controller' => \TopoclimbCH\Controllers\AchievementController::class,
        'action' => 'index'  // Système badges et réussites
    ],

    // **11. RECHERCHE AVANCÉE ET FILTRES**
    // Pages développement futur : Recherche visuelle, filtres IA, sauvegarde recherches
    [
        'method' => 'GET',
        'path' => '/search',
        'controller' => \TopoclimbCH\Controllers\SearchController::class,
        'action' => 'index'  // Recherche avancée multi-critères
    ],
    [
        'method' => 'POST',
        'path' => '/search',
        'controller' => \TopoclimbCH\Controllers\SearchController::class,
        'action' => 'results'  // Résultats recherche avec pagination
    ],
    [
        'method' => 'GET',
        'path' => '/search/saved',
        'controller' => \TopoclimbCH\Controllers\SearchController::class,
        'action' => 'saved',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Recherches sauvegardées
    ],

    // **12. API MOBILE ET INTÉGRATIONS**
    // Pages développement futur : App mobile, widgets, API publique
    [
        'method' => 'GET',
        'path' => '/api/mobile/sync',
        'controller' => \TopoclimbCH\Controllers\ApiController::class,
        'action' => 'mobileSync',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]  // Sync données offline
    ],
    [
        'method' => 'GET',
        'path' => '/api/public/routes/popular',
        'controller' => \TopoclimbCH\Controllers\ApiController::class,
        'action' => 'popularRoutes'  // API publique voies populaires
    ],
    [
        'method' => 'GET',
        'path' => '/widgets',
        'controller' => \TopoclimbCH\Controllers\WidgetController::class,
        'action' => 'index'  // Widgets intégrables sites web
    ],

    // **13. ADMINISTRATION ÉTENDUE**
    // Pages développement futur : Modération contenu, analytics détaillées, gestion communauté
    [
        'method' => 'GET',
        'path' => '/admin/users',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'users',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AdminMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/moderation',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'moderation',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AdminMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/analytics',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'analytics',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class, \TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // **14. PAGES LÉGALES ET SUPPORT**
    // Pages développement futur : Chat support, FAQ interactive, documentation API
    [
        'method' => 'GET',
        'path' => '/about',
        'controller' => \TopoclimbCH\Controllers\PageController::class,
        'action' => 'about'  // À propos plateforme
    ],
    [
        'method' => 'GET',
        'path' => '/terms',
        'controller' => \TopoclimbCH\Controllers\PageController::class,
        'action' => 'terms'  // Conditions utilisation
    ],
    [
        'method' => 'GET',
        'path' => '/privacy',
        'controller' => \TopoclimbCH\Controllers\PageController::class,
        'action' => 'privacy'  // Politique confidentialité
    ],
    [
        'method' => 'GET',
        'path' => '/contact',
        'controller' => \TopoclimbCH\Controllers\ContactController::class,
        'action' => 'index'  // Formulaire contact
    ],
    [
        'method' => 'POST',
        'path' => '/contact',
        'controller' => \TopoclimbCH\Controllers\ContactController::class,
        'action' => 'send',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/help',
        'controller' => \TopoclimbCH\Controllers\HelpController::class,
        'action' => 'index'  // Centre aide et FAQ
    ],

    // **FAVORIS - Système de favoris utilisateur**
    [
        'method' => 'GET',
        'path' => '/favorites',
        'controller' => \TopoclimbCH\Controllers\FavoriteController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Core\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/api/favorites/toggle',
        'controller' => \TopoclimbCH\Controllers\FavoriteController::class,
        'action' => 'apiToggle',
        'middlewares' => [\TopoclimbCH\Core\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/api/favorites/status',
        'controller' => \TopoclimbCH\Controllers\FavoriteController::class,
        'action' => 'apiStatus',
        'middlewares' => [\TopoclimbCH\Core\Middleware\AuthMiddleware::class]
    ],
];

/*
==============================================================================
PLAN DE DÉVELOPPEMENT FUTUR - FONCTIONNALITÉS AVANCÉES TOPOCLIMB CH
==============================================================================

**PHASE 1 - CORE FONCTIONNEL (ACTUEL)**
✅ Gestion base : regions, sites, secteurs, voies
✅ Authentification et autorisation  
✅ Recherche basique et navigation
⚠️ En cours : Formulaires création/édition complets

**PHASE 2 - SOCIAL ET COMMUNAUTÉ (3-6 mois)**
🎯 Profils utilisateurs détaillés avec statistiques
🎯 Carnets d'ascensions (logbook) avec import/export  
🎯 Système avis et notes collaboratif
🎯 Galerie photos communautaire avec géolocalisation
🎯 Événements et organisation sorties groupe

**PHASE 3 - INTELLIGENCE ET PERSONNALISATION (6-12 mois)**
🎯 Recommandations IA basées historique utilisateur
🎯 Prédictions météo spécialisées escalade
🎯 Conditions temps réel (crowdsourcing)
🎯 Calculateurs matériel et planification
🎯 Découverte personnalisée (algorithme matching)

**PHASE 4 - MOBILE ET OFFLINE (12-18 mois)**  
🎯 Application mobile native (iOS/Android)
🎯 Mode hors ligne avec synchronisation
🎯 GPS et navigation sur site
🎯 Réalité augmentée pour identification voies
🎯 Intégration wearables (montres, capteurs)

**PHASE 5 - ADVANCED FEATURES (18+ mois)**
🎯 Marketplace équipement et services
🎯 Formations en ligne certifiantes
🎯 Compétitions virtuelles et défis
🎯 Analytics comportementaux avancés
🎯 API publique pour développeurs tiers

**CONSIDÉRATIONS TECHNIQUES FUTURES:**
- Architecture microservices pour scalabilité
- Cache distribué (Redis) pour performance  
- CDN global pour médias et assets
- Machine Learning pour recommandations
- WebRTC pour chat vidéo temps réel
- Progressive Web App (PWA) capabilities
- Elasticsearch pour recherche avancée full-text
- Queue system (RabbitMQ) pour tâches asynchrones

**MONÉTISATION POSSIBLE:**
- Abonnements premium (météo avancée, stats détaillées)
- Marketplace commissions équipement
- Formations payantes certifiantes
- API commerciale pour guides/applications
- Partenariats fabricants équipement
*/
