<?php

/**
 * Script pour identifier EXACTEMENT où la redirection se produit dans le processus de routage
 */

require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Container;
use TopoclimbCH\Core\Logger;
use Symfony\Component\HttpFoundation\Request;

echo "🔍 DIAGNOSTIC EXACT DU ROUTAGE - REDIRECTION CREATE\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    // Initialiser les dépendances
    $container = Container::getInstance();
    $logger = new Logger();
    $router = new Router($logger, $container);
    
    // Charger les routes depuis le fichier config
    echo "📋 Chargement des routes...\n";
    $routes = require __DIR__ . '/config/routes.php';
    
    foreach ($routes as $route) {
        $router->add(
            $route['method'],
            $route['path'],
            [
                'controller' => $route['controller'],
                'action' => $route['action'],
                'middlewares' => $route['middlewares'] ?? []
            ]
        );
    }
    
    echo "✅ Routes chargées\n\n";
    
    // Test de résolution des routes create
    $testRoutes = [
        '/routes/create',
        '/sectors/create', 
        '/sites/create',
        '/books/create'
    ];
    
    echo "🧪 Test de résolution des routes:\n\n";
    
    foreach ($testRoutes as $path) {
        echo "🔍 Test route: $path\n";
        
        try {
            $resolvedRoute = $router->resolve('GET', $path);
            
            echo "   ✅ Route résolue:\n";
            echo "      - Controller: " . $resolvedRoute['handler']['controller'] . "\n";
            echo "      - Action: " . $resolvedRoute['handler']['action'] . "\n";
            echo "      - Middlewares: " . implode(', ', $resolvedRoute['handler']['middlewares'] ?? []) . "\n";
            echo "      - Params: " . json_encode($resolvedRoute['params']) . "\n";
            
        } catch (Exception $e) {
            echo "   ❌ Erreur résolution: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    echo "🎯 Test simulation d'une requête complète:\n";
    
    // Créer une fausse requête HTTP pour /routes/create
    $request = Request::create('/routes/create', 'GET');
    $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session());
    
    echo "📋 Requête simulée: GET /routes/create\n";
    echo "   Path Info: " . $request->getPathInfo() . "\n";
    echo "   Method: " . $request->getMethod() . "\n";
    
    // Test step-by-step du dispatch
    echo "\n📊 Étape 1: Résolution de la route\n";
    try {
        $route = $router->resolve($request->getMethod(), $request->getPathInfo());
        echo "   ✅ Route trouvée: " . $route['handler']['controller'] . "::" . $route['handler']['action'] . "\n";
        
        echo "\n📊 Étape 2: Vérification du contrôleur\n";
        $controllerClass = $route['handler']['controller'];
        
        if (class_exists($controllerClass)) {
            echo "   ✅ Classe contrôleur existe: $controllerClass\n";
            
            $reflection = new ReflectionClass($controllerClass);
            $action = $route['handler']['action'];
            
            if ($reflection->hasMethod($action)) {
                echo "   ✅ Méthode existe: $action()\n";
                
                // Vérifier les paramètres de la méthode
                $method = $reflection->getMethod($action);
                $params = $method->getParameters();
                
                echo "   📋 Paramètres de la méthode:\n";
                foreach ($params as $param) {
                    echo "      - " . $param->getName() . " (" . ($param->getType() ? $param->getType()->getName() : 'mixed') . ")\n";
                }
                
            } else {
                echo "   ❌ Méthode manquante: $action()\n";
                echo "   📋 Méthodes disponibles: " . implode(', ', get_class_methods($controllerClass)) . "\n";
            }
            
        } else {
            echo "   ❌ Classe contrôleur manquante: $controllerClass\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n💡 HYPOTHÈSES SUR LA REDIRECTION:\n";
    echo "Si la route est bien résolue mais qu'il y a quand même redirection, alors:\n";
    echo "1. 🔧 Un middleware intercepte et redirige AVANT le contrôleur\n";
    echo "2. 🔧 Le contrôleur lui-même fait une redirection immédiate\n";  
    echo "3. 🔧 Une exception dans le contrôleur cause un fallback\n";
    echo "4. 🔧 Un autre système (htaccess, nginx) fait la redirection\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GÉNÉRALE: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎯 DIAGNOSTIC TERMINÉ\n";