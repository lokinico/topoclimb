<?php

/**
 * Script pour vérifier exactement quelle route est matchée pour les paths /create
 */

echo "🔍 DEBUG EXACT MATCH ROUTES CREATE\n";
echo "=" . str_repeat("=", 45) . "\n";

// Charger les routes
$routes = require __DIR__ . '/config/routes.php';

echo "📋 Simulation matching pour les routes create:\n\n";

function findMatchingRoute($routes, $method, $path) {
    echo "🎯 Recherche route pour: $method $path\n";
    
    $matches = [];
    
    foreach ($routes as $index => $route) {
        if ($route['method'] !== $method) {
            continue;
        }
        
        $routePath = $route['path'];
        
        // Conversion du pattern de route en regex (simplifiée)
        $pattern = $routePath;
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        if (preg_match($pattern, $path, $pathMatches)) {
            $matches[] = [
                'index' => $index,
                'route' => $route,
                'matches' => $pathMatches
            ];
            
            echo "   ✅ MATCH #" . ($index + 1) . ": {$route['path']}\n";
            echo "      Controller: " . basename(str_replace('\\', '/', $route['controller'])) . "\n";
            echo "      Action: {$route['action']}\n";
            echo "      Pattern: $pattern\n";
            if (!empty($pathMatches)) {
                $namedMatches = array_filter($pathMatches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                if (!empty($namedMatches)) {
                    echo "      Params: " . json_encode($namedMatches) . "\n";
                }
            }
            echo "\n";
        }
    }
    
    if (empty($matches)) {
        echo "   ❌ AUCUN MATCH TROUVÉ\n\n";
    } else {
        echo "   📊 " . count($matches) . " route(s) matchée(s)\n";
        if (count($matches) > 1) {
            echo "   ⚠️  ATTENTION: Plusieurs routes matchent! La première sera utilisée.\n";
        }
        echo "\n";
    }
    
    return $matches;
}

// Test des routes problématiques
$testPaths = [
    'GET /routes/create',
    'GET /sectors/create',
    'GET /sites/create',
    'GET /books/create'
];

foreach ($testPaths as $test) {
    list($method, $path) = explode(' ', $test, 2);
    findMatchingRoute($routes, $method, $path);
    echo str_repeat('-', 50) . "\n";
}

echo "💡 ANALYSE:\n";
echo "1. Si une route matche correctement et pointe vers la bonne action create(),\n";
echo "   alors le problème est dans le contrôleur ou après.\n";
echo "2. Si aucune route ne matche, c'est un problème de configuration.\n";
echo "3. Si plusieurs routes matchent, il y a conflit et la première est prise.\n";