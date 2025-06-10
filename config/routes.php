<?php

/**
 * Configuration des routes corrigée - les routes /create DOIVENT être AVANT les routes /{id}
 */

return [
    // Routes publiques (accessibles à tous)
    [
        'method' => 'GET',
        'path' => '/',
        'controller' => \TopoclimbCH\Controllers\HomeController::class,
        'action' => 'index'
    ],

    // Routes d'authentification (unchanged)
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

    // Routes pour la récupération de mot de passe (unchanged)
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
    // ROUTES RÉGIONS - ORDRE CORRIGÉ
    // ========================================

    // Liste des régions
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

    // CRÉATION RÉGION - AVANT /{id}
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
        'path' => '/regions', // CORRECTION: pas /regions/create
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Détail région - APRÈS /create
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

    // Édition région
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

    // Suppression région
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
    [
        'method' => 'GET',
        'path' => '/api/regions/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'apiSectors',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES SECTEURS - ORDRE CORRIGÉ
    // ========================================

    // Liste des secteurs
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

    // CRÉATION SECTEUR - AVANT /{id}
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
        'path' => '/sectors', // CORRECTION: pas /sectors/create
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Détail secteur - APRÈS /create
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

    // Édition secteur
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

    // Suppression secteur
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
    // ROUTES VOIES - ORDRE CORRIGÉ
    // ========================================

    // Liste des voies
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

    // CRÉATION VOIE - AVANT /{id}
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
        'path' => '/routes', // CORRECTION: pas /routes/create
        'controller' => \TopoclimbCH\Controllers\RouteController::class,
        'action' => 'store',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Détail voie - APRÈS /create
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

    // Édition voie
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

    // Suppression voie
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
        'action' => 'storeAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES SITES
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
        'action' => 'create',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
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

    // ========================================
    // SUITE DES ROUTES (ascensions, utilisateurs, admin, etc.)
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
    // ROUTES ASCENSIONS (CRUD complet)
    // ========================================

    // Création ascension
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

    // Édition ascension
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

    // Export ascensions
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'GET',
        'path' => '/api/regions/search',
        'controller' => \TopoclimbCH\Controllers\RegionController::class,
        'action' => 'search',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/ascents/{id}',
        'controller' => \TopoclimbCH\Controllers\UserController::class,
        'action' => 'deleteAscent',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
        'action' => 'index',
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // Édition d'un utilisateur (GET)
    [
        'method' => 'GET',
        'path' => '/admin/users/{id}/edit',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'userEdit',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Mise à jour d'un utilisateur (POST)
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

    // Toggle ban/unban utilisateur
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

    // Gestion du contenu (régions, secteurs, voies)
    [
        'method' => 'GET',
        'path' => '/admin/content',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'content',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Gestion des médias
    [
        'method' => 'GET',
        'path' => '/admin/media',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'media',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Supprimer un média
    [
        'method' => 'DELETE',
        'path' => '/admin/media/{id}/delete',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'mediaDelete',
        'middlewares' => [
            \TopoclimbCH\Middleware\AdminMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],

    // Page des rapports
    [
        'method' => 'GET',
        'path' => '/admin/reports',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'reports',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Page de configuration
    [
        'method' => 'GET',
        'path' => '/admin/settings',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'settings',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
    ],

    // Mise à jour configuration
    [
        'method' => 'POST',
        'path' => '/admin/settings',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'settings',
        'middlewares' => [
            \TopoclimbCH\Middleware\AdminMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class
        ]
    ],

    // Page des logs
    [
        'method' => 'GET',
        'path' => '/admin/logs',
        'controller' => \TopoclimbCH\Controllers\AdminController::class,
        'action' => 'logs',
        'middlewares' => [\TopoclimbCH\Middleware\AdminMiddleware::class]
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
            \TopoclimbCH\Middleware\PermissionMiddleware::class
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
    // ROUTES SITES (hiérarchie flexible)
    // ========================================

    // Liste des sites d'une région
    [
        'method' => 'GET',
        'path' => '/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Détail d'un site
    [
        'method' => 'GET',
        'path' => '/sites/{id}',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Formulaire création site
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

    // Formulaire édition site
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

    // Création nouveau site
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

    // Mise à jour site existant
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

    // Suppression site
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
    // API HIÉRARCHIQUE (AJAX)
    // ========================================

    // API pour obtenir données hiérarchiques
    [
        'method' => 'GET',
        'path' => '/sites/hierarchy-api',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'hierarchyApi',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Outil de sélection interactif
    [
        'method' => 'GET',
        'path' => '/sites/selector',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'selector',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES SECTEURS MISES À JOUR
    // ========================================

    // Secteurs d'un site spécifique
    [
        'method' => 'GET',
        'path' => '/sites/{id}/sectors',
        'controller' => \TopoclimbCH\Controllers\SectorController::class,
        'action' => 'bySite',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Déplacer les secteurs d'un site
    [
        'method' => 'POST',
        'path' => '/sites/{id}/move-sectors',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'moveSectors',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ],

    // ========================================
    // API ENDPOINTS POUR AJAX
    // ========================================

    // Recherche sites avec autocomplétion
    [
        'method' => 'GET',
        'path' => '/api/sites/search',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiSearch',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Vérification unicité code site
    [
        'method' => 'GET',
        'path' => '/api/sites/check-code',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'checkCode',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Liste sites d'une région (AJAX)
    [
        'method' => 'GET',
        'path' => '/api/sites',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiIndex',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // Statistiques d'un site (AJAX)
    [
        'method' => 'GET',
        'path' => '/api/sites/{id}/stats',
        'controller' => \TopoclimbCH\Controllers\SiteController::class,
        'action' => 'apiStats',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],

    // ========================================
    // ROUTES BOOKS/TOPOS (optionnelles)
    // ========================================

    // Si tu veux créer un BookController séparé plus tard:
    [
        'method' => 'GET',
        'path' => '/books',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'index',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
        ]
    ],
    // Détail d'un livre/topo
    [
        'method' => 'GET',
        'path' => '/books/{id}',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'show',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class
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
        'path' => '/books/save-selection',
        'controller' => \TopoclimbCH\Controllers\BookController::class,
        'action' => 'saveSelection',
        'middlewares' => [
            \TopoclimbCH\Middleware\AuthMiddleware::class,
            \TopoclimbCH\Middleware\CsrfMiddleware::class,
            \TopoclimbCH\Middleware\PermissionMiddleware::class
        ]
    ]
];
