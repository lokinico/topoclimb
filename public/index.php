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
    // Paramètres de sécurité pour les cookies de session
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $environment === 'production',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

try {
    // Créer et configurer le conteneur
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    
    // Initialiser Container.php avec le conteneur Symfony
    $appContainer = \TopoclimbCH\Core\Container::getInstance($container);
    
    // Créer un logger
    $logger = $container->get(\Psr\Log\LoggerInterface::class);
    
    // Initialiser le routeur
    $router = new \TopoclimbCH\Core\Router($logger, $container);
    
    // Charger les routes
    $router->loadRoutes(BASE_PATH . '/config/routes.php');
    
    // Traiter la requête
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    
    // Pour du débogage, on peut afficher les services enregistrés
    if ($environment === 'development' && isset($_GET['debug_container'])) {
        echo "<h2>Services disponibles:</h2><pre>";
        var_dump($container->getServiceIds());
        echo "</pre>";
        exit;
    }
    
    // Dispatcher la requête
    $response = $router->dispatch($request);
    
    // Envoyer la réponse
    $response->send();
    
} catch (\Throwable $e) {
    // Log l'erreur
    if (isset($logger)) {
        $logger->error($e->getMessage(), ['exception' => $e]);
    } else {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
    
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