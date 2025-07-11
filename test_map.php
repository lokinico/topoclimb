<?php
/**
 * Test spécifique pour la page /map
 */

require_once __DIR__ . '/bootstrap.php';

echo "🗺️ Test MapController spécifique\n";
echo "================================\n\n";

// Test 1: Vérification des modèles
echo "📝 Test 1: Modèles de base\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    
    // Injecter la DB dans les modèles manuellement
    \TopoclimbCH\Models\Region::setDatabase($db);
    \TopoclimbCH\Models\Site::setDatabase($db);
    \TopoclimbCH\Models\Sector::setDatabase($db);
    \TopoclimbCH\Models\Route::setDatabase($db);
    
    echo "✅ Modèles configurés avec DB\n";
    
    // Test de base avec les modèles
    $regions = \TopoclimbCH\Models\Region::all();
    echo "📊 Régions trouvées: " . count($regions) . "\n";
    
    $sites = \TopoclimbCH\Models\Site::all();
    echo "📊 Sites trouvés: " . count($sites) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur modèles: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test du MapController
echo "📝 Test 2: MapController\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    
    // Récupérer le MapController du container
    $mapController = $container->get(\TopoclimbCH\Controllers\MapController::class);
    echo "✅ MapController créé par le container\n";
    
    // Simuler une requête /map
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/map';
    
    echo "🔄 Appel de MapController::index()...\n";
    $response = $mapController->index();
    
    echo "✅ MapController::index() exécuté\n";
    echo "📊 Type de réponse: " . get_class($response) . "\n";
    
    if (method_exists($response, 'getStatusCode')) {
        echo "📊 Status code: " . $response->getStatusCode() . "\n";
    }
    
    if (method_exists($response, 'getContent')) {
        $content = $response->getContent();
        echo "📊 Taille du contenu: " . strlen($content) . " caractères\n";
        
        if (strpos($content, 'Carte Interactive') !== false) {
            echo "✅ Contenu semble correct (titre trouvé)\n";
        } else {
            echo "⚠️ Contenu suspect (titre non trouvé)\n";
            echo "Début du contenu: " . substr($content, 0, 200) . "...\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur MapController: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "🔍 Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 3: Test du template map/index
echo "📝 Test 3: Template map/index\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $view = $container->get(\TopoclimbCH\Core\View::class);
    
    // Test template avec données minimales
    $html = $view->render('map/index', [
        'title' => 'Test Carte',
        'sites' => [],
        'regions' => [],
        'filters' => [],
        'stats' => ['total_sites' => 0, 'total_routes' => 0, 'total_regions' => 0]
    ]);
    
    if (strlen($html) > 200) {
        echo "✅ Template map/index fonctionne\n";
        echo "📊 Taille HTML: " . strlen($html) . " caractères\n";
    } else {
        echo "❌ Template map/index trop court\n";
        echo "Contenu: " . $html . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur template: " . $e->getMessage() . "\n";
}

echo "\n================================\n";
echo "✅ Test MapController terminé\n";