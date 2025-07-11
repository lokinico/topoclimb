<?php
// Test complet via l'Application comme sur le serveur web
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST APPLICATION COMPLÈTE ===\n";

// Simuler exactement la requête web
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/map';
$_SERVER['PATH_INFO'] = '/map';
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'topoclimb.ch';

try {
    require_once 'bootstrap.php';
    echo "✅ Bootstrap OK\n";
    
    // Créer l'application exactement comme dans index.php
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    
    $logger = $container->get(\Psr\Log\LoggerInterface::class);
    $router = new \TopoclimbCH\Core\Router($logger, $container);
    
    // Charger les routes
    $routes = require __DIR__ . '/config/routes.php';
    foreach ($routes as $route) {
        $router->addRoute($route['method'], $route['path'], [
            'controller' => $route['controller'],
            'action' => $route['action'],
            'middlewares' => $route['middlewares'] ?? []
        ]);
    }
    
    echo "✅ Router configuré avec " . count($routes) . " routes\n";
    
    // Créer l'application
    $app = new \TopoclimbCH\Core\Application($router, $logger, $container);
    echo "✅ Application créée\n";
    
    // Test handle() qui est appelé dans run()
    echo "🔄 Test handle()...\n";
    
    ob_start();
    $response = $app->handle();
    $output = ob_get_clean();
    
    echo "✅ Handle terminé\n";
    echo "📊 Status: " . $response->getStatusCode() . "\n";
    echo "📊 Content-Length: " . strlen($response->getContent()) . "\n";
    
    if ($output) {
        echo "🔍 Output: $output\n";
    }
    
    if ($response->getStatusCode() !== 200) {
        echo "❌ ERREUR - Contenu:\n";
        echo substr($response->getContent(), 0, 1000) . "\n";
    } else {
        echo "🎉 SUCCESS!\n";
        
        // Vérifier le contenu
        $content = $response->getContent();
        $checks = [
            'sitesData' => strpos($content, 'sitesData') !== false,
            'climbing-map' => strpos($content, 'climbing-map') !== false,
            'Leaflet' => strpos($content, 'leaflet') !== false,
        ];
        
        foreach ($checks as $check => $result) {
            echo ($result ? "✅" : "❌") . " $check: " . ($result ? "OK" : "MANQUANT") . "\n";
        }
    }
    
} catch (\Error $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
} catch (\Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIN TEST ===\n";