<?php
// src/Core/ContainerBuilder.php

namespace TopoclimbCH\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Response;

class ContainerBuilder
{
    /**
     * Build and configure the dependency injection container.
     */
    public function build(): SymfonyContainerBuilder
    {
        $container = new SymfonyContainerBuilder();

        try {
            // Configuration variables
            $container->setParameter('db_host', $_ENV['DB_HOST'] ?? 'localhost');
            $container->setParameter('db_name', $_ENV['DB_DATABASE'] ?? 'sh139940_');
            $container->setParameter('db_user', $_ENV['DB_USERNAME'] ?? 'root');
            $container->setParameter('db_password', $_ENV['DB_PASSWORD'] ?? '');
            $container->setParameter('environment', $_ENV['APP_ENV'] ?? 'production');
            $container->setParameter('views_path', BASE_PATH . '/resources/views');

            // Services de base
            $this->registerCoreServices($container);
            
            // Services métier
            $this->registerBusinessServices($container);
            
            // Contrôleurs
            $this->registerControllers($container);
            
            // Middlewares
            $this->registerMiddlewares($container);

            return $container;
        } catch (\Throwable $e) {
            // Log l'erreur
            error_log("ContainerBuilder error: " . $e->getMessage());
            
            // Retourner un conteneur minimal fonctionnel
            $minimalContainer = new SymfonyContainerBuilder();
            $minimalContainer->register(View::class, View::class)
                ->addArgument(BASE_PATH . '/resources/views')
                ->addArgument(BASE_PATH . '/cache/views');
                
            return $minimalContainer;
        }
    }
    
    /**
     * Register core services
     */
    private function registerCoreServices(SymfonyContainerBuilder $container): void
    {
        // Logger
        $container->register(LoggerInterface::class, Logger::class)
            ->addArgument('topoclimbch')
            ->addMethodCall('pushHandler', [
                new Reference('logger.handler')
            ]);
            
        $container->register('logger.handler', StreamHandler::class)
            ->addArgument(BASE_PATH . '/logs/app.log')
            ->addArgument(Logger::DEBUG);

        // Database
        $container->register(Database::class, Database::class)
            ->setFactory([Database::class, 'getInstance']);

        // Session
        $container->register(Session::class, Session::class);
        
        // Auth
        $container->register(Auth::class, Auth::class)
            ->setFactory([Auth::class, 'getInstance'])
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));

        // View
        $container->register(View::class, View::class)
            ->addArgument('%views_path%')
            ->addArgument(BASE_PATH . '/cache/views');
    }
    
    /**
     * Register business services
     */
    private function registerBusinessServices(SymfonyContainerBuilder $container): void
    {
        // Services
        $container->register(\TopoclimbCH\Services\AuthService::class)
            ->addArgument(new Reference(Auth::class))
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Services\SectorService::class)
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Services\RouteService::class)
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Services\MediaService::class)
            ->addArgument(new Reference(Database::class));
    }
    
    /**
     * Register controllers
     */
    private function registerControllers(SymfonyContainerBuilder $container): void
    {
        // Liste des contrôleurs avec leurs dépendances
        $controllers = [
            \TopoclimbCH\Controllers\ErrorController::class => [
                View::class,
            ],
            \TopoclimbCH\Controllers\HomeController::class => [
                View::class,
            ],
            \TopoclimbCH\Controllers\AuthController::class => [
                Session::class,
                Database::class,
                View::class,
                \TopoclimbCH\Services\AuthService::class,
            ],
            \TopoclimbCH\Controllers\SectorController::class => [
                View::class,
                Session::class,
                \TopoclimbCH\Services\SectorService::class,
                \TopoclimbCH\Services\MediaService::class,
                Database::class,
            ],
            \TopoclimbCH\Controllers\RouteController::class => [
                View::class,
                Session::class, 
                \TopoclimbCH\Services\RouteService::class,
                Database::class,
            ],
            \TopoclimbCH\Controllers\RegionController::class => [
                View::class,
                Session::class,
                Database::class,
            ],
            \TopoclimbCH\Controllers\SiteController::class => [
                View::class,
                Session::class,
                Database::class,
            ],
            \TopoclimbCH\Controllers\AscentController::class => [
                View::class,
                Session::class,
                Database::class,
                Auth::class,
            ],
            \TopoclimbCH\Controllers\AdminController::class => [
                View::class,
                Session::class,
                Database::class, 
                Auth::class,
            ],
            \TopoclimbCH\Controllers\UserController::class => [
                View::class,
                Session::class,
                Database::class,
                Auth::class,
            ],
        ];
        
        // Enregistrer chaque contrôleur
        foreach ($controllers as $controller => $dependencies) {
            $definition = $container->register($controller);
            
            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }
    
    /**
     * Register middlewares
     */
    private function registerMiddlewares(SymfonyContainerBuilder $container): void
    {
        // Middlewares
        $container->register(\TopoclimbCH\Middleware\AuthMiddleware::class)
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Middleware\AdminMiddleware::class)
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Middleware\ModeratorMiddleware::class)
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));
        
        $container->register(\TopoclimbCH\Middleware\CsrfMiddleware::class)
            ->addArgument(new Reference(Session::class));
    }
}