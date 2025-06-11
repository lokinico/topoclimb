<?php
// src/Core/ContainerBuilder.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use TopoclimbCH\Controllers\HomeController;
use TopoclimbCH\Controllers\ErrorController;
use TopoclimbCH\Controllers\SectorController;
use TopoclimbCH\Controllers\RouteController;
use TopoclimbCH\Controllers\AuthController;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Router;

class ContainerBuilder
{
    /**
     * Build and configure the dependency injection container.
     */
    public function build(): SymfonyContainerBuilder
    {
        $container = new SymfonyContainerBuilder();

        // Configuration variables
        $container->setParameter('db_host', $_ENV['DB_HOST'] ?? 'localhost');
        $container->setParameter('db_name', $_ENV['DB_DATABASE'] ?? 'sh139940_');
        $container->setParameter('db_user', $_ENV['DB_USERNAME'] ?? 'root');
        $container->setParameter('db_password', $_ENV['DB_PASSWORD'] ?? '');
        $container->setParameter('environment', $_ENV['APP_ENV'] ?? 'production');
        $container->setParameter('views_path', BASE_PATH . '/resources/views');
        $container->setParameter('cache_path', BASE_PATH . '/cache/views');
        $container->setParameter('container.dumper.inline_factories', true);
        $container->setParameter('container.autowiring.strict_mode', false);

        // Services de base
        $this->registerCoreServices($container);

        // Services métier
        $this->registerBusinessServices($container);

        // Contrôleurs
        $this->registerControllers($container);

        // Middlewares
        $this->registerMiddlewares($container);

        return $container;
    }

    /**
     * Register core services in the container.
     */
    private function registerCoreServices(SymfonyContainerBuilder $container): void
    {
        // Logger
        $container->register(LoggerInterface::class, Logger::class)
            ->setPublic(true)
            ->addArgument('app');

        // Database
        $container->register(Database::class, Database::class)
            ->setPublic(true)
            ->setFactory([Database::class, 'getInstance']);

        // Session
        $container->register(Session::class, Session::class)
            ->setPublic(true);

        // CORRECTION: CsrfManager AVANT Auth car Auth l'utilise
        $container->register(CsrfManager::class, CsrfManager::class)
            ->setPublic(true)
            ->addArgument(new Reference(Session::class))
            ->addArgument([]); // Array vide pour exemptedRoutes

        // Auth
        $container->register(Auth::class, Auth::class)
            ->setPublic(true)
            ->setFactory([Auth::class, 'getInstance'])
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class))
            ->setLazy(true);

        // View
        $container->register(View::class, View::class)
            ->setPublic(true)
            ->addArgument('%views_path%')
            ->addArgument('%cache_path%')
            ->addArgument(new Reference(CsrfManager::class));

        // Router
        $container->register(Router::class, Router::class)
            ->setPublic(true)
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference('service_container'));

        $container->setAlias('router', Router::class)->setPublic(true);
    }

    /**
     * Register business services in the container.
     */
    private function registerBusinessServices(SymfonyContainerBuilder $container): void
    {
        $services = [
            'TopoclimbCH\\Services\\AuthService' => [
                Auth::class,
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Services\\SectorService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\RouteService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\MediaService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\RegionService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\CountryService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\WeatherService' => [
                Database::class  // Ajout du WeatherService
            ],
            'TopoclimbCH\\Services\\ValidationService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\UserService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\SiteService' => [
                Database::class
            ],
            'TopoclimbCH\\Services\\AscentService' => [
                Database::class
            ]
        ];

        foreach ($services as $id => $dependencies) {
            if (!class_exists($id)) {
                error_log("Warning: Service class $id does not exist");
                continue;
            }

            $definition = $container->register($id, $id);
            $definition->setPublic(true);

            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }

    /**
     * Register controllers in the container.
     */
    private function registerControllers(SymfonyContainerBuilder $container): void
    {
        $controllers = [
            'TopoclimbCH\\Controllers\\HomeController' => [
                View::class,                              // Position 1: pour BaseController
                Database::class,                          // Position 2
                'TopoclimbCH\\Services\\RegionService',   // Position 3
                'TopoclimbCH\\Services\\SiteService',     // Position 4
                'TopoclimbCH\\Services\\SectorService',   // Position 5
                'TopoclimbCH\\Services\\RouteService',    // Position 6
                'TopoclimbCH\\Services\\UserService',     // Position 7
                'TopoclimbCH\\Services\\WeatherService'   // Position 8
            ],
            'TopoclimbCH\\Controllers\\AuthController' => [
                View::class,
                Session::class,
                Database::class,
                CsrfManager::class
            ],
            'TopoclimbCH\\Controllers\\SectorController' => [
                View::class,
                Session::class,
                'TopoclimbCH\\Services\\SectorService',
                'TopoclimbCH\\Services\\MediaService',
                Database::class,
                CsrfManager::class
            ],
            'TopoclimbCH\\Controllers\\RouteController' => [
                View::class,
                Session::class,
                CsrfManager::class,
                'TopoclimbCH\\Services\\RouteService',
                'TopoclimbCH\\Services\\MediaService',
                'TopoclimbCH\\Services\\SectorService',
                'TopoclimbCH\\Services\\AuthService'
            ],
            'TopoclimbCH\\Controllers\\MediaController' => [
                View::class,
                Session::class,
                CsrfManager::class,
                'TopoclimbCH\\Services\\MediaService',
                Database::class
            ],
            // CORRECTION: RegionController avec le bon ordre des dépendances
            'TopoclimbCH\\Controllers\\RegionController' => [
                View::class,                                    // Position 1: View $view
                Session::class,                                 // Position 2: Session $session  
                CsrfManager::class,                            // Position 3: CsrfManager $csrfManager
                'TopoclimbCH\\Services\\RegionService',        // Position 4: RegionService $regionService
                'TopoclimbCH\\Services\\MediaService',         // Position 5: MediaService $mediaService ← CORRIGÉ
                'TopoclimbCH\\Services\\WeatherService',       // Position 6: WeatherService $weatherService ← AJOUTÉ
                Database::class,                               // Position 7: Database $db
                Auth::class,                                   // Position 8: ?Auth $auth
                'TopoclimbCH\\Services\\AuthService'           // Position 9: ?AuthService $authService
            ],
            'TopoclimbCH\\Controllers\\SiteController' => [
                View::class,
                Session::class,
                CsrfManager::class,
                'TopoclimbCH\\Services\\RegionService',
                'TopoclimbCH\\Services\\SectorService'
            ],
            'TopoclimbCH\\Controllers\\ErrorController' => [
                View::class,
                Session::class,
                CsrfManager::class
            ],
            'TopoclimbCH\\Controllers\\UserController' => [
                View::class,
                Session::class,
                CsrfManager::class,
                'TopoclimbCH\\Services\\UserService',
                'TopoclimbCH\\Services\\AscentService',
                'TopoclimbCH\\Services\\AuthService',
                Database::class
            ],
            // CORRECTION: AdminController avec le bon ordre correspondant au constructeur
            'TopoclimbCH\\Controllers\\AdminController' => [
                View::class,           // Position 1: View $view
                Session::class,        // Position 2: Session $session  
                CsrfManager::class,    // Position 3: CsrfManager $csrfManager
                Database::class,       // Position 4: Database $db
                Auth::class            // Position 5: Auth $auth
            ]
        ];

        foreach ($controllers as $id => $dependencies) {
            if (!class_exists($id)) {
                error_log("Warning: Controller class $id does not exist");
                continue;
            }

            $definition = $container->register($id, $id);
            $definition->setPublic(true);

            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }

    /**
     * Register middlewares in the container.
     */
    private function registerMiddlewares(SymfonyContainerBuilder $container): void
    {
        $middlewares = [
            'TopoclimbCH\\Middleware\\AuthMiddleware' => [
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Middleware\\AdminMiddleware' => [
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Middleware\\ModeratorMiddleware' => [
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Middleware\\PermissionMiddleware' => [
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Middleware\\CsrfMiddleware' => [
                CsrfManager::class,
                Session::class
            ]
        ];

        foreach ($middlewares as $id => $dependencies) {
            if (!class_exists($id)) {
                error_log("Warning: Middleware class $id does not exist");
                continue;
            }

            $definition = $container->register($id, $id);
            $definition->setPublic(true);

            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }
}
