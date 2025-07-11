<?php

/**
 * TopoclimbCH - Application de gestion de la communauté alpine
 *
 * @package TopoclimbCH
 * @author  Topoclimb Team
 * @license MIT
 */

use TopoclimbCH\Core\Container;

/**
 * Point d'entrée principal de l'application TopoclimbCH
 */

// Chargement du bootstrap AVANT toute configuration
require_once dirname(__DIR__) . '/bootstrap.php';

// CRITIQUE: Configuration des sessions AVANT toute sortie
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.gc_maxlifetime', 86400); // 24h pour éviter expirations prématurées
}

// ============= DÉBUT CONFIGURATION DE LOGS AMÉLIORÉE =============
// Créer un répertoire de logs s'il n'existe pas
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Définir un fichier de log dédié et formaté avec la date
$logFile = $logDir . '/debug-' . date('Y-m-d') . '.log';

// Configuration des logs
ini_set('log_errors', 1);
ini_set('error_log', $logFile);
ini_set('log_errors_max_len', 0); // Pas de limite de longueur pour les logs

// Activer tous les types d'erreurs
error_reporting(E_ALL);

// En développement uniquement, afficher aussi les erreurs à l'écran
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Informations sur la requête pour le débogage
$requestInfo = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Log de démarrage d'exécution
error_log("========== DÉBUT DE REQUÊTE ==========");
error_log("URI: " . $requestInfo['uri'] . " | Méthode: " . $requestInfo['method'] . " | IP: " . $requestInfo['ip']);
// ============= FIN CONFIGURATION DE LOGS AMÉLIORÉE =============

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

// CRUCIAL: Session handling amélioré
if (session_status() === PHP_SESSION_NONE) {
    // Paramètres de sécurité pour les cookies de session
    session_set_cookie_params([
        'lifetime' => 0, // Session fermée à la fermeture du navigateur
        'path' => '/',
        'domain' => '', // Domaine automatique
        'secure' => $environment === 'production',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // IMPORTANT: Préserver l'ID de session s'il existe dans le cookie
    if (isset($_COOKIE[session_name()])) {
        $sessionId = $_COOKIE[session_name()];
        // Valider le format de l'ID pour éviter les injections
        if (preg_match('/^[a-zA-Z0-9,-]{22,128}$/', $sessionId)) {
            session_id($sessionId);
            error_log("Réutilisation de l'ID de session existant: " . substr($sessionId, 0, 8) . "...");
        }
    }

    session_start();
    error_log("Session démarrée dans index.php, ID: " . session_id());

    // Diagnostic des données d'authentification
    if (isset($_SESSION['auth_user_id'])) {
        error_log("Session active avec auth_user_id: " . $_SESSION['auth_user_id']);
    } else {
        error_log("Session active mais sans auth_user_id");
    }
}

// ============= GESTIONNAIRE D'ERREURS GLOBAL =============
// Gestionnaire d'exceptions global amélioré (APRÈS les sessions)
set_exception_handler(function (\Throwable $e) use ($requestInfo) {
    $errorDetails = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'type' => get_class($e),
        'request_uri' => $requestInfo['uri'],
        'request_method' => $requestInfo['method'],
        'session_id' => session_status() === PHP_SESSION_ACTIVE ? session_id() : 'inactive'
    ];

    // Log détaillé de l'exception
    error_log("=============== EXCEPTION DÉTAILLÉE ===============");
    foreach ($errorDetails as $key => $value) {
        error_log("$key: $value");
    }

    // Trace d'appel complète
    error_log("------ TRACE D'APPEL ------");
    error_log($e->getTraceAsString());

    error_log("==================================================");

    // Afficher une erreur basique selon l'environnement
    $environment = $_ENV['APP_ENV'] ?? 'production';
    http_response_code(500);
    
    if ($environment === 'development') {
        echo "<h1>Erreur serveur</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . " (ligne " . $e->getLine() . ")</p>";
    } else {
        echo "<h1>Erreur temporaire</h1><p>Le site est temporairement indisponible. Veuillez réessayer plus tard.</p>";
    }
    exit;
});
// ============= FIN GESTIONNAIRE D'ERREURS =============

// Variables pour les services principaux
$container = null;
$session = null;
$db = null;
$auth = null;
$logger = null;

try {
    // Créer et configurer le conteneur
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    Container::getInstance($container);

    // Initialiser le logger
    $logger = $container->get(Psr\Log\LoggerInterface::class);

    // Récupérer la session et la base de données
    $session = $container->get(\TopoclimbCH\Core\Session::class);
    $db = $container->get(\TopoclimbCH\Core\Database::class);

    // Vérifier si l'utilisateur est authentifié
    if (isset($_SESSION['auth_user_id'])) {
        try {
            // Laisser le container gérer l'initialisation d'Auth
            $auth = $container->get(\TopoclimbCH\Core\Auth::class);
            $logger->info("Auth initialisé via container pour l'utilisateur ID: " . $_SESSION['auth_user_id']);

            // Vérification critique que l'authentification est toujours valide
            if (!$auth->check()) {
                $logger->warning("Utilisateur en session mais introuvable en base: " . $_SESSION['auth_user_id']);
                unset($_SESSION['auth_user_id']);
                unset($_SESSION['is_authenticated']);
                $session->remove('auth_user_id');
                $session->remove('is_authenticated');
                $session->flash('error', 'Votre session a expiré. Veuillez vous reconnecter.');
            }
        } catch (\Throwable $e) {
            $logger->error("Échec d'initialisation d'Auth via container: " . $e->getMessage());
            // Nettoyage de session si l'authentification échoue
            unset($_SESSION['auth_user_id']);
            unset($_SESSION['is_authenticated']);
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

    // Débogage de session (en développement uniquement)
    if ($environment === 'development' && isset($_GET['debug_session'])) {
        echo "<h2>Données de session:</h2><pre>";
        var_dump($_SESSION);
        echo "</pre>";
        echo "<h2>Cookies:</h2><pre>";
        var_dump($_COOKIE);
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

    // Log avant d'exécuter l'application
    error_log("Avant app->run() pour " . $_SERVER['REQUEST_URI']);

    // Exécuter l'application qui gère tout le cycle requête/réponse
    $app->run();

    // Log après l'exécution (si l'application n'a pas terminé le script)
    error_log("Après app->run() pour " . $_SERVER['REQUEST_URI'] . " - Ce message ne devrait pas apparaître normalement");
} catch (\Throwable $e) {
    // Log l'erreur simplement
    if (isset($logger)) {
        $logger->error($e->getMessage(), ['exception' => $e]);
    } else {
        error_log("[ERREUR CRITIQUE] " . $e->getMessage());
    }

    // Le gestionnaire d'exceptions global s'occupera du reste
    throw $e;
}

// Log de fin de requête normale (si on arrive jusqu'ici)
error_log("========== FIN DE REQUÊTE (NORMAL) ==========");
