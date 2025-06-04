<?php
// config/routes/api.php - Routes API spécialisées

use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Routing\RouteGroup;


return function (Router $router) {
    // API v1 pour mobile et intégrations
    $router->group([
        'prefix' => '/api/v1',
        'namespace' => 'TopoclimbCH\\Controllers\\Api\\V1',
        'name' => 'api.v1',
        'middlewares' => ['api.throttle:60,1', 'api.cors']
    ], function (RouteGroup $group) {

        // Authentification API sans middleware auth
        $group->group(['prefix' => '/auth'], function (RouteGroup $authGroup) {
            $authGroup->post('/login', [
                'controller' => 'AuthApiController',
                'action' => 'login'
            ], ['name' => 'auth.login']);

            $authGroup->post('/register', [
                'controller' => 'AuthApiController',
                'action' => 'register'
            ], ['name' => 'auth.register']);

            $authGroup->post('/forgot-password', [
                'controller' => 'AuthApiController',
                'action' => 'forgotPassword'
            ], ['name' => 'auth.forgot']);
        });

        // Routes protégées par authentification API
        $group->group(['middlewares' => ['api.auth']], function (RouteGroup $authGroup) {

            $authGroup->post('/auth/logout', [
                'controller' => 'AuthApiController',
                'action' => 'logout'
            ], ['name' => 'auth.logout']);

            $authGroup->post('/auth/refresh', [
                'controller' => 'AuthApiController',
                'action' => 'refresh'
            ], ['name' => 'auth.refresh']);

            // API Resources complètes
            $authGroup->apiResource('regions', 'RegionApiController');
            $authGroup->apiResource('sites', 'SiteApiController');
            $authGroup->apiResource('sectors', 'SectorApiController');
            $authGroup->apiResource('routes', 'RouteApiController');
            $authGroup->apiResource('ascents', 'AscentApiController');

            // Endpoints spécialisés
            $authGroup->get('/user/profile', [
                'controller' => 'UserApiController',
                'action' => 'profile'
            ], ['name' => 'user.profile']);

            $authGroup->get('/user/ascents', [
                'controller' => 'UserApiController',
                'action' => 'ascents'
            ], ['name' => 'user.ascents']);

            $authGroup->get('/user/statistics', [
                'controller' => 'UserApiController',
                'action' => 'statistics'
            ], ['name' => 'user.statistics']);

            // Recherche avancée
            $authGroup->get('/search/global', [
                'controller' => 'SearchApiController',
                'action' => 'global'
            ], ['name' => 'search.global']);

            $authGroup->get('/search/routes', [
                'controller' => 'SearchApiController',
                'action' => 'routes'
            ], ['name' => 'search.routes']);

            $authGroup->get('/search/suggestions', [
                'controller' => 'SearchApiController',
                'action' => 'suggestions'
            ], ['name' => 'search.suggestions']);

            // Géolocalisation
            $authGroup->get('/geo/nearby', [
                'controller' => 'GeoApiController',
                'action' => 'nearby'
            ], ['name' => 'geo.nearby']);

            $authGroup->get('/geo/sectors/{lat}/{lng}/{radius?}', [
                'controller' => 'GeoApiController',
                'action' => 'sectorsNearby'
            ], [
                'name' => 'geo.sectors.nearby',
                'constraints' => [
                    'lat' => '-?\d+\.?\d*',
                    'lng' => '-?\d+\.?\d*',
                    'radius' => '\d+'
                ],
                'defaults' => ['radius' => '10']
            ]);

            // Conditions en temps réel
            $authGroup->get('/conditions/current', [
                'controller' => 'ConditionApiController',
                'action' => 'current'
            ], ['name' => 'conditions.current']);

            $authGroup->post('/conditions/report', [
                'controller' => 'ConditionApiController',
                'action' => 'report'
            ], ['name' => 'conditions.report']);

            // Médias et uploads
            $authGroup->post('/media/upload', [
                'controller' => 'MediaApiController',
                'action' => 'upload'
            ], ['name' => 'media.upload']);

            $authGroup->get('/media/{id}/download', [
                'controller' => 'MediaApiController',
                'action' => 'download'
            ], [
                'name' => 'media.download',
                'constraints' => ['id' => '\d+']
            ]);

            // Synchronisation offline
            $authGroup->get('/sync/data/{timestamp?}', [
                'controller' => 'SyncApiController',
                'action' => 'data'
            ], [
                'name' => 'sync.data',
                'constraints' => ['timestamp' => '\d+']
            ]);

            $authGroup->post('/sync/ascents', [
                'controller' => 'SyncApiController',
                'action' => 'syncAscents'
            ], ['name' => 'sync.ascents']);
        });

        // Endpoints publics (sans authentification)
        $group->get('/regions/public', [
            'controller' => 'RegionApiController',
            'action' => 'publicIndex'
        ], ['name' => 'regions.public']);

        $group->get('/sectors/public', [
            'controller' => 'SectorApiController',
            'action' => 'publicIndex'
        ], ['name' => 'sectors.public']);

        $group->get('/statistics/public', [
            'controller' => 'StatisticsApiController',
            'action' => 'public'
        ], ['name' => 'statistics.public']);

        // Health check
        $group->get('/health', [
            'controller' => 'HealthApiController',
            'action' => 'check'
        ], ['name' => 'health']);

        $group->get('/version', [
            'controller' => 'HealthApiController',
            'action' => 'version'
        ], ['name' => 'version']);
    });

    // API v2 avec nouvelles fonctionnalités
    $router->group([
        'prefix' => '/api/v2',
        'namespace' => 'TopoclimbCH\\Controllers\\Api\\V2',
        'name' => 'api.v2',
        'middlewares' => ['api.throttle:100,1', 'api.cors', 'api.auth'],
        'condition' => function () {
            return $_ENV['APP_ENV'] === 'production' || $_ENV['API_V2_ENABLED'] === 'true';
        }
    ], function (RouteGroup $group) {

        // Nouvelles fonctionnalités v2
        $group->get('/routes/{id}/recommendations', [
            'controller' => 'RouteApiController',
            'action' => 'recommendations'
        ], [
            'name' => 'routes.recommendations',
            'constraints' => ['id' => '\d+']
        ]);

        $group->get('/routes/{id}/similar', [
            'controller' => 'RouteApiController',
            'action' => 'similar'
        ], [
            'name' => 'routes.similar',
            'constraints' => ['id' => '\d+']
        ]);

        // Analytics et ML
        $group->get('/analytics/user-trends', [
            'controller' => 'AnalyticsApiController',
            'action' => 'userTrends'
        ], ['name' => 'analytics.user-trends']);

        $group->get('/ml/predict-grade', [
            'controller' => 'MLApiController',
            'action' => 'predictGrade'
        ], ['name' => 'ml.predict-grade']);

        // GraphQL endpoint
        $group->match(['GET', 'POST'], '/graphql', [
            'controller' => 'GraphQLApiController',
            'action' => 'handle'
        ], ['name' => 'graphql']);
    });
};
