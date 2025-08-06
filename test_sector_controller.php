<?php
// test_sector_controller.php - Test direct du SectorController
require_once __DIR__ . '/bootstrap.php';

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Controllers\SectorController;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\ValidationService;
use TopoclimbCH\Core\Security\CsrfManager;

echo "=== TEST SECTORCONTROLLER - TopoclimbCH ===\n\n";

try {
    
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Base de données connectée\n";
    
    // Instancier les services nécessaires
    $view = new View(__DIR__ . '/resources/views');
    $session = new Session();
    $sectorService = new SectorService($db);
    $mediaService = new MediaService($db);
    $validationService = new ValidationService();
    $csrfManager = new CsrfManager($session);
    
    echo "✅ Services initialisés\n";
    
    // Créer le contrôleur
    $controller = new SectorController(
        $view,
        $session,
        $sectorService,
        $mediaService,
        $validationService,
        $db,
        $csrfManager
    );
    
    echo "✅ SectorController créé\n\n";
    
    // Test 1: Créer une requête pour /sectors
    echo "1. TEST index() avec Request simulée:\n";
    echo "=====================================\n";
    
    $request = Request::create('/sectors', 'GET');
    
    try {
        $response = $controller->index($request);
        
        echo "✅ Controller->index() réussi\n";
        echo "Type de réponse: " . get_class($response) . "\n";
        echo "Status: " . $response->getStatusCode() . "\n";
        
        $content = $response->getContent();
        echo "Taille du contenu: " . strlen($content) . " caractères\n";
        
        // Chercher des indices dans le contenu
        if (strpos($content, 'sectors') !== false) {
            echo "✅ Contenu contient 'sectors'\n";
        }
        
        if (strpos($content, 'error') !== false) {
            echo "⚠️  Contenu contient 'error'\n";
        }
        
        // Afficher un extrait du début et de la fin
        echo "\nExtrait du début (200 premiers caractères):\n";
        echo "---\n";
        echo substr($content, 0, 200) . "...\n";
        echo "---\n";
        
    } catch (\Exception $e) {
        echo "❌ ERREUR Controller->index(): " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";