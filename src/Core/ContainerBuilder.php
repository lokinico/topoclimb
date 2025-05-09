<?php

namespace TopoclimbCH\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

        // Paramètres de la base de données depuis .env
        $container->setParameter('db_host', $_ENV['DB_HOST'] ?? 'localhost');
        $container->setParameter('db_name', $_ENV['DB_DATABASE'] ?? 'sh139940_');
        $container->setParameter('db_user', $_ENV['DB_USERNAME'] ?? 'root');
        $container->setParameter('db_password', $_ENV['DB_PASSWORD'] ?? '');

        // Configuration du routeur
        $container->register(Router::class, Router::class)
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument($container);

        // Configuration de l'application
        $container->register(Application::class, Application::class)
            ->addArgument(new Reference(Router::class))
            ->addArgument(new Reference(LoggerInterface::class));

        // Retourner le conteneur configuré
        return $container;
    }
}