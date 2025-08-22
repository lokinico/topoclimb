<?php

/**
 * Diagnostic simple sans Composer
 */

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC SIMPLE TOPOCLIMB ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Répertoire actuel: " . __DIR__ . "\n\n";

// Vérifier les autoloaders
echo "=== AUTOLOADERS ===\n";
$local_autoloader = __DIR__ . '/vendor/autoload.php';
$plesk_autoloader = '/tmp/vendor/autoload.php';

if (file_exists($local_autoloader)) {
    echo "✅ Autoloader local: $local_autoloader\n";
} else {
    echo "❌ Autoloader local manquant: $local_autoloader\n";
}

if (file_exists($plesk_autoloader)) {
    echo "✅ Autoloader Plesk: $plesk_autoloader\n";
} else {
    echo "❌ Autoloader Plesk manquant: $plesk_autoloader\n";
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
    $fullPath = __DIR__ . '/' . $file;
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
if (file_exists(__DIR__ . '/bootstrap.php')) {
    $content = file_get_contents(__DIR__ . '/bootstrap.php');
    if (strpos($content, 'plesk_autoloadFile') !== false) {
        echo "✅ Bootstrap.php contient la logique Plesk\n";
    } else {
        echo "❌ Bootstrap.php ne contient pas la logique Plesk\n";
    }
} else {
    echo "❌ Bootstrap.php manquant\n";
}

// Vérifier la vue région
echo "\n=== VUE RÉGION ===\n";
$regionView = __DIR__ . '/resources/views/regions/show.twig';
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

echo "\n=== PERMISSIONS ===\n";
echo "PHP User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown') . "\n";
echo "Working Directory: " . getcwd() . "\n";
echo "Script Directory: " . __DIR__ . "\n";

echo "\n=== FIN DU DIAGNOSTIC ===\n";