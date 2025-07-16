<?php

/**
 * Diagnostic simple sans Composer - VERSION PUBLIQUE
 */

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC SIMPLE TOPOCLIMB ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Répertoire actuel: " . __DIR__ . "\n";
echo "Répertoire parent: " . dirname(__DIR__) . "\n\n";

// Vérifier les autoloaders
echo "=== AUTOLOADERS ===\n";
$app_root = dirname(__DIR__);
$local_autoloader = $app_root . '/vendor/autoload.php';

// Chemins possibles pour l'autoloader Plesk
$plesk_paths = [
    '/tmp/vendor/autoload.php',
    '/home/httpd/vhosts/topoclimb.ch/topoclimb/vendor/autoload.php',
    '/home/httpd/vhosts/topoclimb.ch/vendor/autoload.php',
    '/opt/plesk/php/8.4/bin/composer/vendor/autoload.php'
];

if (file_exists($local_autoloader)) {
    echo "✅ Autoloader local: $local_autoloader\n";
} else {
    echo "❌ Autoloader local manquant: $local_autoloader\n";
}

echo "\n=== RECHERCHE AUTOLOADER PLESK ===\n";
$plesk_found = false;
foreach ($plesk_paths as $path) {
    if (file_exists($path)) {
        echo "✅ Autoloader Plesk trouvé: $path\n";
        $plesk_found = true;
        break;
    }
}

if (!$plesk_found) {
    echo "❌ Aucun autoloader Plesk trouvé dans les chemins standards\n";
    echo "Chemins vérifiés:\n";
    foreach ($plesk_paths as $path) {
        echo "  - $path\n";
    }
}

// Vérifier les fichiers critiques
echo "\n=== FICHIERS CRITIQUES ===\n";
$criticalFiles = [
    'bootstrap.php',
    'public/index.php',
    'public/css/common.css',
    'public/css/pages/regions/show.css',
    'resources/views/regions/show.twig',
    'src/Controllers/RegionController.php',
    'config/routes.php'
];

foreach ($criticalFiles as $file) {
    $fullPath = $app_root . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
        echo "✅ $file (taille: $size, modifié: $modified)\n";
    } else {
        echo "❌ $file (MANQUANT)\n";
    }
}

// Vérifier le contenu de bootstrap.php
echo "\n=== CONTENU BOOTSTRAP.PHP ===\n";
if (file_exists($app_root . '/bootstrap.php')) {
    $content = file_get_contents($app_root . '/bootstrap.php');
    if (strpos($content, 'plesk_autoloadFile') !== false) {
        echo "✅ Bootstrap.php contient la logique Plesk\n";
    } else {
        echo "❌ Bootstrap.php ne contient pas la logique Plesk\n";
        echo "Première ligne: " . substr($content, 0, 100) . "...\n";
    }
} else {
    echo "❌ Bootstrap.php manquant\n";
}

// Vérifier la vue région
echo "\n=== VUE RÉGION ===\n";
$regionView = $app_root . '/resources/views/regions/show.twig';
if (file_exists($regionView)) {
    $content = file_get_contents($regionView);
    $lines = substr_count($content, "\n");
    echo "✅ Vue région trouvée ($lines lignes)\n";
    
    if (strpos($content, 'filters-section') !== false) {
        echo "✅ Nouvelle version avec filtres\n";
    } else {
        echo "❌ Ancienne version sans filtres\n";
    }
} else {
    echo "❌ Vue région manquante\n";
}

// Vérifier les derniers commits Git
echo "\n=== GIT INFO ===\n";
if (file_exists($app_root . '/.git')) {
    echo "✅ Dépôt Git présent\n";
    if (function_exists('exec') && is_executable('/usr/bin/git')) {
        $output = [];
        exec("cd $app_root && git log -1 --oneline 2>/dev/null", $output);
        if (!empty($output)) {
            echo "Dernier commit: " . implode("\n", $output) . "\n";
        }
    }
} else {
    echo "❌ Pas de dépôt Git\n";
}

echo "\n=== PERMISSIONS ===\n";
echo "PHP User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown') . "\n";
echo "Working Directory: " . getcwd() . "\n";
echo "Script Directory: " . __DIR__ . "\n";
echo "App Root: " . $app_root . "\n";

// Vérifier les données en base
echo "\n=== DONNÉES EN BASE ===\n";
try {
    // Essayer de charger l'autoloader pour accéder à la base
    if (file_exists($app_root . '/vendor/autoload.php')) {
        require_once $app_root . '/vendor/autoload.php';
        require_once $app_root . '/bootstrap.php';
        
        // Vérifier la connexion à la base
        $db_file = $app_root . '/topoclimb.db';
        if (file_exists($db_file)) {
            echo "✅ Base de données SQLite trouvée: $db_file\n";
            
            $pdo = new PDO("sqlite:$db_file");
            
            // Compter les régions
            $stmt = $pdo->query("SELECT COUNT(*) FROM regions");
            $region_count = $stmt->fetchColumn();
            echo "📊 Nombre de régions: $region_count\n";
            
            // Compter les secteurs
            $stmt = $pdo->query("SELECT COUNT(*) FROM sectors");
            $sector_count = $stmt->fetchColumn();
            echo "📊 Nombre de secteurs: $sector_count\n";
            
            // Compter les voies
            $stmt = $pdo->query("SELECT COUNT(*) FROM routes");
            $route_count = $stmt->fetchColumn();
            echo "📊 Nombre de voies: $route_count\n";
            
            // Lister quelques régions
            $stmt = $pdo->query("SELECT id, name, slug FROM regions LIMIT 5");
            $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($regions)) {
                echo "🗺️ Régions disponibles:\n";
                foreach ($regions as $region) {
                    echo "  - {$region['name']} (ID: {$region['id']}, slug: {$region['slug']})\n";
                }
            }
            
        } else {
            echo "❌ Base de données non trouvée: $db_file\n";
        }
    } else {
        echo "❌ Impossible de charger l'autoloader\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification base: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";