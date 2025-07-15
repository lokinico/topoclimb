<?php
// Script de diagnostic pour production
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic TopoclimbCH Production</h1>";
echo "<pre>";

// 1. Vérifier PHP
echo "1. VERSION PHP\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 2. Vérifier les extensions PHP requises
echo "2. EXTENSIONS PHP REQUISES\n";
$required_extensions = [
    'pdo', 'pdo_mysql', 'pdo_sqlite', 'json', 'mbstring', 
    'curl', 'openssl', 'session', 'xml', 'zip'
];

foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? "✅" : "❌";
    echo "$status $ext\n";
}
echo "\n";

// 3. Vérifier les fichiers critiques
echo "3. FICHIERS CRITIQUES\n";
$critical_files = [
    'bootstrap.php',
    'src/Core/Database.php',
    'src/Core/Router.php',
    'src/Core/View.php',
    'src/Controllers/HomeController.php',
    'config/routes.php',
    'composer.json'
];

foreach ($critical_files as $file) {
    $status = file_exists(__DIR__ . '/' . $file) ? "✅" : "❌";
    echo "$status $file\n";
}
echo "\n";

// 4. Vérifier les permissions
echo "4. PERMISSIONS RÉPERTOIRES\n";
$directories = [
    'resources/views',
    'public',
    'src',
    'vendor'
];

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $readable = is_readable($path) ? "R" : "-";
        $writable = is_writable($path) ? "W" : "-";
        echo "✅ $dir ($perms) $readable$writable\n";
    } else {
        echo "❌ $dir (non trouvé)\n";
    }
}
echo "\n";

// 5. Vérifier Composer
echo "5. COMPOSER\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ Autoload Composer trouvé\n";
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "✅ Autoload chargé avec succès\n";
    } catch (Exception $e) {
        echo "❌ Erreur autoload: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ vendor/autoload.php non trouvé\n";
    echo "   Exécutez: composer install\n";
}
echo "\n";

// 6. Test de base de données
echo "6. TEST BASE DE DONNÉES\n";
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ Fichier .env trouvé\n";
    
    // Charger les variables d'environnement
    $env = file_get_contents(__DIR__ . '/.env');
    $lines = explode("\n", $env);
    foreach ($lines as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            $parts = explode('=', $line, 2);
            if (count($parts) == 2) {
                $_ENV[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    try {
        $db_driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        echo "Driver DB: $db_driver\n";
        
        if ($db_driver === 'sqlite') {
            $db_path = $_ENV['DB_PATH'] ?? __DIR__ . '/test.db';
            echo "Chemin SQLite: $db_path\n";
            if (file_exists($db_path)) {
                echo "✅ Base SQLite trouvée\n";
            } else {
                echo "❌ Base SQLite non trouvée\n";
            }
        } else {
            echo "Host: " . ($_ENV['DB_HOST'] ?? 'non défini') . "\n";
            echo "Database: " . ($_ENV['DB_NAME'] ?? 'non défini') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur config DB: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Fichier .env non trouvé\n";
    echo "   Créez un fichier .env avec la configuration DB\n";
}
echo "\n";

// 7. Test de chargement des classes
echo "7. TEST CHARGEMENT CLASSES\n";
$test_classes = [
    'TopoclimbCH\\Core\\Database',
    'TopoclimbCH\\Core\\Router',
    'TopoclimbCH\\Core\\View',
    'TopoclimbCH\\Controllers\\HomeController'
];

foreach ($test_classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class\n";
    } else {
        echo "❌ $class\n";
    }
}
echo "\n";

// 8. Test de création d'une instance de base
echo "8. TEST INSTANCIATION\n";
try {
    if (class_exists('TopoclimbCH\\Core\\Database')) {
        echo "✅ Test Database possible\n";
    }
    
    if (class_exists('TopoclimbCH\\Core\\Router')) {
        echo "✅ Test Router possible\n";
    }
    
    if (class_exists('TopoclimbCH\\Core\\View')) {
        echo "✅ Test View possible\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur instanciation: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🔍 RECOMMANDATIONS:\n";
echo "1. Vérifiez que 'composer install' a été exécuté\n";
echo "2. Créez un fichier .env avec la configuration DB\n";
echo "3. Vérifiez les permissions des fichiers/dossiers\n";
echo "4. Consultez les logs PHP de votre serveur\n";
echo "5. Activez les erreurs PHP temporairement\n";

echo "</pre>";
?>