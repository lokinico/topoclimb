<?php
// src/Core/ContainerBuilder.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Config\FileLocator;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Router;

class ContainerBuilder
{
    /**
     * Build and configure the dependency injection container with autowiring and caching.
     */
    public function build(): SymfonyContainerBuilder
    {
        $environment = $_ENV['APP_ENV'] ?? 'production';
        $cacheFile = BASE_PATH . '/cache/container/container.php';
        
        // In production, try to load cached container first
        if ($environment === 'production' && file_exists($cacheFile)) {
            require_once $cacheFile;
            if (class_exists('CachedContainer')) {
                return new \CachedContainer();
            }
        }

        // Build container from scratch
        $container = new SymfonyContainerBuilder();

        // Enable autowiring and autoconfiguration
        $container->setParameter('container.autowiring.strict_mode', false);
        $container->setParameter('container.dumper.inline_factories', true);

        // Configuration variables
        $container->setParameter('db_host', $_ENV['DB_HOST'] ?? 'localhost');
        $container->setParameter('db_name', $_ENV['DB_DATABASE'] ?? 'sh139940_');
        $container->setParameter('db_user', $_ENV['DB_USERNAME'] ?? 'root');
        $container->setParameter('db_password', $_ENV['DB_PASSWORD'] ?? '');
        $container->setParameter('environment', $environment);
        $container->setParameter('views_path', BASE_PATH . '/resources/views');
        $container->setParameter('cache_path', BASE_PATH . '/cache/views');

        // Configure autowiring for the src/ directory
        $this->configureAutowiring($container);

        // Register only special services that need custom configuration
        $this->registerSpecialServices($container);

        // Compile container
        $container->compile();

        // Cache container in production
        if ($environment === 'production') {
            $this->cacheContainer($container, $cacheFile);
        }

        return $container;
    }

    /**
     * Cache the compiled container for production use.
     */
    private function cacheContainer(SymfonyContainerBuilder $container, string $cacheFile): void
    {
        $dumper = new PhpDumper($container);
        $cachedContainer = $dumper->dump(['class' => 'CachedContainer']);
        
        // Ensure cache directory exists
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents($cacheFile, $cachedContainer);
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
     * Auto-discover and register services with autowiring (recursive).
     */
    private function autoDiscoverServices(SymfonyContainerBuilder $container, string $namespace, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Calculate relative path from base directory
                $relativePath = substr($file->getPath(), strlen($directory));
                $relativeNamespace = str_replace('/', '\\', $relativePath);
                
                // Build full class name
                $className = $namespace . ($relativeNamespace ? $relativeNamespace . '\\' : '') . $file->getBasename('.php');
                
                if (class_exists($className)) {
                    $definition = $container->autowire($className, $className);
                    $definition->setPublic(true);
                    $definition->setAutoconfigured(true);
                }
            }
        }
    }

    /**
     * Register only special services that need custom configuration.
     * Regular services are now auto-discovered and autowired.
     */
    private function registerSpecialServices(SymfonyContainerBuilder $container): void
    {
        // Logger - needs custom configuration with StreamHandler
        $container->register(LoggerInterface::class, Logger::class)
            ->setPublic(true)
            ->addArgument('app')
            ->addMethodCall('pushHandler', [new Reference('logger.stream_handler')]);

        // StreamHandler for logger - different levels based on environment
        $logLevel = ($_ENV['APP_ENV'] ?? 'production') === 'development' ? Logger::DEBUG : Logger::INFO;
        $container->register('logger.stream_handler', StreamHandler::class)
            ->addArgument(BASE_PATH . '/logs/app.log')
            ->addArgument($logLevel);

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
