<?php
// config/routes/climbing.php - Routes spécifiques à l'escalade

use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Routing\RouteGroup;

return function (Router $router) {
    // Groupe principal pour l'escalade
    $router->group([
        'prefix' => '/climbing',
        'namespace' => 'TopoclimbCH\\Controllers\\Climbing',
        'name' => 'climbing',
        'middlewares' => []
    ], function (RouteGroup $group) {

        // Routes pour les topos/guides d'escalade
        $group->resource('guides', 'GuideController', [
            'names' => [
                'index' => 'guides.index',
                'show' => 'guides.show',
                'create' => 'guides.create',
                'store' => 'guides.store',
                'edit' => 'guides.edit',
                'update' => 'guides.update',
                'destroy' => 'guides.destroy'
            ]
        ]);

        // Routes pour les conditions d'escalade
        $group->group(['prefix' => '/conditions'], function (RouteGroup $condGroup) {
            $condGroup->get('/', [
                'controller' => 'ConditionController',
                'action' => 'index'
            ], ['name' => 'conditions.index']);

            $condGroup->get('/sectors/{id}', [
                'controller' => 'ConditionController',
                'action' => 'sector'
            ], [
                'name' => 'conditions.sector',
                'constraints' => ['id' => '\d+']
            ]);

            $condGroup->post('/report', [
                'controller' => 'ConditionController',
                'action' => 'report'
            ], [
                'name' => 'conditions.report',
                'middlewares' => ['auth', 'csrf']
            ]);

            $condGroup->get('/weather/{region}', [
                'controller' => 'ConditionController',
                'action' => 'weather'
            ], [
                'name' => 'conditions.weather',
                'constraints' => ['region' => '[a-z\-]+']
            ]);
        });

        // Routes pour les alertes d'escalade
        $group->group(['prefix' => '/alerts'], function (RouteGroup $alertGroup) {
            $alertGroup->get('/', [
                'controller' => 'AlertController',
                'action' => 'index'
            ], ['name' => 'alerts.index']);

            $alertGroup->post('/create', [
                'controller' => 'AlertController',
                'action' => 'store'
            ], [
                'name' => 'alerts.store',
                'middlewares' => ['auth', 'csrf']
            ]);

            $alertGroup->post('/{id}/confirm', [
                'controller' => 'AlertController',
                'action' => 'confirm'
            ], [
                'name' => 'alerts.confirm',
                'middlewares' => ['auth', 'csrf']
            ]);

            $alertGroup->post('/{id}/resolve', [
                'controller' => 'AlertController',
                'action' => 'resolve'
            ], [
                'name' => 'alerts.resolve',
                'middlewares' => ['auth', 'csrf']
            ]);
        });

        // Routes pour l'équipement
        $group->group(['prefix' => '/equipment'], function (RouteGroup $equipGroup) {
            $equipGroup->resource('', 'EquipmentController', [
                'names' => [
                    'index' => 'equipment.index',
                    'show' => 'equipment.show',
                    'create' => 'equipment.create',
                    'store' => 'equipment.store',
                    'edit' => 'equipment.edit',
                    'update' => 'equipment.update',
                    'destroy' => 'equipment.destroy'
                ]
            ]);

            $equipGroup->get('/categories', [
                'controller' => 'EquipmentController',
                'action' => 'categories'
            ], ['name' => 'equipment.categories']);

            $equipGroup->get('/recommendations/{type}', [
                'controller' => 'EquipmentController',
                'action' => 'recommendations'
            ], [
                'name' => 'equipment.recommendations',
                'constraints' => ['type' => '(sport|trad|alpine|boulder)']
            ]);

            // Kits d'équipement personnalisés
            $equipGroup->resource('kits', 'EquipmentKitController');

            $equipGroup->post('/kits/{id}/copy', [
                'controller' => 'EquipmentKitController',
                'action' => 'copy'
            ], [
                'name' => 'equipment.kits.copy',
                'middlewares' => ['auth', 'csrf']
            ]);
        });

        // Routes pour les listes de vérification (checklists)
        $group->group(['prefix' => '/checklists'], function (RouteGroup $checkGroup) {
            $checkGroup->resource('', 'ChecklistController', [
                'names' => [
                    'index' => 'checklists.index',
                    'show' => 'checklists.show',
                    'create' => 'checklists.create',
                    'store' => 'checklists.store',
                    'edit' => 'checklists.edit',
                    'update' => 'checklists.update',
                    'destroy' => 'checklists.destroy'
                ]
            ]);

            $checkGroup->get('/templates', [
                'controller' => 'ChecklistController',
                'action' => 'templates'
            ], ['name' => 'checklists.templates']);

            $checkGroup->post('/{id}/check-item/{itemId}', [
                'controller' => 'ChecklistController',
                'action' => 'checkItem'
            ], [
                'name' => 'checklists.check-item',
                'middlewares' => ['auth', 'csrf']
            ]);
        });

        // Routes pour les difficultés et conversions
        $group->group(['prefix' => '/difficulty'], function (RouteGroup $diffGroup) {
            $diffGroup->get('/systems', [
                'controller' => 'DifficultyController',
                'action' => 'systems'
            ], ['name' => 'difficulty.systems']);

            $diffGroup->get('/convert', [
                'controller' => 'DifficultyController',
                'action' => 'convert'
            ], ['name' => 'difficulty.convert']);

            $diffGroup->post('/conversion/add', [
                'controller' => 'DifficultyController',
                'action' => 'addConversion'
            ], [
                'name' => 'difficulty.conversion.add',
                'middlewares' => ['auth', 'admin', 'csrf']
            ]);
        });
    });
};
