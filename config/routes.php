<?php

/**
 * Configuration des routes de l'application avec sécurisation par rôles
 */
$adminMiddlewares = ['auth', 'admin'];

return [
    // Routes publiques (accessibles à tous)
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

    // ========================================
    // ROUTES UTILISATEURS (Authentification requise)
    // ========================================

    // Profil utilisateur - Accès : 0,1,2,3,4 (tous connectés)
    [
        'method' => 'GET',
        'path' => '/profile',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'profile',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Ascensions - Accès : 0,1,2,3 (pas les nouveaux membres)
    [
        'method' => 'GET',
        'path' => '/ascents',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'ascents',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-ascents']
        ]
    ],

    // Favoris - Accès : 0,1,2,3 (pas les nouveaux membres)
    [
        'method' => 'GET',
        'path' => '/favorites',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'favorites',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-favorites']
        ]
    ],

    // Paramètres - Accès : 0,1,2,3,4 (tous connectés)
    [
        'method' => 'GET',
        'path' => '/settings',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'settings',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Mise à jour profil
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

    // Mise à jour mot de passe
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

    // Page d'attente pour nouveaux membres
    [
        'method' => 'GET',
        'path' => '/pending',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'pending',
        'middlewares' => [\TopoclimbCH\Middleware\AuthMiddleware::class]
    ],

    // Page pour utilisateurs bannis
    [
        'method' => 'GET',
        'path' => '/banned',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'banned'
    ],

    // ========================================
    // ROUTES RÉGIONS (Accès selon rôle)
    // ========================================

    // Liste des régions - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Détail région - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Création région - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/regions/create',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],

    // Édition région - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/regions/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
        ]
    ],

    // Suppression région - Accès : 0,1 seulement
    [
        'method' => 'DELETE',
        'path' => '/regions/{id}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'destroy',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['delete-content']
        ]
    ],

    // ========================================
    // ROUTES SITES (Accès selon rôle)
    // ========================================

    // Liste des sites - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Détail site - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Création site - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/sites/create',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],

    // ========================================
    // ROUTES SECTEURS (Accès selon rôle)
    // ========================================

    // Liste des secteurs - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Détail secteur - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/sectors/{id}',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Création secteur - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/sectors/create',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],

    // Édition secteur - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
        ]
    ],

    // Suppression secteur - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/sectors/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['delete-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['delete-content']
        ]
    ],

    // ========================================
    // ROUTES VOIES (Accès selon rôle)
    // ========================================

    // Liste des voies - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/routes',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Détail voie - Accès : 0,1,2 + partiellement 3
    [
        'method' => 'GET',
        'path' => '/routes/{id}',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Création voie - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/create',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-content']
        ]
    ],

    // Édition voie - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/routes/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
        ]
    ],

    // Suppression voie - Accès : 0,1 seulement
    [
        'method' => 'GET',
        'path' => '/routes/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'delete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['delete-content']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['delete-content']
        ]
    ],

    // Logger une ascension - Accès : 0,1,2,3 (pas les nouveaux membres)
    [
        'method' => 'GET',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'logAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-ascent']
        ]
    ],
    [
        'method' => 'POST',
        'path' => '/routes/{id}/log-ascent',
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'storeAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-ascent']
        ]
    ],

    // ========================================
    // ROUTES ASCENSIONS (CRUD complet)
    // ========================================

    // Liste ascensions (déjà définie plus haut)

    // Création ascension
    [
        'method' => 'GET',
        'path' => '/ascents/create',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-ascent']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['create-ascent']
        ]
    ],

    // Édition ascension
    [
        'method' => 'GET',
        'path' => '/ascents/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'edit',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-ascent']
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-ascent']
        ]
    ],

    // Export ascensions
    [
        'method' => 'GET',
        'path' => '/ascents/export',
        'controller' => \TopoclimbCH\Controllers\UserAscentController::class,
        'action' => 'export',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-ascents']
        ]
    ],

    // ========================================
    // ROUTES API (AJAX)
    // ========================================

    // API Secteurs
    [
        'method' => 'GET',
        'path' => '/api/sectors/{id}/routes',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'getRoutes',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // API Régions
    [
        'method' => 'GET',
        'path' => '/api/regions',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'apiIndex',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/search',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'search',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // API Météo
    [
        'method' => 'GET',
        'path' => '/regions/{id}/weather',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'weather',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // API Ascensions
    [
        'method' => 'POST',
        'path' => '/api/ascents/{id}/toggle-favorite',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'toggleFavorite',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-ascents']
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/ascents/{id}',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'deleteAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-ascent']
        ]
    ],

    // ========================================
    // ROUTES ADMINISTRATION (Admin/Modérateur)
    // ========================================

    // Dashboard admin - Accès : 0 seulement
    [
        'method' => 'GET',
        'path' => '/admin',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'dashboard',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Gestion utilisateurs - Accès : 0,1
    [
        'method' => 'GET',
        'path' => '/admin/users',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'users',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['manage-users']
        ]
    ],

    // Validation utilisateur - Accès : 0,1
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/validate',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'validateUser',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['validate-users']
        ]
    ],

    // Bannir utilisateur - Accès : 0,1
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/ban',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'banUser',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['ban-users']
        ]
    ],

    // ========================================
    // ROUTES MÉDIAS (Upload/suppression)
    // ========================================

    // Upload média - Accès : 0,1,2,3
    [
        'method' => 'POST',
        'path' => '/regions/{id}/media',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'uploadMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // Suppression média - Accès : 0,1 + propriétaire
    [
        'method' => 'DELETE',
        'path' => '/regions/{id}/media/{mediaId}',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'deleteMedia',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['edit-content']
        ]
    ],

    // ========================================
    // ROUTES EXPORTS
    // ========================================

    // Export région
    [
        'method' => 'GET',
        'path' => '/regions/{id}/export',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'export',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class => ['view-content']
        ]
    ],

    // ========================================
    // ROUTES STATIQUES
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

    // Newsletter
    [
        'method' => 'POST',
        'path' => '/newsletter',
        'controller' => \TopoclimbCH\Controllers\NewsletterController::class,
        'action' => 'subscribe',
        'middlewares' => [\TopoclimbCH\Middleware\CsrfMiddleware::class]
    ],

    // ========================================
    // ROUTES ERREURS
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
    // ROUTES DEBUG (Développement seulement)
    // ========================================

    [
        'method' => 'GET',
        'path' => '/debug/weather-test',
        'controller' => \TopoclimbCH\Controllers\DebugController::class,
        'action' => 'weatherTest'
    ],



    // ===== ADMINISTRATION =====

    // Dashboard admin
    [
        'method' => 'GET',
        'path' => '/admin',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'index',
        'middlewares' => $adminMiddlewares
    ],

    // ===== GESTION UTILISATEURS =====

    // Liste des utilisateurs
    [
        'method' => 'GET',
        'path' => '/admin/users',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'users',
        'middlewares' => $adminMiddlewares
    ],

    // Édition d'un utilisateur (GET)
    [
        'method' => 'GET',
        'path' => '/admin/users/{id}/edit',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userEdit',
        'middlewares' => $adminMiddlewares
    ],

    // Mise à jour d'un utilisateur (POST)
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/edit',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userEdit',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Toggle ban/unban utilisateur
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/toggle-ban',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userToggleBan',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Valider un utilisateur
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/validate',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userValidate',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Reset mot de passe utilisateur
    [
        'method' => 'POST',
        'path' => '/admin/users/{id}/reset-password',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userResetPassword',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Se connecter en tant qu'utilisateur
    [
        'method' => 'GET',
        'path' => '/admin/users/{id}/login-as',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userLoginAs',
        'middlewares' => $adminMiddlewares
    ],

    // Supprimer utilisateur
    [
        'method' => 'DELETE',
        'path' => '/admin/users/{id}/delete',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userDelete',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Actions en masse sur utilisateurs
    [
        'method' => 'POST',
        'path' => '/admin/users/bulk-action',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'usersBulkAction',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Export utilisateurs
    [
        'method' => 'POST',
        'path' => '/admin/users/export',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'usersExport',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Voir toutes les ascensions d'un utilisateur
    [
        'method' => 'GET',
        'path' => '/admin/users/{id}/ascents',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'userAscents',
        'middlewares' => $adminMiddlewares
    ],

    // ===== GESTION CONTENU =====

    // Gestion du contenu (régions, secteurs, voies)
    [
        'method' => 'GET',
        'path' => '/admin/content',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'content',
        'middlewares' => $adminMiddlewares
    ],

    // Supprimer un élément de contenu
    [
        'method' => 'DELETE',
        'path' => '/admin/content/{type}/{id}/delete',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'contentDelete',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Modérer un élément de contenu
    [
        'method' => 'POST',
        'path' => '/admin/content/{type}/{id}/moderate',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'contentModerate',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // ===== GESTION MÉDIAS =====

    // Liste des médias
    [
        'method' => 'GET',
        'path' => '/admin/media',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'media',
        'middlewares' => $adminMiddlewares
    ],

    // Supprimer un média
    [
        'method' => 'DELETE',
        'path' => '/admin/media/{id}/delete',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'mediaDelete',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Actions en masse sur médias
    [
        'method' => 'POST',
        'path' => '/admin/media/bulk-action',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'mediaBulkAction',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // ===== RAPPORTS & ANALYTICS =====

    // Page des rapports
    [
        'method' => 'GET',
        'path' => '/admin/reports',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'reports',
        'middlewares' => $adminMiddlewares
    ],

    // Export de rapport
    [
        'method' => 'POST',
        'path' => '/admin/reports/export',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'reportsExport',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // ===== CONFIGURATION =====

    // Page de configuration
    [
        'method' => 'GET',
        'path' => '/admin/settings',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'settings',
        'middlewares' => $adminMiddlewares
    ],

    // Mise à jour configuration
    [
        'method' => 'POST',
        'path' => '/admin/settings',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'settings',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // ===== LOGS SYSTÈME =====

    // Page des logs
    [
        'method' => 'GET',
        'path' => '/admin/logs',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'logs',
        'middlewares' => $adminMiddlewares
    ],

    // Vider les logs
    [
        'method' => 'POST',
        'path' => '/admin/logs/clear',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'logsClear',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Download logs
    [
        'method' => 'GET',
        'path' => '/admin/logs/download',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'logsDownload',
        'middlewares' => $adminMiddlewares
    ],

    // ===== MAINTENANCE =====

    // Mode maintenance
    [
        'method' => 'POST',
        'path' => '/admin/maintenance/toggle',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'maintenanceToggle',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // Cache clear
    [
        'method' => 'POST',
        'path' => '/admin/cache/clear',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'cacheClear',
        'middlewares' => array_merge($adminMiddlewares, ['csrf'])
    ],

    // ===== API ADMIN (AJAX) =====

    // Stats en temps réel pour dashboard
    [
        'method' => 'GET',
        'path' => '/admin/api/stats',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'apiStats',
        'middlewares' => $adminMiddlewares
    ],

    // Recherche utilisateurs (autocomplete)
    [
        'method' => 'GET',
        'path' => '/admin/api/users/search',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'apiUsersSearch',
        'middlewares' => $adminMiddlewares
    ],

    // Informations système en temps réel
    [
        'method' => 'GET',
        'path' => '/admin/api/system-info',
        'controller' => 'TopoclimbCH\\Controllers\\AdminController',
        'action' => 'apiSystemInfo',
        'middlewares' => $adminMiddlewares
    ]
];
