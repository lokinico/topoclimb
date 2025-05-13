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

        // Configuration du logger
        $container->register(LoggerInterface::class, Logger::class)
            ->addArgument('topoclimbch')
            ->addMethodCall('pushHandler', [
                new Reference('logger.handler')
            ]);
            
        $container->register('logger.handler', StreamHandler::class)
            ->addArgument(BASE_PATH . '/logs/app.log')
            ->addArgument(Logger::DEBUG);

        // Configuration de la base de données
        $container->register(Database::class, Database::class);

        // Configuration de la session
        $container->register(Session::class, Session::class);

        // Configuration de la vue
        $container->register(View::class, View::class)
            ->addArgument('%views_path%')
            ->addArgument(BASE_PATH . '/cache/views');
            
        // Configuration de l'authentification
        $container->register(Auth::class, Auth::class)
            ->setFactory([Auth::class, 'getInstance'])
            ->addArgument(new Reference(Session::class))
            ->addArgument(new Reference(Database::class));

        // Enregistrer les contrôleurs (vérifie leur existence)
        $this->registerControllersSafely($container);
        
        // Enregistrer les middlewares (vérifie leur existence)
        $this->registerMiddlewaresSafely($container);
        
        // Enregistrer les services (vérifie leur existence)
        $this->registerServicesSafely($container);

        return $container;
    }
    
    /**
     * Register controllers in a safe way to avoid null references
     */
    private function registerControllersSafely(SymfonyContainerBuilder $container): void
    {
        // Définir les contrôleurs et leurs dépendances
        $controllers = [
            'HomeController' => [View::class],
            'AuthController' => [Session::class, Database::class, View::class],
            'SectorController' => [View::class, Session::class],
            'RouteController' => [View::class],
            'RegionController' => [View::class],
            'SiteController' => [View::class],
            'UserController' => [View::class, Session::class, Auth::class],
            'AdminController' => [View::class, Session::class, Auth::class],
            'AscentController' => [View::class, Session::class, Auth::class],
            'ErrorController' => [View::class]
        ];
        
        // Enregistrer chaque contrôleur si la classe existe
        foreach ($controllers as $name => $deps) {
            $className = "\\TopoclimbCH\\Controllers\\$name";
            
            if (class_exists($className)) {
                $definition = $container->register($className, $className);
                
                // Ajouter les dépendances
                foreach ($deps as $dep) {
                    $definition->addArgument(new Reference($dep));
                }
            }
        }
    }
    
    /**
     * Register middlewares in a safe way
     */
    private function registerMiddlewaresSafely(SymfonyContainerBuilder $container): void
    {
        // Définir les middlewares et leurs dépendances
        $middlewares = [
            'AuthMiddleware' => [Session::class, Database::class],
            'AdminMiddleware' => [Session::class, Database::class],
            'ModeratorMiddleware' => [Session::class, Database::class],
            'CsrfMiddleware' => [Session::class]
        ];
        
        // Enregistrer chaque middleware si la classe existe
        foreach ($middlewares as $name => $deps) {
            $className = "\\TopoclimbCH\\Middleware\\$name";
            
            if (class_exists($className)) {
                $definition = $container->register($className, $className);
                
                // Ajouter les dépendances
                foreach ($deps as $dep) {
                    $definition->addArgument(new Reference($dep));
                }
            }
        }
    }
    
    /**
     * Register services in a safe way
     */
    private function registerServicesSafely(SymfonyContainerBuilder $container): void
    {
        // Définir les services et leurs dépendances
        $services = [
            'AuthService' => [Auth::class, Session::class, Database::class],
            'SectorService' => [Database::class],
            'RouteService' => [Database::class],
            'MediaService' => [Database::class]
        ];
        
        // Enregistrer chaque service si la classe existe
        foreach ($services as $name => $deps) {
            $className = "\\TopoclimbCH\\Services\\$name";
            
            if (class_exists($className)) {
                $definition = $container->register($className, $className);
                
                // Ajouter les dépendances
                foreach ($deps as $dep) {
                    $definition->addArgument(new Reference($dep));
                }
            }
        }
    }
}