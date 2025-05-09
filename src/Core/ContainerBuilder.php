<?php
// src/Core/ContainerBuilder.php

namespace TopoclimbCH\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\MediaService;

class ContainerBuilder
{
    /**
     * Build and configure the dependency injection container.
     *
     * @return SymfonyContainerBuilder
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

        // Configuration du logger
        $container->register(LoggerInterface::class, Logger::class)
            ->addArgument('topoclimbch')
            ->addMethodCall('pushHandler', [
                new StreamHandler(BASE_PATH . '/logs/app.log', Logger::DEBUG)
            ]);

        // Configuration de la base de données
        $container->register(Database::class, Database::class)
            ->addArgument('%db_host%')
            ->addArgument('%db_name%')
            ->addArgument('%db_user%')
            ->addArgument('%db_password%');

        // Configuration de la session
        $container->register(Session::class, Session::class);

        // Configuration de la vue
        $container->register(View::class, View::class)
            ->addArgument('%views_path%')
            ->addArgument(BASE_PATH . '/cache/views');
            
        // Services
        $container->register(SectorService::class, SectorService::class)
            ->addArgument(new Reference(Database::class));
            
        $container->register(MediaService::class, MediaService::class)
            ->addArgument(new Reference(Database::class));

        // Configuration du routeur
        $container->register(Router::class, Router::class)
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument($container);

        // Configuration de l'application
        $container->register(Application::class, Application::class)
            ->addArgument(new Reference(Router::class))
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument($container)
            ->addArgument('%environment%');

        // Enregistrement des contrôleurs
        $this->registerControllers($container);

        // Retourner le conteneur configuré
        return $container;
    }
    
    /**
     * Register all controllers in the container
     *
     * @param SymfonyContainerBuilder $container
     * @return void
     */
    private function registerControllers(SymfonyContainerBuilder $container): void
    {
        // ErrorController
        $container->register(\TopoclimbCH\Controllers\ErrorController::class)
            ->addArgument(new Reference(View::class));
            
        // HomeController
        $container->register(\TopoclimbCH\Controllers\HomeController::class)
            ->addArgument(new Reference(View::class));
            
        // SectorController
        $container->register(\TopoclimbCH\Controllers\SectorController::class)
            ->addArgument(new Reference(View::class))
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(SectorService::class))
            ->addArgument(new Reference(MediaService::class))
            ->addArgument(new Reference(Database::class));
            
        // Autres contrôleurs à enregistrer...
    }
}