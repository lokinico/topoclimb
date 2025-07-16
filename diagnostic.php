<?php

/**
 * Script de diagnostic pour vérifier les fichiers déployés
 */

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC DE DÉPLOIEMENT TOPOCLIMB ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Vérifier les fichiers critiques
$criticalFiles = [
    'bootstrap.php',
    'public/css/common.css',
    'public/css/pages/regions/show.css',
    'resources/views/regions/show.twig',
    'src/Controllers/RegionController.php',
    'config/routes.php',
    'vendor/autoload.php'
];

echo "=== FICHIERS CRITIQUES ===\n";
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

echo "\n=== VÉRIFICATION DES ROUTES ===\n";
if (file_exists(__DIR__ . '/config/routes.php')) {
    $routes = include __DIR__ . '/config/routes.php';
    
    // Chercher la route regions
    $regionRoutes = array_filter($routes, function($route) {
        return isset($route['path']) && $route['path'] === '/regions';
    });
    
    if (!empty($regionRoutes)) {
        echo "✅ Route /regions trouvée\n";
        foreach ($regionRoutes as $route) {
            echo "   - Méthode: " . $route['method'] . "\n";
            echo "   - Contrôleur: " . $route['controller'] . "\n";
            echo "   - Action: " . $route['action'] . "\n";
            if (isset($route['middlewares'])) {
                echo "   - Middlewares: " . implode(', ', $route['middlewares']) . "\n";
            }
        }
    } else {
        echo "❌ Route /regions non trouvée\n";
    }
} else {
    echo "❌ Fichier routes.php manquant\n";
}

echo "\n=== VÉRIFICATION DE LA VUE RÉGION ===\n";
$regionView = __DIR__ . '/resources/views/regions/show.twig';
if (file_exists($regionView)) {
    $content = file_get_contents($regionView);
    $lines = substr_count($content, "\n");
    echo "✅ Vue région trouvée ($lines lignes)\n";
    
    // Vérifier si c'est la nouvelle version avec filtres
    if (strpos($content, 'filters-section') !== false) {
        echo "✅ Nouvelle version avec filtres détectée\n";
    } else {
        echo "❌ Ancienne version sans filtres\n";
    }
} else {
    echo "❌ Vue région manquante\n";
}

echo "\n=== VÉRIFICATION CSS ===\n";
$commonCss = __DIR__ . '/public/css/common.css';
if (file_exists($commonCss)) {
    $size = filesize($commonCss);
    echo "✅ common.css trouvé (taille: $size)\n";
} else {
    echo "❌ common.css manquant\n";
}

$regionCss = __DIR__ . '/public/css/pages/regions/show.css';
if (file_exists($regionCss)) {
    $content = file_get_contents($regionCss);
    $size = filesize($regionCss);
    echo "✅ regions/show.css trouvé (taille: $size)\n";
    
    // Vérifier si c'est la nouvelle version harmonisée
    if (strpos($content, '@import url(\'../../common.css\')') !== false) {
        echo "✅ CSS harmonisé détecté\n";
    } else {
        echo "❌ CSS non harmonisé\n";
    }
} else {
    echo "❌ regions/show.css manquant\n";
}

echo "\n=== VÉRIFICATION CONTRÔLEUR ===\n";
$controller = __DIR__ . '/src/Controllers/RegionController.php';
if (file_exists($controller)) {
    $content = file_get_contents($controller);
    $lines = substr_count($content, "\n");
    echo "✅ RegionController trouvé ($lines lignes)\n";
    
    // Vérifier si c'est la nouvelle version avec filtres
    if (strpos($content, 'validateRegionFilters') !== false) {
        echo "✅ Nouvelle version avec filtres détectée\n";
    } else {
        echo "❌ Ancienne version sans filtres\n";
    }
} else {
    echo "❌ RegionController manquant\n";
}

echo "\n=== GIT INFO ===\n";
if (file_exists(__DIR__ . '/.git')) {
    echo "✅ Dépôt Git présent\n";
    if (function_exists('exec')) {
        exec('cd ' . __DIR__ . ' && git log -1 --oneline', $output);
        if (!empty($output)) {
            echo "Dernier commit: " . implode("\n", $output) . "\n";
        }
    }
} else {
    echo "❌ Pas de dépôt Git\n";
}

echo "\n=== RECOMMANDATIONS ===\n";
echo "Si des fichiers sont manquants, vérifiez que le déploiement Plesk a bien récupéré les derniers fichiers.\n";
echo "Si vous voyez 'Ancienne version', cela signifie que les fichiers n'ont pas été mis à jour.\n";
echo "\n=== FIN DU DIAGNOSTIC ===\n";