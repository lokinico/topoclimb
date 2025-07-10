<?php
// Debug script to identify MapController error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUGGING MAP CONTROLLER ERROR ===\n";

// Vérifier que le bootstrap fonctionne
echo "1. Chargement du bootstrap...\n";
try {
    require_once __DIR__ . '/bootstrap.php';
    echo "   ✓ Bootstrap chargé avec succès\n";
} catch (Exception $e) {
    echo "   ✗ Erreur bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier que les classes existent
echo "2. Vérification des classes...\n";
$classes = [
    'TopoclimbCH\Controllers\MapController',
    'TopoclimbCH\Controllers\BaseController',
    'TopoclimbCH\Core\Request',
    'TopoclimbCH\Core\Response',
    'TopoclimbCH\Core\View',
    'TopoclimbCH\Core\Session',
    'TopoclimbCH\Core\Database',
    'TopoclimbCH\Core\Auth',
    'TopoclimbCH\Core\Security\CsrfManager',
    'TopoclimbCH\Models\Region',
    'TopoclimbCH\Models\Site',
    'TopoclimbCH\Models\Sector',
    'TopoclimbCH\Models\Route'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✓ $class existe\n";
    } else {
        echo "   ✗ $class n'existe pas\n";
    }
}

// Test d'instanciation du contrôleur
echo "3. Test d'instanciation...\n";
try {
    $container = TopoclimbCH\Core\Container::getInstance();
    echo "   ✓ Container récupéré\n";
    
    $view = $container->get('view');
    echo "   ✓ View récupérée\n";
    
    $session = $container->get('session');
    echo "   ✓ Session récupérée\n";
    
    $csrfManager = $container->get('csrf');
    echo "   ✓ CSRF Manager récupéré\n";
    
    $db = $container->get('database');
    echo "   ✓ Database récupérée\n";
    
    $auth = $container->get('auth');
    echo "   ✓ Auth récupéré\n";
    
    $controller = new TopoclimbCH\Controllers\MapController($view, $session, $csrfManager, $db, $auth);
    echo "   ✓ MapController instancié avec succès\n";
    
} catch (Exception $e) {
    echo "   ✗ Erreur d'instanciation: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Test d'appel de la méthode index
echo "4. Test d'appel de la méthode index...\n";
try {
    // Simuler une requête
    $request = new TopoclimbCH\Core\Request();
    $response = $controller->index($request);
    echo "   ✓ Méthode index appelée avec succès\n";
    echo "   Response type: " . get_class($response) . "\n";
    echo "   Response status: " . $response->getStatusCode() . "\n";
    
} catch (Exception $e) {
    echo "   ✗ Erreur dans la méthode index: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "=== TOUS LES TESTS RÉUSSIS ===\n";