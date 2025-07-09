<?php
// src/Core/ContainerBuilder.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Router;

class ContainerBuilder
{
    /**
     * Build and configure the dependency injection container with autowiring.
     */
    public function build(): SymfonyContainerBuilder
    {
        $container = new SymfonyContainerBuilder();

        // Enable autowiring and autoconfiguration
        $container->setParameter('container.autowiring.strict_mode', false);
        $container->setParameter('container.dumper.inline_factories', true);

        // Configuration variables
        $container->setParameter('db_host', $_ENV['DB_HOST'] ?? 'localhost');
        $container->setParameter('db_name', $_ENV['DB_DATABASE'] ?? 'sh139940_');
        $container->setParameter('db_user', $_ENV['DB_USERNAME'] ?? 'root');
        $container->setParameter('db_password', $_ENV['DB_PASSWORD'] ?? '');
        $container->setParameter('environment', $_ENV['APP_ENV'] ?? 'production');
        $container->setParameter('views_path', BASE_PATH . '/resources/views');
        $container->setParameter('cache_path', BASE_PATH . '/cache/views');

        // Configure autowiring for the src/ directory
        $this->configureAutowiring($container);

        // Register only special services that need custom configuration
        $this->registerSpecialServices($container);

        return $container;
    }

    /**
     * Configure autowiring for all services in the src/ directory.
     */
    private function configureAutowiring(SymfonyContainerBuilder $container): void
    {
        // Auto-register all services in the src/ directory
        $container->registerForAutoconfiguration('TopoclimbCH\\Services\\*')
            ->addTag('app.service');
        
        $container->registerForAutoconfiguration('TopoclimbCH\\Controllers\\*')
            ->addTag('app.controller');
            
        $container->registerForAutoconfiguration('TopoclimbCH\\Middleware\\*')
            ->addTag('app.middleware');

        // Auto-discover services, controllers, and middleware
        $this->autoDiscoverServices($container, 'TopoclimbCH\\Services\\', BASE_PATH . '/src/Services/');
        $this->autoDiscoverServices($container, 'TopoclimbCH\\Controllers\\', BASE_PATH . '/src/Controllers/');
        $this->autoDiscoverServices($container, 'TopoclimbCH\\Middleware\\', BASE_PATH . '/src/Middleware/');
    }

    /**
     * Auto-discover and register services with autowiring.
     */
    private function autoDiscoverServices(SymfonyContainerBuilder $container, string $namespace, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '*.php');
        
        foreach ($files as $file) {
            $className = $namespace . basename($file, '.php');
            
            if (class_exists($className)) {
                $definition = $container->autowire($className, $className);
                $definition->setPublic(true);
                $definition->setAutoconfigured(true);
            }
        }
    }

    /**
     * Register only special services that need custom configuration.
     * Regular services are now auto-discovered and autowired.
     */
    private function registerSpecialServices(SymfonyContainerBuilder $container): void
    {
        // Logger - needs custom configuration
        $container->register(LoggerInterface::class, Logger::class)
            ->setPublic(true)
            ->addArgument('app');

        // Database - now using dependency injection
        $container->autowire(Database::class, Database::class)
            ->setPublic(true);

        // Session - simple registration
        $container->register(Session::class, Session::class)
            ->setPublic(true);

        // CsrfManager - needs custom arguments
        $container->register(CsrfManager::class, CsrfManager::class)
            ->setPublic(true)
            ->addArgument(new Reference(Session::class))
            ->addArgument([]); // Array vide pour exemptedRoutes

        // Auth - now using dependency injection
        $container->autowire(Auth::class, Auth::class)
            ->setPublic(true);

        // View - needs custom configuration
        $container->register(View::class, View::class)
            ->setPublic(true)
            ->addArgument('%views_path%')
            ->addArgument('%cache_path%')
            ->addArgument(new Reference(CsrfManager::class));

        // Router - needs custom configuration
        $container->register(Router::class, Router::class)
            ->setPublic(true)
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference('service_container'));

        $container->setAlias('router', Router::class)->setPublic(true);
    }

}
