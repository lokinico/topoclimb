<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Debug Index.php - " . date('Y-m-d H:i:s') . "\n\n";

echo "📋 REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'undefined') . "\n";
echo "📋 SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'undefined') . "\n";
echo "📋 PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'undefined') . "\n\n";

// Test 1: Autoloader
echo "🧪 Test 1: Autoloader\n";
try {
    require_once '../vendor/autoload.php';
    echo "✅ Autoloader chargé\n\n";
} catch (Exception $e) {
    echo "❌ Erreur autoloader: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Variables d'environnement
echo "🧪 Test 2: Variables d'environnement\n";
try {
    if (file_exists('../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        echo "✅ .env chargé\n";
        echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'undefined') . "\n\n";
    } else {
        echo "❌ .env non trouvé\n\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur .env: " . $e->getMessage() . "\n\n";
}

// Test 3: Base de données
echo "🧪 Test 3: Base de données\n";
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_DATABASE']),
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    echo "✅ Connexion DB OK\n\n";
} catch (Exception $e) {
    echo "❌ Erreur DB: " . $e->getMessage() . "\n\n";
}

// Test 4: Container
echo "🧪 Test 4: Container Symfony\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container construit avec succès\n";
    echo "Type: " . get_class($container) . "\n\n";
} catch (Exception $e) {
    echo "❌ Erreur Container: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

// Test 5: Application
echo "🧪 Test 5: Application\n";
try {
    $app = new \TopoclimbCH\Core\Application($container ?? null);
    echo "✅ Application initialisée\n\n";
} catch (Exception $e) {
    echo "❌ Erreur Application: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

echo "🎯 Fin des tests\n";
?>