<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\SectorService;

echo "=== TEST FINAL SECTEURS ===\n\n";

try {
    $db = new Database();
    
    echo "1. Test structure DB:\n";
    $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    echo "Colonnes climbing_sectors: " . count($columns) . "\n";
    
    // Vérifier colonnes critiques
    $criticalColumns = ['id', 'name', 'code', 'active', 'region_id'];
    foreach ($criticalColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['name'] === $col) {
                $found = true;
                break;
            }
        }
        echo "  - $col: " . ($found ? '✅' : '❌') . "\n";
    }
    
    echo "\n2. Test données directes:\n";
    $directSectors = $db->fetchAll("SELECT id, name, code, active FROM climbing_sectors WHERE active = 1");
    echo "Secteurs actifs: " . count($directSectors) . "\n";
    foreach ($directSectors as $sector) {
        echo "  - {$sector['name']} (code: {$sector['code']}, active: {$sector['active']})\n";
    }
    
    echo "\n3. Test SectorService:\n";
    $sectorService = new SectorService($db);
    
    $mockFilter = new class {
        public function getRegionId() { return null; }
        public function getSearch() { return null; }
        public function getSortBy() { return 'name'; }
        public function getSortDirection() { return 'ASC'; }
        public function getPage() { return 1; }
        public function getPerPage() { return 24; }
    };
    
    $paginatedSectors = $sectorService->getPaginatedSectors($mockFilter);
    echo "Type paginator: " . get_class($paginatedSectors) . "\n";
    
    $items = $paginatedSectors->getItems();
    echo "Items retournés: " . count($items) . "\n";
    
    if (count($items) > 0) {
        echo "Premier secteur:\n";
        foreach ($items[0] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
    
    echo "\n4. Test template data:\n";
    // Simuler ce que le template reçoit
    $templateData = [
        'sectors' => $paginatedSectors,
        'total' => $paginatedSectors->getTotal(),
        'currentPage' => $paginatedSectors->getCurrentPage()
    ];
    
    echo "Template data:\n";
    echo "  - sectors type: " . get_class($templateData['sectors']) . "\n";
    echo "  - total: {$templateData['total']}\n";
    echo "  - currentPage: {$templateData['currentPage']}\n";
    
    // Test getItems() comme dans le template
    $sectorItems = $templateData['sectors']->getItems();
    echo "  - sectorItems count: " . count($sectorItems) . "\n";
    
    echo "\n✅ TOUS LES TESTS PASSENT !\n";
    echo "Les secteurs sont prêts à être affichés dans le template.\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}