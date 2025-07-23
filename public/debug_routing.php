<?php
// Diagnostic complet du système de routing en production
header('Content-Type: text/plain');

echo "🔍 DIAGNOSTIC ROUTING PRODUCTION - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 70) . "\n\n";

try {
    // Charger le bootstrap
    require_once dirname(__DIR__) . '/bootstrap.php';
    
    echo "📍 Environment: " . ($_ENV['APP_ENV'] ?? 'undefined') . "\n";
    echo "📍 Base Path: " . BASE_PATH . "\n\n";
    
    // Vérifier le cache des routes
    $cacheFile = BASE_PATH . '/cache/routes/routes.php';
    echo "🗂️ CACHE DES ROUTES:\n";
    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        echo "   ✅ Cache existe: " . date('Y-m-d H:i:s', $cacheTime) . "\n";
        
        // Charger le cache et chercher /map-new
        $cachedRoutes = require $cacheFile;
        $mapNewFound = false;
        
        foreach ($cachedRoutes as $method => $routes) {
            foreach ($routes as $pattern => $route) {
                if (isset($route['path']) && $route['path'] === '/map-new') {
                    echo "   ✅ /map-new trouvé dans cache ($method): $pattern\n";
                    $mapNewFound = true;
                }
            }
        }
        
        if (!$mapNewFound) {
            echo "   ❌ /map-new ABSENT du cache (PROBLÈME IDENTIFIÉ !)\n";
        }
        
        echo "   📊 Routes en cache: " . array_sum(array_map('count', $cachedRoutes)) . "\n";
    } else {
        echo "   ❌ Cache n'existe pas\n";
    }
    
    // Vérifier les routes source
    echo "\n📋 ROUTES SOURCE:\n";
    $sourceRoutes = require BASE_PATH . '/config/routes.php';
    echo "   📊 Routes source: " . count($sourceRoutes) . "\n";
    
    $mapNewInSource = false;
    foreach ($sourceRoutes as $index => $route) {
        if (isset($route['path']) && $route['path'] === '/map-new') {
            echo "   ✅ /map-new trouvé dans source (index $index)\n";
            echo "      Controller: " . ($route['controller'] ?? 'undefined') . "\n";
            echo "      Action: " . ($route['action'] ?? 'undefined') . "\n";
            echo "      Method: " . ($route['method'] ?? 'undefined') . "\n";
            $mapNewInSource = true;
        }
    }
    
    if (!$mapNewInSource) {
        echo "   ❌ /map-new ABSENT des routes source\n";
    }
    
    // Test du router
    echo "\n🔧 TEST ROUTER:\n";
    require BASE_PATH . '/vendor/autoload.php';
    
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $router = $container->get(\TopoclimbCH\Core\Router::class);
    
    // Charger les routes (utilisera le cache en production)
    $router->loadRoutes(BASE_PATH . '/config/routes.php');
    echo "   ✅ Router initialisé et routes chargées\n";
    
    // Tester la résolution
    try {
        $route = $router->resolve('GET', '/map-new');
        echo "   ✅ Route /map-new résolue:\n";
        echo "      Handler: " . json_encode($route['handler'] ?? [], JSON_PRETTY_PRINT) . "\n";
    } catch (\TopoclimbCH\Exceptions\RouteNotFoundException $e) {
        echo "   ❌ Route /map-new NON RÉSOLUE: " . $e->getMessage() . "\n";
    }
    
    // Tester /map pour comparaison
    try {
        $route = $router->resolve('GET', '/map');
        echo "   ✅ Route /map résolue (pour comparaison)\n";
    } catch (\TopoclimbCH\Exceptions\RouteNotFoundException $e) {
        echo "   ❌ Route /map non résolue: " . $e->getMessage() . "\n";
    }
    
    echo "\n📁 VÉRIFICATIONS FICHIERS:\n";
    $controllerFile = BASE_PATH . '/src/Controllers/MapNewController.php';
    if (file_exists($controllerFile)) {
        echo "   ✅ MapNewController.php existe\n";
    } else {
        echo "   ❌ MapNewController.php manquant\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    
    if (file_exists($cacheFile) && !$mapNewFound && $mapNewInSource) {
        echo "🚨 PROBLÈME IDENTIFIÉ: Cache obsolète !\n";
        echo "💡 SOLUTION: Supprimer le cache avec /clear_route_cache.php\n";
    } elseif (!$mapNewInSource) {
        echo "🚨 PROBLÈME: Route manquante dans les sources\n";
    } else {
        echo "✅ Configuration semble correcte\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}