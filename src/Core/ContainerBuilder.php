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
        
        // TEMPORARILY DISABLE CONTAINER CACHING TO PREVENT CORRUPTION
        // In production, try to load cached container first
        if (false && $environment === 'production' && file_exists($cacheFile)) {
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

        // Register controllers with explicit configuration (temporary fix for complex dependencies)
        $this->registerControllersExplicitly($container);

        // Compile container
        $container->compile();

        // TEMPORARILY DISABLE CONTAINER CACHING
        // Cache container in production
        if (false && $environment === 'production') {
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
        
        // Register BaseController explicitly since other controllers inherit from it
        $container->autowire('TopoclimbCH\\Controllers\\BaseController', 'TopoclimbCH\\Controllers\\BaseController')
            ->setPublic(false); // Abstract class, should not be public
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
                
                if (class_exists($className) && $this->canAutowire($className)) {
                    $definition = $container->autowire($className, $className);
                    $definition->setPublic(true);
                    $definition->setAutoconfigured(true);
                    $definition->setShared(true); // Force singleton behavior
                }
            }
        }
    }

    /**
     * Check if a class can be safely autowired (all dependencies exist).
     */
    private function canAutowire(string $className): bool
    {
        try {
            $reflection = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                return true; // No constructor, safe to autowire
            }
            
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                
                if ($type && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencyClass = $type->getName();
                    
                    // Check if the dependency class exists
                    if (!class_exists($dependencyClass) && !interface_exists($dependencyClass)) {
                        error_log("Skipping autowiring for $className: dependency $dependencyClass not found");
                        return false;
                    }
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error checking autowiring for $className: " . $e->getMessage());
            return false;
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
            ->setShared(true)
            ->addArgument('app')
            ->addMethodCall('pushHandler', [new Reference('logger.stream_handler')]);

        // StreamHandler for logger - different levels based on environment
        $logLevel = ($_ENV['APP_ENV'] ?? 'production') === 'development' ? Logger::DEBUG : Logger::INFO;
        $container->register('logger.stream_handler', StreamHandler::class)
            ->addArgument(BASE_PATH . '/logs/app.log')
            ->addArgument($logLevel);

        // Database - now using dependency injection
        $container->autowire(Database::class, Database::class)
            ->setPublic(true)
            ->setShared(true);

        // Session - simple registration
        $container->register(Session::class, Session::class)
            ->setPublic(true)
            ->setShared(true);

        // CsrfManager - needs custom arguments
        $container->register(CsrfManager::class, CsrfManager::class)
            ->setPublic(true)
            ->setShared(true)
            ->addArgument(new Reference(Session::class))
            ->addArgument([]); // Array vide pour exemptedRoutes

        // Auth - now using dependency injection
        $container->autowire(Auth::class, Auth::class)
            ->setPublic(true)
            ->setShared(true);

        // View - needs custom configuration
        $container->register(View::class, View::class)
            ->setPublic(true)
            ->setShared(true)
            ->addArgument('%views_path%')
            ->addArgument('%cache_path%')
            ->addArgument(new Reference(CsrfManager::class));

        // Router - needs custom configuration
        $container->register(Router::class, Router::class)
            ->setPublic(true)
            ->setShared(true)
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference('service_container'));

        $container->setAlias('router', Router::class)->setPublic(true);
    }

    /**
     * Register controllers with explicit dependencies (temporary fix for complex inheritance).
     */
    private function registerControllersExplicitly(SymfonyContainerBuilder $container): void
    {
        // For controllers with complex dependencies, we need to override the autowiring
        // with explicit configuration
        $controllers = [
            'TopoclimbCH\\Controllers\\HomeController' => [
                View::class,                              // BaseController
                Session::class,                           // BaseController
                CsrfManager::class,                       // BaseController
                Database::class,                          // BaseController + HomeController
                Auth::class,                              // BaseController
                'TopoclimbCH\\Services\\RegionService',   // HomeController
                'TopoclimbCH\\Services\\SiteService',     // HomeController
                'TopoclimbCH\\Services\\SectorService',   // HomeController
                'TopoclimbCH\\Services\\RouteService',    // HomeController
                'TopoclimbCH\\Services\\UserService',     // HomeController
                'TopoclimbCH\\Services\\WeatherService'   // HomeController
            ],
            'TopoclimbCH\\Controllers\\ErrorController' => [
                View::class,                              // BaseController
                Session::class,                           // BaseController
                CsrfManager::class,                       // BaseController
                Database::class,                          // BaseController (optional)
                Auth::class                               // BaseController (optional)
            ],
            'TopoclimbCH\\Controllers\\AuthController' => [
                View::class,                              // BaseController
                Session::class,                           // BaseController
                CsrfManager::class,                       // BaseController
                Database::class,                          // BaseController (optional)
                Auth::class,                              // BaseController (optional)
                'TopoclimbCH\\Services\\AuthService'      // AuthController
            ],
            'TopoclimbCH\\Controllers\\MapController' => [
                View::class,                              // BaseController
                Session::class,                           // BaseController
                CsrfManager::class,                       // BaseController
                Database::class,                          // BaseController (optional)
                Auth::class                               // BaseController (optional)
            ],
            // Add other controllers as needed
        ];

        foreach ($controllers as $id => $dependencies) {
            if (class_exists($id)) {
                // Remove the autowired definition if it exists
                if ($container->hasDefinition($id)) {
                    $container->removeDefinition($id);
                }
                
                // Register with explicit dependencies
                $definition = $container->register($id, $id);
                $definition->setPublic(true);

                foreach ($dependencies as $dependency) {
                    $definition->addArgument(new Reference($dependency));
                }
            }
        }
    }

}
