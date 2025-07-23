<?php
/**
 * DEBUG ROUTE - Voir ce qui se passe vraiment avec /map
 */

echo "🔍 DEBUG ROUTE /map\n";
echo "==================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Vérifier les fichiers critiques
echo "1. VÉRIFICATION FICHIERS:\n";

$files = [
    '/home/nibaechl/topoclimb/config/routes.php' => 'Configuration routes',
    '/home/nibaechl/topoclimb/src/Controllers/MapController.php' => 'MapController',
    '/home/nibaechl/topoclimb/resources/views/map/index.twig' => 'Template Twig',
    '/home/nibaechl/topoclimb/resources/views/layouts/fullscreen.twig' => 'Layout fullscreen',
    '/home/nibaechl/topoclimb/public/index.php' => 'Point entrée',
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        $mtime = date('Y-m-d H:i:s', filemtime($file));
        echo "✅ $desc: modifié $mtime\n";
    } else {
        echo "❌ $desc: MANQUANT\n";
    }
}

echo "\n2. TEST DIRECT DU ROUTING:\n";

// 2. Simuler le processus de routing
$currentDir = '/home/nibaechl/topoclimb';
chdir($currentDir);

// Inclure le système de routing
if (file_exists('config/routes.php')) {
    $routes = include 'config/routes.php';
    
    // Chercher la route /map
    $mapRoute = null;
    foreach ($routes as $route) {
        if (isset($route['path']) && $route['path'] === '/map') {
            $mapRoute = $route;
            break;
        }
    }
    
    if ($mapRoute) {
        echo "✅ Route /map trouvée dans config:\n";
        echo "   Contrôleur: " . ($mapRoute['controller'] ?? 'non défini') . "\n";
        echo "   Méthode: " . ($mapRoute['action'] ?? 'index') . "\n";
        
        // Vérifier que la classe existe
        $controllerClass = $mapRoute['controller'] ?? '';
        if (class_exists($controllerClass)) {
            echo "✅ Classe $controllerClass existe\n";
            
            // Vérifier la méthode
            $method = $mapRoute['action'] ?? 'index';
            if (method_exists($controllerClass, $method)) {
                echo "✅ Méthode $method existe\n";
            } else {
                echo "❌ Méthode $method manquante\n";
            }
        } else {
            echo "❌ Classe $controllerClass manquante\n";
        }
    } else {
        echo "❌ Route /map NON TROUVÉE dans config\n";
    }
} else {
    echo "❌ Fichier config/routes.php manquant\n";
}

echo "\n3. TEST DIRECT INDEX.PHP:\n";

// 3. Simuler $_SERVER pour /map
$_SERVER['REQUEST_URI'] = '/map';
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "URI simulée: /map\n";
echo "Méthode: GET\n";

// Capturer la sortie du point d'entrée
ob_start();
try {
    // On ne peut pas include index.php car il ferait des headers
    // mais on peut tester la logique de base
    
    if (file_exists('public/index.php')) {
        $indexContent = file_get_contents('public/index.php');
        
        if (strpos($indexContent, 'MapController') !== false) {
            echo "✅ index.php fait référence à MapController\n";
        } else {
            echo "⚠️ index.php ne mentionne pas explicitement MapController\n";
        }
        
        if (strpos($indexContent, 'routes.php') !== false) {
            echo "✅ index.php charge les routes\n";
        } else {
            echo "❌ index.php ne charge pas les routes\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur test index.php: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo $output;

echo "\n4. HYPOTHÈSES SUR LE PROBLÈME:\n";
echo "=============================\n";

// 4. Analyser les hypothèses
echo "a) Cache OPCache persistant malgré reset ?\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status && isset($status['opcache_statistics']['num_cached_scripts'])) {
        echo "   OPCache scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "   ⚠️ OPCache encore actif\n";
    }
}

echo "\nb) Template Twig en cache malgré vidage ?\n";
$twigCacheDir = '/home/nibaechl/topoclimb/storage/cache';
if (is_dir($twigCacheDir)) {
    $files = glob($twigCacheDir . '/*');
    echo "   Fichiers cache Twig: " . count($files) . "\n";
    if (count($files) > 0) {
        echo "   ⚠️ Cache Twig pas complètement vidé\n";
    }
}

echo "\nc) Problème au niveau serveur web ?\n";
echo "   Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'inconnu') . "\n";
echo "   Serveur: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu') . "\n";

echo "\nd) Ancienne version en cache navigateur ?\n";
echo "   User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'inconnu') . "\n";
echo "   Si-None-Match: " . ($_SERVER['HTTP_IF_NONE_MATCH'] ?? 'aucun') . "\n";

echo "\n5. ACTION RECOMMANDÉE:\n";
echo "======================\n";
echo "✅ Créer une route de test complètement différente\n";
echo "✅ Comparer /map vs /map-test pour isoler le problème\n";
echo "✅ Vérifier si c'est spécifique à la route /map ou général\n";

?>