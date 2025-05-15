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

    private function registerCoreServices(SymfonyContainerBuilder $container): void
    {
        // Logger
        $container->register(LoggerInterface::class, Logger::class)
            ->setPublic(true)
            ->addArgument('app')
            ->addMethodCall('pushHandler', [
                new Reference('logger.handler')
            ]);

        $container->register('logger.handler', StreamHandler::class)
            ->setPublic(true)
            ->addArgument(BASE_PATH . '/logs/app.log')
            ->addArgument(Logger::DEBUG);

        // Database
        $container->register(Database::class, Database::class)
            ->setPublic(true)
            ->setFactory([Database::class, 'getInstance']);

        // Session
        $container->register(Session::class, Session::class)
            ->setPublic(true);

        // Auth
        $container->register(Auth::class, Auth::class)
            ->setPublic(true)
            ->setFactory([Auth::class, 'getInstance'])
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));

        // View
        $container->register(View::class, View::class)
            ->setPublic(true)
            ->addArgument('%views_path%')
            ->addArgument('%cache_path%');
    }

    private function registerBusinessServices(SymfonyContainerBuilder $container): void
    {
        // Services métier
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
            ]
        ];

        foreach ($services as $id => $dependencies) {
            $definition = $container->register($id, $id);
            $definition->setPublic(true);

            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }

    private function registerControllers(SymfonyContainerBuilder $container): void
    {
        // Définition des contrôleurs et leurs dépendances
        $controllers = [
            'TopoclimbCH\\Controllers\\HomeController' => [
                View::class,
                Session::class
            ],
            'TopoclimbCH\\Controllers\\AuthController' => [
                View::class,
                Session::class,
                Database::class
            ],
            'TopoclimbCH\\Controllers\\SectorController' => [
                View::class,
                Session::class,
                'TopoclimbCH\\Services\\SectorService',
                'TopoclimbCH\\Services\\MediaService',
                Database::class
            ],
            'TopoclimbCH\\Controllers\\RouteController' => [
                View::class,
                Session::class,
                'TopoclimbCH\\Services\\RouteService',
                'TopoclimbCH\\Services\\MediaService',
                'TopoclimbCH\\Services\\SectorService',
                'TopoclimbCH\\Services\\AuthService'
            ],
            'TopoclimbCH\\Controllers\\RegionController' => [
                View::class,
                Session::class
            ],
            'TopoclimbCH\\Controllers\\SiteController' => [
                View::class,
                Session::class
            ],
            'TopoclimbCH\\Controllers\\ErrorController' => [
                View::class,
                Session::class
            ],
            'TopoclimbCH\\Controllers\\UserController' => [
                View::class,
                Session::class,
                Auth::class
            ],
            'TopoclimbCH\\Controllers\\AdminController' => [
                View::class,
                Session::class,
                Auth::class
            ],
            'TopoclimbCH\\Controllers\\AscentController' => [
                View::class,
                Session::class,
                Auth::class
            ]
        ];

        foreach ($controllers as $id => $dependencies) {
            if ($_ENV['APP_ENV'] === 'development') {
                error_log("HomeController exists in container: " . ($container->has('TopoclimbCH\\Controllers\\HomeController') ? 'YES' : 'NO'));
            }

            $definition = $container->register($id, $id);
            $definition->setPublic(true);

            foreach ($dependencies as $dependency) {
                $definition->addArgument(new Reference($dependency));
            }
        }
    }

    private function registerMiddlewares(SymfonyContainerBuilder $container): void
    {
        // Définition des middlewares et leurs dépendances
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
            'TopoclimbCH\\Middleware\\CsrfMiddleware' => [
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
