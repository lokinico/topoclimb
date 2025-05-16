<?php

/**
 * Point d'entrée principal de l'application TopoclimbCH
 */

// Définir le chemin de base de l'application
define('BASE_PATH', dirname(__DIR__));

// Optimisation critique des sessions avant tout
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_trans_sid', 0);
ini_set('session.gc_maxlifetime', 86400); // 24h pour éviter expirations prématurées

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

    // Exécuter l'application qui gère tout le cycle requête/réponse
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

    // Gestion spéciale des erreurs d'authentification
    if ((strpos($e->getMessage(), 'Auth') !== false ||
        strpos($e->getMessage(), 'auth') !== false ||
        strpos($e->getFile(), 'Auth.php') !== false) && isset($session)) {

        error_log("Tentative de nettoyage de la session après erreur Auth");

        // Nettoyage complet des données d'authentification
        unset($_SESSION['auth_user_id']);
        unset($_SESSION['is_authenticated']);
        $session->remove('auth_user_id');
        $session->remove('is_authenticated');

        // Sauvegarde URL courante pour y revenir après login
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        if ($currentUrl !== '/login' && $currentUrl !== '/logout') {
            $_SESSION['intended_url'] = $currentUrl;
            $session->set('intended_url', $currentUrl);
        }

        $session->flash('error', 'Problème d\'authentification. Veuillez vous reconnecter.');

        if (method_exists($session, 'persist')) {
            $session->persist();
        }

        // Rediriger vers login
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
            // Afficher de manière sécurisée les données sensibles
            foreach ($_SESSION as $key => $value) {
                if (is_string($value)) {
                    $displayValue = (strlen($value) > 100 || strpos($key, 'token') !== false || strpos($key, 'password') !== false)
                        ? substr($value, 0, 8) . '...'
                        : $value;
                } else {
                    $displayValue = gettype($value);
                }
                echo htmlspecialchars($key) . ' => ' . htmlspecialchars(is_string($displayValue) ? $displayValue : json_encode($displayValue)) . "\n";
            }
            echo "</pre>";
        }
    } else {
        include BASE_PATH . '/resources/views/errors/500.php';
    }
}
