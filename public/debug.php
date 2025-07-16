<?php
/**
 * Page de débogage simple pour topoclimb.ch
 */

echo "<!DOCTYPE html><html><head><title>Debug TopoclimbCH</title></head><body>";
echo "<h1>🔍 Debug TopoclimbCH</h1>";

echo "<h2>📊 Configuration</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "</pre>";

echo "<h2>🌍 Variables d'environnement</h2>";
if (file_exists(__DIR__ . '/../.env')) {
    echo "<p>✅ Fichier .env trouvé</p>";
    
    // Charger l'autoloader
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Charger les variables d'environnement
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
    
    echo "<pre>";
    echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'non défini') . "\n";
    echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'non défini') . "\n";
    echo "DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? 'non défini') . "\n";
    echo "</pre>";
} else {
    echo "<p>❌ Fichier .env non trouvé</p>";
}

echo "<h2>🔧 Test connexion base de données</h2>";
try {
    $dsn = "mysql:host=" . ($_ENV['DB_HOST'] ?? '127.0.0.1') . 
           ";port=" . ($_ENV['DB_PORT'] ?? '3306') . 
           ";dbname=" . ($_ENV['DB_DATABASE'] ?? 'topoclimb');
    
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '');
    echo "<p>✅ Connexion base de données réussie</p>";
    
    // Test simple
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<p>✅ Test query OK: " . $result['test'] . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erreur connexion BDD: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>📁 Fichiers importants</h2>";
$files = [
    '/.htaccess' => __DIR__ . '/../.htaccess',
    '/public/.htaccess' => __DIR__ . '/.htaccess',
    '/public/index.php' => __DIR__ . '/index.php',
    '/config/routes.php' => __DIR__ . '/../config/routes.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<p>✅ $name existe (" . filesize($path) . " bytes)</p>";
    } else {
        echo "<p>❌ $name manquant</p>";
    }
}

echo "<h2>🌐 Test de routage simple</h2>";
echo "<p><a href='/'>Tester page d'accueil</a></p>";
echo "<p><a href='/map'>Tester page carte</a></p>";
echo "<p><a href='/login'>Tester page login</a></p>";

echo "</body></html>";
?>