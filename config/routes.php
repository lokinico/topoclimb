<?php

/**
 * Configuration des routes finales avec Sites et Books
 * Structure: Région → Site → Secteur → Voie + Books (guides indépendants)
 */

return [
    // ========================================
    // ROUTES PUBLIQUES
    // ========================================
    [
        'method' => 'GET',
        'path' => '/',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/debug-home',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'debugTest'
    ],

    // ========================================
    // ROUTES CARTE INTERACTIVE
    // ========================================
    [
        'method' => 'GET',
        'path' => '/map',
        'controller' => \TopoclimbCH\Controllers\MapController::class,
        'action' => 'index'
    ],
    [
        'method' => 'GET',
        'path' => '/api/map/sites',
        'controller' => \TopoclimbCH\Controllers\MapController::class,
        'action' => 'apiSites'
    ],
    [
        'method' => 'GET',
        'path' => '/api/map/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\MapController::class,
        'action' => 'apiSiteDetails'
    ],
    [
        'method' => 'GET',
        'path' => '/api/map/search',
        'controller' => \TopoclimbCH\Controllers\MapController::class,
        'action' => 'apiGeoSearch'
    ],

    // ========================================
    // ROUTES D'AUTHENTIFICATION
    // ========================================
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

    // Routes récupération mot de passe
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

    // ========================================
    // ROUTES RÉGIONS (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/regions/create',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES SITES (NOUVELLES)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'form',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sites/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'form',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'PUT',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'destroy',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES SECTEURS (MISES À JOUR)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES BOOKS/GUIDES (NOUVELLES)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/books',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/books/create',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'form',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/books',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/books/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'form',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'PUT',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'destroy',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Gestion des secteurs dans les guides
    [
        'method' => 'GET',
        'path' => '/books/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'sectorSelector',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/books/{id}/add-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'addSector',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/books/{id}/remove-sector',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'removeSector',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES VOIES (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/routes/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Logger une ascension
    [
        'method' => 'GET',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'logAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'recordAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Routes pour commentaires
    [
        'method' => 'GET',
        'path' => '/routes/{id}/comments',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'comments',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/comments',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'addComment',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Routes pour favoris
    [
        'method' => 'POST',
        'path' => '/routes/{id}/favorite',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'toggleFavorite',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/routes/{id}/favorite',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'removeFavorite',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES ÉVÉNEMENTS
    // ========================================
    [
        'method' => 'GET',
        'path' => '/events',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/events/create',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/events',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/events/{id}',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'show',
        'middlewares' => []
    ],
    [
        'method' => 'POST',
        'path' => '/events/{id}/register',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'register',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/events/{id}/unregister',
        'controller' => \TopoclimbCH\Controllers\EventController::class,
        'action' => 'unregister',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES ALERTES
    // ========================================
    [
        'method' => 'GET',
        'path' => '/alerts',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/create',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/{id}',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'show',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/alerts/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/alerts/{id}',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/alerts/{id}/confirm',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'confirm',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/api/alerts',
        'controller' => \TopoclimbCH\Controllers\AlertController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],

    // ========================================
    // ROUTES FORUM
    // ========================================
    [
        'method' => 'GET',
        'path' => '/forum',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/forum/category/{id}',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'category',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/forum/topic/{id}',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'topic',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/forum/category/{id}/create',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'createTopic',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/forum/category/{id}/create',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'storeTopic',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/forum/topic/{id}/reply',
        'controller' => \TopoclimbCH\Controllers\ForumController::class,
        'action' => 'reply',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES API (NOUVELLES ET EXISTANTES)
    // ========================================

    // API Régions
    [
        'method' => 'GET',
        'path' => '/api/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/search',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'search',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'apiSectors',
        'middlewares' => []
    ],

    // API Secteurs
    [
        'method' => 'GET',
        'path' => '/api/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/sectors/search',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'apiSearch',
        'middlewares' => []
    ],

    // API Routes (Voies)
    [
        'method' => 'GET',
        'path' => '/api/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/routes/search',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'apiSearch',
        'middlewares' => []
    ],

    // API Books (Guides)
    [
        'method' => 'GET',
        'path' => '/api/books',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/weather',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'weather',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // API Sites
    [
        'method' => 'GET',
        'path' => '/api/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiIndex',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/sites/search',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiSearch',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiShow',
        'middlewares' => []
    ],

    // API Secteurs
    [
        'method' => 'GET',
        'path' => '/api/sectors/{id}/routes',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'getRoutes',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // API Books
    [
        'method' => 'GET',
        'path' => '/api/books/search',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'apiSearch',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/books/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'apiSectors',
        'middlewares' => []
    ],

    // ========================================
    // ROUTES UTILISATEURS (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/profile',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'profile',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/profile/ascents',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'ascents',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/profile/favorites',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'favorites',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'ascents',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/favorites',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'favorites',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/settings',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'settings',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/settings/profile',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'updateProfile',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/settings/password',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'updatePassword',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/pending',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'pending',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/banned',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'banned'
    ],

    // ========================================
    // ROUTES ASCENSIONS (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/ascents/create',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/ascents',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/ascents/{id}',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'update',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/ascents/export',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'export',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES ADMINISTRATION (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/admin',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'index',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/users',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'users',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/reports',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'reports',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/users/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'userEdit',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'userEdit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AdminMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/toggle-ban',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'userToggleBan',
        'middlewares' => [
            \TopoclimbCH\Middleware\AdminMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES MÉDIAS (inchangées)
    // ========================================
    [
        'method' => 'POST',
        'path' => '/regions/{id}/media',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'uploadMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/regions/{id}/media/{mediaId}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'deleteMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES EXPORTS (inchangées)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/regions/{id}/export',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'export',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES STATIQUES (inchangées)
    // ========================================
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

    // ========================================
    // ROUTES ERREURS (inchangées)
    // ========================================
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

    // ========================================
    // API REST v1 ENDPOINTS - MODERN API
    // ========================================
    
    // ========================================
    // ROUTES MÉDIAS
    // ========================================
    [
        'method' => 'GET',
        'path' => '/media',
        'controller' => \TopoclimbCH\Controllers\MediaController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/regions/{id}/media',
        'controller' => \TopoclimbCH\Controllers\MediaController::class,
        'action' => 'uploadForm',
        'middlewares' => []
    ],
    
    // ========================================
    // ROUTES GÉOLOCALISATION
    // ========================================
    [
        'method' => 'GET',
        'path' => '/geolocation',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/geolocation/directions/{id}',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'directions',
        'middlewares' => []
    ],
    
    // API Géolocalisation
    [
        'method' => 'GET',
        'path' => '/api/geolocation/nearest-sites',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiNearestSites',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/nearest-sectors',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiNearestSectors',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/directions/{id}',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiDirections',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/geocode',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiGeocode',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/reverse-geocode',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiReverseGeocode',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/convert-swiss',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiConvertToSwiss',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/weather',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiWeatherByLocation',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geolocation/nearby-pois',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiNearbyPOIs',
        'middlewares' => []
    ],

    // API Météo et Géocodage manquantes
    [
        'method' => 'GET',
        'path' => '/api/weather/current',
        'controller' => \TopoclimbCH\Controllers\WeatherController::class,
        'action' => 'apiCurrent',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/geocoding/search',
        'controller' => \TopoclimbCH\Controllers\GeolocationController::class,
        'action' => 'apiSearch',
        'middlewares' => []
    ],

    // ========================================
    // ROUTES DE TEST (SANS AUTHENTIFICATION)
    // ========================================
    [
        'method' => 'GET',
        'path' => '/test/regions/create',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'testCreate',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/test/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'form',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/test/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'testCreate',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/test/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'testCreate',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/test/books/create',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'testCreate',
        'middlewares' => []
    ],
    
    // ========================================
    // ROUTES MONITORING ET SURVEILLANCE
    // ========================================
    [
        'method' => 'GET',
        'path' => '/admin/monitoring',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'dashboard',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/backups',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'backups',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    
    // API Monitoring
    [
        'method' => 'GET',
        'path' => '/api/monitoring/health',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiHealthCheck',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/api/system-metrics',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiSystemMetrics',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/api/usage-metrics',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiUsageMetrics',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/api/error-stats',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiErrorStats',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/record-metric',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiRecordMetric',
        'middlewares' => []
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/record-error',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiRecordError',
        'middlewares' => []
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/record-user-action',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiRecordUserAction',
        'middlewares' => []
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/cleanup-logs',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiCleanupLogs',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    
    // API Backup
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/backup/full',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiCreateFullBackup',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/backup/incremental',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiCreateIncrementalBackup',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/admin/monitoring/api/backup/restore/{backup_name}',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiRestoreBackup',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/api/backup/list',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiListBackups',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/admin/monitoring/api/backup/stats',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'apiBackupStats',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\AdminMiddleware::class
        ]
    ],
    
    // Webhooks et monitoring externe
    [
        'method' => 'POST',
        'path' => '/api/monitoring/webhook',
        'controller' => \TopoclimbCH\Controllers\MonitoringController::class,
        'action' => 'webhook',
        'middlewares' => []
    ],
    
    // ========================================
    // ROUTES ÉQUIPEMENT D'ESCALADE
    // ========================================
    [
        'method' => 'GET',
        'path' => '/equipment',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/categories',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'categories',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/categories/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'createCategory',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/categories/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'createCategory',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/types',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'types',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/types/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'createType',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/types/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'createType',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/kits',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'kits',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/kits/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'editKit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/kits/create',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'editKit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/kits/{id}',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'showKit',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/kits/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'editKit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/kits/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'editKit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/kits/{id}/duplicate',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'duplicateKit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/equipment/kits/{id}/items',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'addKitItem',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/equipment/kits/{id}/items',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'removeKitItem',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/recommendations',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'recommendations',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/search',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'search',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/equipment/kits/{id}/export',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'exportKit',
        'middlewares' => []
    ],
    
    // API Équipement
    [
        'method' => 'GET',
        'path' => '/api/equipment/types/search',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'apiSearchTypes',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/equipment/types/select',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'apiTypesForSelect',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/equipment/stats',
        'controller' => \TopoclimbCH\Controllers\EquipmentController::class,
        'action' => 'apiStats',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    
    // ========================================
    // ROUTES CHECKLISTS DE SÉCURITÉ
    // ========================================
    [
        'method' => 'GET',
        'path' => '/checklists',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'index',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/templates',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'templates',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/templates/create',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'editTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/templates/create',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'editTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/templates/{id}',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'showTemplate',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/templates/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'editTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/templates/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'editTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/templates/{id}/create',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'createFromTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/templates/{id}/create',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'createFromTemplate',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/my',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'myChecklists',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/my/{id}',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'showChecklist',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/my/{id}/complete',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'completeChecklist',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/my/{id}/reset',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'resetChecklist',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/my/{id}/duplicate',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'duplicateChecklist',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/my/{id}/items',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'addChecklistItem',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/checklists/items/{id}/toggle',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'toggleChecklistItem',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'PUT',
        'path' => '/checklists/items/{id}/notes',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'updateItemNotes',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/checklists/items/{id}',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'removeChecklistItem',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/search',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'search',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/checklists/my/{id}/export',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'exportChecklist',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    
    // API Checklists
    [
        'method' => 'GET',
        'path' => '/api/checklists/equipment-types',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'apiEquipmentTypes',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/checklists/stats',
        'controller' => \TopoclimbCH\Controllers\ChecklistController::class,
        'action' => 'apiStats',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    
    // ========================================
    // ROUTES SYNCHRONISATION HORS-LIGNE
    // ========================================
    [
        'method' => 'GET',
        'path' => '/api/sync/offline-data',
        'controller' => \TopoclimbCH\Controllers\SyncController::class,
        'action' => 'getOfflineData',
        'middlewares' => []
    ],
    [
        'method' => 'GET',
        'path' => '/api/sync/delta',
        'controller' => \TopoclimbCH\Controllers\SyncController::class,
        'action' => 'getDeltaSync',
        'middlewares' => []
    ],
    [
        'method' => 'POST',
        'path' => '/api/sync/changes',
        'controller' => \TopoclimbCH\Controllers\SyncController::class,
        'action' => 'syncLocalChanges',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/api/sync/stats',
        'controller' => \TopoclimbCH\Controllers\SyncController::class,
        'action' => 'getSyncStats',
        'middlewares' => []
    ],
];
