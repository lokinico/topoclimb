<?php
// test_sectors_bypass_auth.php - Test secteurs sans authentification
require_once __DIR__ . '/bootstrap.php';

echo "=== TEST SECTEURS SANS AUTH - TopoclimbCH ===\n\n";

try {
    $db = TopoclimbCH\Core\Database::getInstance();
    $sectorService = new TopoclimbCH\Services\SectorService($db);
    
    echo "✅ Services initialisés\n\n";
    
    // Test 1: SectorService getPaginatedSectors directement
    echo "1. TEST SectorService->getPaginatedSectors():\n";
    echo "===============================================\n";
    
    $sectors = $sectorService->getPaginatedSectors(null);
    
    echo "✅ Secteurs récupérés\n";
    echo "Type: " . get_class($sectors) . "\n";
    
    if (method_exists($sectors, 'getItems')) {
        $items = $sectors->getItems();
        echo "Nombre de secteurs: " . count($items) . "\n";
        
        foreach ($items as $i => $sector) {
            echo sprintf("  #%d - %s (%s) - %s voies\n", 
                $sector['id'],
                $sector['name'], 
                $sector['code'] ?? 'N/A',
                $sector['routes_count'] ?? '0'
            );
        }
    }
    
    // Test 2: Simulation de la vue sans contrôleur
    echo "\n2. TEST SIMULATION VUE:\n";
    echo "========================\n";
    
    $templateData = [
        'sectors' => $sectors,
        'filter' => null,
        'regions' => [],
        'sites' => [],
        'exposures' => [],
        'months' => [],
        'currentUrl' => '/sectors',
        'sort_by' => 'name',
        'sort_dir' => 'ASC'
    ];
    
    echo "✅ Template data préparée\n";
    echo "Variables disponibles pour la vue:\n";
    foreach ($templateData as $key => $value) {
        $type = is_object($value) ? get_class($value) : gettype($value);
        $count = is_array($value) ? count($value) : (is_object($value) && method_exists($value, 'getItems') ? count($value->getItems()) : 'N/A');
        echo "  - $key: $type ($count éléments)\n";
    }
    
    // Test 3: Vérifier les fichiers template
    echo "\n3. TEST FICHIERS TEMPLATE:\n";  
    echo "===========================\n";
    
    $templatePath = __DIR__ . '/resources/views/sectors';
    if (is_dir($templatePath)) {
        echo "✅ Dossier template sectors existe\n";
        
        $files = scandir($templatePath);
        echo "Fichiers template disponibles:\n";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - $file\n";
            }
        }
    } else {
        echo "❌ Dossier template sectors manquant: $templatePath\n";
    }

} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";