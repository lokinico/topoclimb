<?php
// config/routes/admin.php - Routes d'administration

use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Routing\RouteGroup;


return function (Router $router) {
    $router->group([
        'prefix' => '/admin',
        'namespace' => 'TopoclimbCH\\Controllers\\Admin',
        'name' => 'admin',
        'middlewares' => ['auth', 'admin'],
        'domain' => 'admin.topoclimb.ch' // Sous-domaine optionnel
    ], function (RouteGroup $group) {

        // Dashboard principal
        $group->get('/', [
            'controller' => 'DashboardController',
            'action' => 'index'
        ], ['name' => 'dashboard']);

        // Gestion des utilisateurs
        $group->resource('users', 'UserController');

        $group->get('/users/{id}/impersonate', [
            'controller' => 'UserController',
            'action' => 'impersonate'
        ], [
            'name' => 'users.impersonate',
            'middlewares' => ['super.admin']
        ]);

        $group->get('/users/export/csv', [
            'controller' => 'UserController',
            'action' => 'exportCsv'
        ], ['name' => 'users.export.csv']);

        // Gestion du contenu d'escalade
        $group->group(['prefix' => '/climbing'], function (RouteGroup $climbGroup) {
            $climbGroup->resource('regions', 'ClimbingRegionController');
            $climbGroup->resource('sectors', 'ClimbingSectorController');
            $climbGroup->resource('routes', 'ClimbingRouteController');

            // Import en masse
            $climbGroup->get('/import', [
                'controller' => 'ImportController',
                'action' => 'form'
            ], ['name' => 'climbing.import']);

            $climbGroup->post('/import/csv', [
                'controller' => 'ImportController',
                'action' => 'csv'
            ], [
                'name' => 'climbing.import.csv',
                'middlewares' => ['csrf']
            ]);

            // Nettoyage des données
            $climbGroup->get('/cleanup', [
                'controller' => 'CleanupController',
                'action' => 'index'
            ], ['name' => 'climbing.cleanup']);

            $climbGroup->post('/cleanup/duplicates', [
                'controller' => 'CleanupController',
                'action' => 'removeDuplicates'
            ], [
                'name' => 'climbing.cleanup.duplicates',
                'middlewares' => ['csrf']
            ]);
        });

        // Système de logs et monitoring
        $group->group(['prefix' => '/system'], function (RouteGroup $sysGroup) {
            $sysGroup->get('/logs', [
                'controller' => 'LogController',
                'action' => 'index'
            ], ['name' => 'system.logs']);

            $sysGroup->get('/performance', [
                'controller' => 'PerformanceController',
                'action' => 'index'
            ], ['name' => 'system.performance']);

            $sysGroup->get('/health', [
                'controller' => 'HealthController',
                'action' => 'check'
            ], ['name' => 'system.health']);

            $sysGroup->post('/maintenance/mode', [
                'controller' => 'MaintenanceController',
                'action' => 'toggleMode'
            ], [
                'name' => 'system.maintenance.toggle',
                'middlewares' => ['csrf']
            ]);

            $sysGroup->post('/cache/flush', [
                'controller' => 'CacheController',
                'action' => 'flush'
            ], [
                'name' => 'system.cache.flush',
                'middlewares' => ['csrf']
            ]);
        });

        // Rapports et statistiques
        $group->group(['prefix' => '/reports'], function (RouteGroup $repGroup) {
            $repGroup->get('/', [
                'controller' => 'ReportController',
                'action' => 'index'
            ], ['name' => 'reports.index']);

            $repGroup->get('/users', [
                'controller' => 'ReportController',
                'action' => 'users'
            ], ['name' => 'reports.users']);

            $repGroup->get('/climbing', [
                'controller' => 'ReportController',
                'action' => 'climbing'
            ], ['name' => 'reports.climbing']);

            $repGroup->get('/activity', [
                'controller' => 'ReportController',
                'action' => 'activity'
            ], ['name' => 'reports.activity']);

            // Export des rapports
            $repGroup->exportRoutes('ReportController', 'reports');
        });

        // Configuration du site
        $group->get('/settings', [
            'controller' => 'SettingsController',
            'action' => 'index'
        ], ['name' => 'settings']);

        $group->post('/settings/update', [
            'controller' => 'SettingsController',
            'action' => 'update'
        ], [
            'name' => 'settings.update',
            'middlewares' => ['csrf']
        ]);
    });
};
