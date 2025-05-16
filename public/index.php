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

// IMPORTANT: Démarrer la session au tout début de l'exécution
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
    error_log("Session démarrée dans index.php, ID: " . session_id());
}

// Variables pour les services principaux
$container = null;
$session = null;
$db = null;
$auth = null;

try {
    // Créer et configurer le conteneur
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();

    // Initialiser le logger
    $logger = $container->get(Psr\Log\LoggerInterface::class);

    // Récupérer la session et la base de données
    $session = $container->get(\TopoclimbCH\Core\Session::class);
    $db = $container->get(\TopoclimbCH\Core\Database::class);

    // IMPORTANT: Initialiser Auth avant de traiter la requête si l'utilisateur est connecté
    // Cela garantit que Auth est disponible partout dans l'application
    if (isset($_SESSION['auth_user_id'])) {
        try {
            $auth = \TopoclimbCH\Core\Auth::getInstance($session, $db);
            $logger->info("Auth initialisé dans index.php pour l'utilisateur ID: " . $_SESSION['auth_user_id']);
        } catch (\Throwable $e) {
            $logger->error("Échec d'initialisation d'Auth dans index.php: " . $e->getMessage());
            // Ne pas lancer d'exception ici, continuer le traitement
        }
    }

    // Initialiser le routeur
    $router = $container->get(\TopoclimbCH\Core\Router::class);
    $logger->info("Router successfully retrieved from container");

    // Charger les routes
    $router->loadRoutes(BASE_PATH . '/config/routes.php');

    // Pour du débogage, on peut afficher les services enregistrés
    if ($environment === 'development' && isset($_GET['debug_container'])) {
        echo "<h2>Services disponibles:</h2><pre>";
        var_dump($container->getServiceIds());
        echo "</pre>";
        exit;
    }

    // Utiliser la classe Application pour gérer le cycle de requête/réponse
    $app = new \TopoclimbCH\Core\Application(
        $router,
        $logger,
        $container,
        $environment
    );

    // Exécuter l'application qui gère tout le cycle requête/réponse
    // incluant l'envoi automatique de la réponse
    $app->run();
} catch (\Throwable $e) {
    // Log l'erreur
    if (isset($logger)) {
        $logger->error($e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    } else {
        error_log("[ERREUR CRITIQUE] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    // Si l'erreur est liée à Auth, essayer de nettoyer la session
    if (strpos($e->getMessage(), 'Auth') !== false && isset($session)) {
        error_log("Tentative de nettoyage de la session après erreur Auth");
        $session->remove('auth_user_id');
        $session->remove('user_authenticated');
        $session->flash('error', 'Votre session a expiré. Veuillez vous reconnecter.');
        $session->persist();

        // Rediriger vers la page d'accueil
        header('Location: /login');
        exit;
    }

    // Afficher une erreur basique en cas de problème
    http_response_code(500);
    if ($environment === 'development') {
        echo "<h1>Erreur serveur</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . " (ligne " . $e->getLine() . ")</p>";
        echo "<h2>Trace</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";

        // Afficher les informations de session qui pourraient être utiles
        if (isset($_SESSION)) {
            echo "<h2>Informations de session</h2>";
            echo "<pre>";
            print_r(array_map(function ($k, $v) {
                return [$k => is_string($v) ? (strlen($v) > 100 ? substr($v, 0, 100) . '...' : $v) : gettype($v)];
            }, array_keys($_SESSION), $_SESSION));
            echo "</pre>";
        }
    } else {
        include BASE_PATH . '/resources/views/errors/500.php';
    }
}
