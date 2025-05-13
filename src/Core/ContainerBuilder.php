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

            // Configuration du logger
            $container->register(LoggerInterface::class, Logger::class)
                ->addArgument('topoclimbch')
                ->addMethodCall('pushHandler', [
                    new StreamHandler(BASE_PATH . '/logs/app.log', Logger::DEBUG)
                ]);

            // Configuration de la base de données
            $container->register(Database::class, Database::class);

            // Configuration de la session
            $container->register(Session::class, Session::class);

            // Configuration de la vue
            $container->register(View::class, View::class)
                ->addArgument('%views_path%')
                ->addArgument(BASE_PATH . '/cache/views');

            // IMPORTANT: Enregistrer les contrôleurs de manière sécurisée
            $this->registerControllersSecurely($container);

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
     * Register controllers in a secure way to avoid null references
     */
    private function registerControllersSecurely(SymfonyContainerBuilder $container): void
    {
        // Liste des contrôleurs à enregistrer
        $controllers = [
            'ErrorController',
            'HomeController',
            'SectorController',
            // Ajoutez d'autres contrôleurs ici si nécessaire
        ];
        
        // Vérifier et enregistrer chaque contrôleur
        foreach ($controllers as $controllerName) {
            $fullClassName = '\\TopoclimbCH\\Controllers\\' . $controllerName;
            
            // Vérifier si la classe existe avant d'essayer de l'enregistrer
            if (class_exists($fullClassName)) {
                $container->register($fullClassName)
                    ->addArgument(new Reference(View::class));
                
                // Pour SectorController qui a des dépendances supplémentaires
                if ($controllerName === 'SectorController' && 
                    class_exists('\\TopoclimbCH\\Services\\SectorService') && 
                    class_exists('\\TopoclimbCH\\Services\\MediaService')) {
                    
                    // Enregistrer les services requis
                    $container->register('\\TopoclimbCH\\Services\\SectorService')
                        ->addArgument(new Reference(Database::class));
                        
                    $container->register('\\TopoclimbCH\\Services\\MediaService')
                        ->addArgument(new Reference(Database::class));
                    
                    // Mettre à jour l'enregistrement du contrôleur
                    $container->register($fullClassName)
                        ->addArgument(new Reference(View::class))
                        ->addArgument(new Reference(Session::class))
                        ->addArgument(new Reference('\\TopoclimbCH\\Services\\SectorService'))
                        ->addArgument(new Reference('\\TopoclimbCH\\Services\\MediaService'))
                        ->addArgument(new Reference(Database::class));
                }
            } else {
                error_log("Controller class not found: " . $fullClassName);
            }
        }
    }
}