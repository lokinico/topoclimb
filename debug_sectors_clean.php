<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\SectorService;

echo "=== DEBUG SECTORS PRODUCTION ===\n\n";

try {
    // 1. Test Database
    echo "1. Test connexion Database...\n";
    $db = new Database();
    echo "✅ Database connectée\n";
    
    // 2. Test comptage direct
    echo "\n2. Test comptage secteurs...\n";
    $totalSectors = $db->fetchOne("SELECT COUNT(*) as total FROM climbing_sectors");
    echo "Total secteurs: " . $totalSectors['total'] . "\n";
    
    // Note: colonne 'active' n'existe pas, tous les secteurs sont considérés actifs
    echo "Tous les secteurs sont actifs (pas de colonne 'active')\n";
    
    // 3. Test requête simple
    echo "\n3. Test requête simple...\n";
    $simpleQuery = "SELECT id, name FROM climbing_sectors ORDER BY name ASC LIMIT 5";
    $simpleSectors = $db->fetchAll($simpleQuery);
    echo "Secteurs simples trouvés: " . count($simpleSectors) . "\n";
    
    foreach ($simpleSectors as $sector) {
        echo "  - ID {$sector['id']}: {$sector['name']}\n";
    }
    
    // 4. Test requête avec JOIN - exactement celle de SectorService
    echo "\n4. Test requête JOIN SectorService...\n";
    $joinQuery = "
        SELECT 
            s.id, 
            s.name, 
            s.region_id,
            r.name as region_name,
            s.description,
            (SELECT COUNT(*) FROM climbing_routes WHERE sector_id = s.id) as routes_count
        FROM climbing_sectors s 
        LEFT JOIN climbing_regions r ON s.region_id = r.id
        ORDER BY s.name ASC
        LIMIT 50
    ";
    
    $joinSectors = $db->fetchAll($joinQuery);
    echo "Secteurs avec JOIN trouvés: " . count($joinSectors) . "\n";
    
    if (count($joinSectors) > 0) {
        echo "Premier secteur complet:\n";
        print_r($joinSectors[0]);
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
        
        if (count($items) > 0) {
            echo "Premier secteur SectorService:\n";
            print_r($items[0]);
        }
    }
    
    echo "\n✅ DIAGNOSTIC TERMINÉ\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}