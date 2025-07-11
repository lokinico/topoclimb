<?php
/**
 * Test de la gestion d'erreur DB dans MapController
 */

require_once __DIR__ . '/bootstrap.php';

echo "🗺️ Test MapController - Gestion erreur DB\n";
echo "==========================================\n\n";

// Simuler une erreur de base de données en modifiant la config
$_ENV['DB_HOST'] = 'invalid_host';
$_ENV['DB_PORT'] = '9999';

try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    
    // Récupérer le MapController du container
    $mapController = $container->get(\TopoclimbCH\Controllers\MapController::class);
    echo "✅ MapController créé\n";
    
    // Simuler une requête /map
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/map';
    
    echo "🔄 Test avec DB inaccessible...\n";
    $response = $mapController->index();
    
    echo "✅ MapController::index() survit à l'erreur DB\n";
    echo "📊 Status code: " . $response->getStatusCode() . "\n";
    
    $content = $response->getContent();
    echo "📊 Taille du contenu: " . strlen($content) . " caractères\n";
    
    if (strpos($content, 'temporairement inaccessible') !== false) {
        echo "✅ Message d'erreur DB affiché correctement\n";
    } else {
        echo "⚠️ Message d'erreur DB pas trouvé dans le contenu\n";
    }
    
    if (strpos($content, 'Carte Interactive') !== false) {
        echo "✅ Page se charge malgré l'erreur DB\n";
    } else {
        echo "❌ Page ne se charge pas correctement\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur non gérée: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n==========================================\n";
echo "✅ Test terminé\n";