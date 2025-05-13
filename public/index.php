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
}

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Solution temporaire : Créer manuellement les services essentiels
    // au lieu d'utiliser le conteneur d'injection de dépendances
    $view = new \TopoclimbCH\Core\View();

    // Créer un contrôleur et l'exécuter directement
    $homeController = new \TopoclimbCH\Controllers\HomeController($view);
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $response = $homeController->index($request);
    $response->send();
} catch (\Throwable $e) {
    // Afficher une erreur basique en cas de problème
    http_response_code(500);
    if ($environment === 'development') {
        echo "<h1>Erreur serveur</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . " (ligne " . $e->getLine() . ")</p>";
        echo "<h2>Trace</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        include BASE_PATH . '/resources/views/errors/500.php';
    }
}