<?php
// test_sector_service.php - Test direct du SectorService
echo "=== TEST SECTORSERVICE - TopoclimbCH ===\n\n";

try {
    require_once __DIR__ . '/bootstrap.php';
    
    $db = TopoclimbCH\Core\Database::getInstance();
    $sectorService = new TopoclimbCH\Services\SectorService($db);
    
    echo "✅ SectorService initialisé\n\n";

    // Test 1: getPaginatedSectors
    echo "1. TEST getPaginatedSectors():\n";
    echo "===============================\n";
    
    try {
        $result = $sectorService->getPaginatedSectors(null);
        
        echo "✅ getPaginatedSectors() réussi\n";
        echo "Type retourné: " . get_class($result) . "\n";
        
        if (method_exists($result, 'getItems')) {
            $items = $result->getItems();
            echo "Nombre de secteurs: " . count($items) . "\n\n";
            
            if (!empty($items)) {
                echo "Premier secteur:\n";
                print_r($items[0]);
            }
        }
        
    } catch (\Exception $e) {
        echo "❌ ERREUR getPaginatedSectors: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n\n";
    }

    // Test 2: getSectorById
    echo "2. TEST getSectorById(1):\n";
    echo "==========================\n";
    
    try {
        $sector = $sectorService->getSectorById(1);
        
        if ($sector) {
            echo "✅ Secteur trouvé:\n";
            echo "ID: " . $sector['id'] . "\n";
            echo "Nom: " . $sector['name'] . "\n";
            echo "Code: " . ($sector['code'] ?? 'N/A') . "\n";
            echo "Région: " . ($sector['region_name'] ?? 'N/A') . "\n";
        } else {
            echo "❌ Aucun secteur trouvé avec l'ID 1\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ ERREUR getSectorById: " . $e->getMessage() . "\n";
    }
    
    // Test 3: Requête directe pour comparaison
    echo "\n3. TEST REQUÊTE DIRECTE:\n";
    echo "=========================\n";
    
    try {
        $sectors = $db->fetchAll("SELECT id, name, code FROM climbing_sectors WHERE active = 1 LIMIT 5");
        
        echo "✅ Requête directe réussie - " . count($sectors) . " secteurs\n";
        
        foreach ($sectors as $sector) {
            echo sprintf("  #%-2d | %-20s | %s\n", 
                $sector['id'], 
                substr($sector['name'], 0, 20), 
                $sector['code'] ?? 'N/A'
            );
        }
        
    } catch (\Exception $e) {
        echo "❌ ERREUR requête directe: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";