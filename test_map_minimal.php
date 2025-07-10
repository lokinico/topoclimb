<?php
// Test minimal pour identifier le problème exact du MapController

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST MINIMAL MAP CONTROLLER ===\n";

require_once __DIR__ . '/bootstrap.php';
require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

try {
    echo "1. Test de Database::getInstance()...\n";
    $db = \TopoclimbCH\Core\Database::getInstance();
    echo "   ✓ Database::getInstance() OK\n";
    
    echo "2. Test de getConnection()...\n";
    $connection = $db->getConnection();
    echo "   ✓ getConnection() OK, type: " . get_class($connection) . "\n";
    
    echo "3. Test des modèles...\n";
    
    echo "   Test Region::all()...\n";
    $regions = \TopoclimbCH\Models\Region::all();
    echo "   ✓ Region::all() OK, nombre: " . count($regions) . "\n";
    
    echo "   Test Site::all()...\n";
    $sites = \TopoclimbCH\Models\Site::all();
    echo "   ✓ Site::all() OK, nombre: " . count($sites) . "\n";
    
    echo "   Test Sector::all()...\n";
    $sectors = \TopoclimbCH\Models\Sector::all();
    echo "   ✓ Sector::all() OK, nombre: " . count($sectors) . "\n";
    
    echo "   Test Route::all()...\n";
    $routes = \TopoclimbCH\Models\Route::all();
    echo "   ✓ Route::all() OK, nombre: " . count($routes) . "\n";
    
    echo "4. Test des services...\n";
    $session = new \TopoclimbCH\Core\Session();
    $view = new \TopoclimbCH\Core\View(BASE_PATH . '/resources/views');
    $csrfManager = new \TopoclimbCH\Core\Security\CsrfManager($session);
    $auth = new \TopoclimbCH\Core\Auth($db);
    echo "   ✓ Services créés\n";
    
    echo "5. Test MapController...\n";
    $mapController = new \TopoclimbCH\Controllers\MapController(
        $view, $session, $csrfManager, $db, $auth
    );
    echo "   ✓ MapController créé\n";
    
    echo "6. Test des méthodes privées...\n";
    // On va utiliser la réflexion pour tester les méthodes privées
    $reflection = new ReflectionClass($mapController);
    
    $getSitesMethod = $reflection->getMethod('getSitesForMap');
    $getSitesMethod->setAccessible(true);
    
    $filters = [];
    $sitesForMap = $getSitesMethod->invoke($mapController, $filters);
    echo "   ✓ getSitesForMap() OK, nombre: " . count($sitesForMap) . "\n";
    
    $getStatsMethod = $reflection->getMethod('getMapStatistics');
    $getStatsMethod->setAccessible(true);
    
    $stats = $getStatsMethod->invoke($mapController);
    echo "   ✓ getMapStatistics() OK\n";
    echo "     - Sites: " . $stats['total_sites'] . "\n";
    echo "     - Routes: " . $stats['total_routes'] . "\n";
    echo "     - Regions: " . $stats['total_regions'] . "\n";
    
    echo "=== TOUS LES TESTS RÉUSSIS - Le problème n'est pas dans la logique métier ===\n";
    echo "Le problème doit être dans le rendu du template ou la configuration du serveur web.\n";
    
} catch (\Exception $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " (ligne " . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}