<?php
// config/routes/main.php - Configuration principale des routes

use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Routing\RouteGroup;

return function (Router $router) {
    // Routes publiques de base
    $router->get('/', [
        'controller' => 'HomeController',
        'action' => 'index'
    ], ['name' => 'home']);

    // Redirections pour compatibilité ascendante
    $router->permanentRedirect('/secteur/{id}', '/sectors/{id}');
    $router->permanentRedirect('/voie/{id}', '/routes/{id}');

    // Groupe d'authentification
    $router->group([
        'prefix' => '/auth',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'auth'
    ], function (RouteGroup $group) {
        // Formulaires d'authentification
        $group->get('/login', [
            'controller' => 'AuthController',
            'action' => 'loginForm'
        ], ['name' => 'login']);

        $group->post('/login', [
            'controller' => 'AuthController',
            'action' => 'login'
        ], [
            'name' => 'login.submit',
            'middlewares' => ['csrf']
        ]);

        $group->get('/register', [
            'controller' => 'AuthController',
            'action' => 'registerForm'
        ], ['name' => 'register']);

        $group->post('/register', [
            'controller' => 'AuthController',
            'action' => 'register'
        ], [
            'name' => 'register.submit',
            'middlewares' => ['csrf']
        ]);

        $group->get('/logout', [
            'controller' => 'AuthController',
            'action' => 'logout'
        ], [
            'name' => 'logout',
            'middlewares' => ['auth']
        ]);

        // Récupération de mot de passe
        $group->get('/forgot-password', [
            'controller' => 'AuthController',
            'action' => 'forgotPasswordForm'
        ], ['name' => 'password.request']);

        $group->post('/forgot-password', [
            'controller' => 'AuthController',
            'action' => 'forgotPassword'
        ], [
            'name' => 'password.email',
            'middlewares' => ['csrf']
        ]);

        $group->get('/reset-password', [
            'controller' => 'AuthController',
            'action' => 'resetPasswordForm'
        ], ['name' => 'password.reset']);

        $group->post('/reset-password', [
            'controller' => 'AuthController',
            'action' => 'resetPassword'
        ], [
            'name' => 'password.update',
            'middlewares' => ['csrf']
        ]);
    });

    // Groupe de gestion de l'escalade
    $router->group([
        'prefix' => '/climbing',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'climbing'
    ], function (RouteGroup $group) {
        // Routes des régions
        $group->resource('regions', 'RegionController', [
            'names' => [
                'index' => 'regions.index',
                'show' => 'regions.show',
                'create' => 'regions.create',
                'store' => 'regions.store',
                'edit' => 'regions.edit',
                'update' => 'regions.update',
                'destroy' => 'regions.destroy'
            ]
        ]);

        // Routes des sites
        $group->resource('sites', 'SiteController');

        // Routes des secteurs avec fonctionnalités étendues
        $group->group(['prefix' => '/sectors'], function (RouteGroup $sectorGroup) {
            $sectorGroup->resource('', 'SectorController', [
                'names' => [
                    'index' => 'sectors.index',
                    'show' => 'sectors.show',
                    'create' => 'sectors.create',
                    'store' => 'sectors.store',
                    'edit' => 'sectors.edit',
                    'update' => 'sectors.update',
                    'destroy' => 'sectors.destroy'
                ]
            ]);

            // Routes spécifiques aux secteurs
            $sectorGroup->get('/{id}/routes', [
                'controller' => 'SectorController',
                'action' => 'routes'
            ], [
                'name' => 'sectors.routes',
                'constraints' => ['id' => '\d+']
            ]);

            $sectorGroup->get('/{id}/topo', [
                'controller' => 'SectorController',
                'action' => 'topo'
            ], [
                'name' => 'sectors.topo',
                'constraints' => ['id' => '\d+']
            ]);

            $sectorGroup->get('/{id}/conditions', [
                'controller' => 'SectorController',
                'action' => 'conditions'
            ], [
                'name' => 'sectors.conditions',
                'constraints' => ['id' => '\d+']
            ]);

            // Routes d'export pour secteurs
            $sectorGroup->exportRoutes('SectorController', 'sectors');
        });

        // Routes des voies avec fonctionnalités complètes
        $group->group(['prefix' => '/routes'], function (RouteGroup $routeGroup) {
            $routeGroup->resource('', 'RouteController', [
                'names' => [
                    'index' => 'routes.index',
                    'show' => 'routes.show',
                    'create' => 'routes.create',
                    'store' => 'routes.store',
                    'edit' => 'routes.edit',
                    'update' => 'routes.update',
                    'destroy' => 'routes.destroy'
                ]
            ]);

            // Routes pour les ascensions
            $routeGroup->ascentRoutes('RouteController');

            // Routes pour les commentaires
            $routeGroup->commentRoutes('RouteController', 'routes');

            // Routes d'export
            $routeGroup->exportRoutes('RouteController', 'routes');

            // Routes de recherche
            $routeGroup->searchRoutes('RouteController', 'routes');

            // Routes spécifiques aux voies
            $routeGroup->get('/{id}/media', [
                'controller' => 'RouteController',
                'action' => 'media'
            ], [
                'name' => 'routes.media',
                'constraints' => ['id' => '\d+']
            ]);

            $routeGroup->get('/{id}/statistics', [
                'controller' => 'RouteController',
                'action' => 'statistics'
            ], [
                'name' => 'routes.statistics',
                'constraints' => ['id' => '\d+']
            ]);
        });
    });

    // Groupe d'administration
    $router->group([
        'prefix' => '/admin',
        'namespace' => 'TopoclimbCH\\Controllers\\Admin',
        'name' => 'admin',
        'middlewares' => ['auth', 'admin'],
        'condition' => 'admin'
    ], function (RouteGroup $group) {
        $group->get('/', [
            'controller' => 'DashboardController',
            'action' => 'index'
        ], ['name' => 'dashboard']);

        // Gestion des utilisateurs
        $group->resource('users', 'UserController');

        // Gestion des médias
        $group->resource('media', 'MediaController');

        // Statistiques et rapports
        $group->get('/statistics', [
            'controller' => 'StatisticsController',
            'action' => 'index'
        ], ['name' => 'statistics']);

        $group->get('/reports', [
            'controller' => 'ReportController',
            'action' => 'index'
        ], ['name' => 'reports']);

        // Maintenance
        $group->get('/maintenance', [
            'controller' => 'MaintenanceController',
            'action' => 'index'
        ], ['name' => 'maintenance']);

        $group->post('/cache/clear', [
            'controller' => 'MaintenanceController',
            'action' => 'clearCache'
        ], [
            'name' => 'cache.clear',
            'middlewares' => ['csrf']
        ]);
    });

    // Groupe API REST
    $router->group([
        'prefix' => '/api/v1',
        'namespace' => 'TopoclimbCH\\Controllers\\Api\\V1',
        'name' => 'api.v1',
        'middlewares' => ['api.throttle', 'api.auth']
    ], function (RouteGroup $group) {
        // API Resources pour l'escalade
        $group->apiResource('regions', 'RegionApiController');
        $group->apiResource('sites', 'SiteApiController');
        $group->apiResource('sectors', 'SectorApiController');
        $group->apiResource('routes', 'RouteApiController');
        $group->apiResource('ascents', 'AscentApiController');

        // Endpoints spéciaux
        $group->get('/search', [
            'controller' => 'SearchApiController',
            'action' => 'index'
        ], ['name' => 'search']);

        $group->get('/statistics', [
            'controller' => 'StatisticsApiController',
            'action' => 'index'
        ], ['name' => 'statistics']);

        // Authentification API
        $group->post('/auth/login', [
            'controller' => 'AuthApiController',
            'action' => 'login'
        ], ['name' => 'auth.login']);

        $group->post('/auth/logout', [
            'controller' => 'AuthApiController',
            'action' => 'logout'
        ], ['name' => 'auth.logout']);

        $group->post('/auth/refresh', [
            'controller' => 'AuthApiController',
            'action' => 'refresh'
        ], ['name' => 'auth.refresh']);
    });

    // Groupe API v2 (exemple de versioning)
    $router->group([
        'prefix' => '/api/v2',
        'namespace' => 'TopoclimbCH\\Controllers\\Api\\V2',
        'name' => 'api.v2',
        'middlewares' => ['api.throttle', 'api.auth'],
        'condition' => 'production' // Uniquement en production
    ], function (RouteGroup $group) {
        // API v2 avec nouvelles fonctionnalités
        $group->apiResource('routes', 'RouteApiController');

        // Nouveaux endpoints v2
        $group->get('/routes/{id}/recommendations', [
            'controller' => 'RouteApiController',
            'action' => 'recommendations'
        ], ['name' => 'routes.recommendations']);
    });

    // Groupe des profils utilisateur
    $router->group([
        'prefix' => '/users',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'users',
        'middlewares' => ['auth']
    ], function (RouteGroup $group) {
        $group->get('/profile', [
            'controller' => 'UserController',
            'action' => 'profile'
        ], ['name' => 'profile']);

        $group->get('/profile/edit', [
            'controller' => 'UserController',
            'action' => 'editProfile'
        ], ['name' => 'profile.edit']);

        $group->post('/profile/update', [
            'controller' => 'UserController',
            'action' => 'updateProfile'
        ], [
            'name' => 'profile.update',
            'middlewares' => ['csrf']
        ]);

        $group->get('/ascents', [
            'controller' => 'UserController',
            'action' => 'ascents'
        ], ['name' => 'ascents']);

        $group->get('/favorites', [
            'controller' => 'UserController',
            'action' => 'favorites'
        ], ['name' => 'favorites']);

        $group->get('/statistics', [
            'controller' => 'UserController',
            'action' => 'statistics'
        ], ['name' => 'statistics']);
    });

    // Groupe des événements
    $router->group([
        'prefix' => '/events',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'events'
    ], function (RouteGroup $group) {
        $group->resource('', 'EventController', [
            'names' => [
                'index' => 'events.index',
                'show' => 'events.show',
                'create' => 'events.create',
                'store' => 'events.store',
                'edit' => 'events.edit',
                'update' => 'events.update',
                'destroy' => 'events.destroy'
            ]
        ]);

        // Participation aux événements
        $group->post('/{id}/join', [
            'controller' => 'EventController',
            'action' => 'join'
        ], [
            'name' => 'events.join',
            'middlewares' => ['auth', 'csrf']
        ]);

        $group->post('/{id}/leave', [
            'controller' => 'EventController',
            'action' => 'leave'
        ], [
            'name' => 'events.leave',
            'middlewares' => ['auth', 'csrf']
        ]);

        // Calendrier
        $group->get('/calendar', [
            'controller' => 'EventController',
            'action' => 'calendar'
        ], ['name' => 'events.calendar']);

        $group->get('/calendar/feed', [
            'controller' => 'EventController',
            'action' => 'calendarFeed'
        ], ['name' => 'events.calendar.feed']);
    });

    // Groupe des médias
    $router->group([
        'prefix' => '/media',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'media'
    ], function (RouteGroup $group) {
        $group->get('/{type}/{filename}', [
            'controller' => 'MediaController',
            'action' => 'serve'
        ], [
            'name' => 'serve',
            'constraints' => [
                'type' => '(images|documents|topos)',
                'filename' => '[a-zA-Z0-9._-]+'
            ]
        ]);

        $group->post('/upload', [
            'controller' => 'MediaController',
            'action' => 'upload'
        ], [
            'name' => 'upload',
            'middlewares' => ['auth', 'csrf']
        ]);

        $group->delete('/{id}', [
            'controller' => 'MediaController',
            'action' => 'delete'
        ], [
            'name' => 'delete',
            'middlewares' => ['auth', 'csrf']
        ]);
    });

    // Webhooks (sans authentification)
    $router->group([
        'prefix' => '/webhooks',
        'namespace' => 'TopoclimbCH\\Controllers',
        'name' => 'webhooks'
    ], function (RouteGroup $group) {
        $group->webhookRoutes('WebhookController');
    });

    // Routes de développement (uniquement en mode development)
    $router->group([
        'prefix' => '/dev',
        'namespace' => 'TopoclimbCH\\Controllers\\Dev',
        'name' => 'dev',
        'condition' => 'development'
    ], function (RouteGroup $group) {
        $group->get('/routes', [
            'controller' => 'DebugController',
            'action' => 'routes'
        ], ['name' => 'routes.debug']);

        $group->get('/cache', [
            'controller' => 'DebugController',
            'action' => 'cache'
        ], ['name' => 'cache.debug']);

        $group->get('/phpinfo', [
            'controller' => 'DebugController',
            'action' => 'phpinfo'
        ], ['name' => 'phpinfo']);
    });

    // Routes pour les erreurs
    $router->get('/404', [
        'controller' => 'ErrorController',
        'action' => 'notFound'
    ], ['name' => 'error.404']);

    $router->get('/500', [
        'controller' => 'ErrorController',
        'action' => 'serverError'
    ], ['name' => 'error.500']);

    // Route catch-all pour SPA (optionnel)
    // $router->get('/{path}', [
    //     'controller' => 'SpaController',
    //     'action' => 'index'
    // ], [
    //     'name' => 'spa.catchall',
    //     'constraints' => ['path' => '.*']
    // ]);
};
