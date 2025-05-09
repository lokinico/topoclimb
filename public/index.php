<?php
/**
 * Point d'entrée principal de l'application TopoclimbCH
 */

// Définir le chemin de base de l'application
define('BASE_PATH', dirname(__DIR__));

// Charger l'autoloader de Composer
require BASE_PATH . '/vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Récupérer l'environnement actuel
$environment = $_ENV['APP_ENV'] ?? 'production';

// Configurer le gestionnaire d'erreurs en fonction de l'environnement
if ($environment === 'development') {
    // Afficher toutes les erreurs en développement
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Utiliser Whoops pour un affichage plus joli des erreurs
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();
} else {
    // En production, ne pas afficher les erreurs
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    // Configurer le gestionnaire d'erreurs personnalisé
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}

// Démarrer la session
session_start();

// Initialiser le conteneur d'injection de dépendances
$containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
$container = $containerBuilder->build();

// Créer et exécuter l'application
try {
    /** @var \TopoclimbCH\Core\Application $app */
    $app = $container->get(\TopoclimbCH\Core\Application::class);
    $response = $app->handle();
    $response->send();
} catch (Throwable $e) {
    // Logger l'erreur
    if ($container->has(\Psr\Log\LoggerInterface::class)) {
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        $logger->error($e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    // Afficher une page d'erreur générique en production
    if ($environment !== 'development') {
        http_response_code(500);
        include BASE_PATH . '/resources/views/errors/500.php';
    } else {
        // En développement, laisser Whoops afficher l'erreur
        throw $e;
    }
}