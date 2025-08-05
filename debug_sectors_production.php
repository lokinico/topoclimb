<?php
// Utiliser EXACTEMENT la même configuration que l'application

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\SectorService;

echo "=== DEBUG SECTORS PRODUCTION ===\n\n";

try {
    // 1. Test Database TopoclimbCH
    echo "1. Test connexion Database...\n";
    $db = new Database();
    echo "✅ Database connectée\n";
    
    // 2. Test comptage direct
    echo "\n2. Test comptage secteurs...\n";
    $totalSectors = $db->fetchOne("SELECT COUNT(*) as total FROM climbing_sectors");
    echo "Total secteurs: " . $totalSectors['total'] . "\n";
    
    $activeSectors = $db->fetchOne("SELECT COUNT(*) as total FROM climbing_sectors WHERE active = 1");
    echo "Secteurs actifs: " . $activeSectors['total'] . "\n";
    
    if ($activeSectors['total'] == 0) {
        echo "❌ PROBLÈME: Aucun secteur actif\!\n";
        
        // Voir secteurs inactifs
        $inactiveSectors = $db->fetchAll("SELECT id, name, active FROM climbing_sectors WHERE active \!= 1 LIMIT 5");
        echo "Secteurs inactifs trouvés:\n";
        foreach ($inactiveSectors as $sector) {
            echo "  - ID {$sector['id']}: {$sector['name']} (active={$sector['active']})\n";
        }
        exit;
    }
    
    // 3. Test requête simple
    echo "\n3. Test requête simple...\n";
    $simpleQuery = "SELECT id, name, active FROM climbing_sectors WHERE active = 1 ORDER BY name ASC LIMIT 5";
    $simpleSectors = $db->fetchAll($simpleQuery);
    echo "Secteurs simples trouvés: " . count($simpleSectors) . "\n";
    
    foreach ($simpleSectors as $sector) {
        echo "  - ID {$sector['id']}: {$sector['name']}\n";
    }
    
    // 4. Test requête avec JOIN
    echo "\n4. Test requête avec JOIN...\n";
    $joinQuery = "
        SELECT 
            s.id, 
            s.name, 
            s.region_id,
            r.name as region_name
        FROM climbing_sectors s 
        LEFT JOIN climbing_regions r ON s.region_id = r.id 
        WHERE s.active = 1
        ORDER BY s.name ASC
        LIMIT 5
    ";
    
    $joinSectors = $db->fetchAll($joinQuery);
    echo "Secteurs avec JOIN trouvés: " . count($joinSectors) . "\n";
    
    foreach ($joinSectors as $sector) {
        echo "  - ID {$sector['id']}: {$sector['name']} (région: " . ($sector['region_name'] ?? 'NULL') . ")\n";
    }
    
    // 5. Test SectorService
    echo "\n5. Test SectorService...\n";
    $sectorService = new SectorService($db);
    
    // Créer un filter mock simple
    $mockFilter = new class {
        public function getRegionId() { return null; }
        public function getSearch() { return null; }
        public function getSortBy() { return 'name'; }
        public function getSortDirection() { return 'ASC'; }
        public function getPage() { return 1; }
        public function getPerPage() { return 24; }
    };
    
    $paginatedSectors = $sectorService->getPaginatedSectors($mockFilter);
    echo "SectorService - Type: " . get_class($paginatedSectors) . "\n";
    
    if (method_exists($paginatedSectors, 'getItems')) {
        $items = $paginatedSectors->getItems();
        echo "Items via getItems(): " . count($items) . "\n";
    } else {
        echo "Pas de méthode getItems(), c'est un SimplePaginator\n";
        // SimplePaginator stocke directement les données
        $reflection = new ReflectionClass($paginatedSectors);
        if ($reflection->hasProperty('items')) {
            $itemsProperty = $reflection->getProperty('items');
            $itemsProperty->setAccessible(true);
            $items = $itemsProperty->getValue($paginatedSectors);
            echo "Items via reflection: " . count($items) . "\n";
            
            if (count($items) > 0) {
                echo "Premier secteur:\n";
                print_r($items[0]);
            }
        }
    }
    
    echo "\n✅ DIAGNOSTIC TERMINÉ\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
EOF < /dev/null
