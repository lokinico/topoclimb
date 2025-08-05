<?php
require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

echo "=== MISE À JOUR DONNÉES DE TEST ===\n\n";

$db = new Database();

try {
    // Mettre à jour les secteurs existants avec des codes et active=1
    $updates = [
        ['name' => 'Secteur Sud', 'code' => 'SUD', 'active' => 1],
        ['name' => 'Secteur Nord', 'code' => 'NORD', 'active' => 1],
        ['name' => 'Secteur Est', 'code' => 'EST', 'active' => 1],
        ['name' => 'Secteur Ouest', 'code' => 'OUEST', 'active' => 1]
    ];
    
    foreach ($updates as $update) {
        $sql = "UPDATE climbing_sectors SET code = ?, active = ? WHERE name = ?";
        $db->query($sql, [$update['code'], $update['active'], $update['name']]);
        echo "✅ Mis à jour: {$update['name']} -> code={$update['code']}, active={$update['active']}\n";
    }
    
    // Test SectorService
    echo "\n=== TEST FINAL ===\n";
    $sectorService = new \TopoclimbCH\Services\SectorService($db);
    
    $mockFilter = new class {
        public function getRegionId() { return null; }
        public function getSearch() { return null; }
        public function getSortBy() { return 'name'; }
        public function getSortDirection() { return 'ASC'; }
        public function getPage() { return 1; }
        public function getPerPage() { return 24; }
    };
    
    $paginatedSectors = $sectorService->getPaginatedSectors($mockFilter);
    $items = $paginatedSectors->getItems();
    
    echo "Secteurs retournés: " . count($items) . "\n";
    foreach ($items as $sector) {
        echo "  - {$sector['name']} (code: {$sector['code']}, active: {$sector['active']})\n";
    }
    
    echo "\n✅ DONNÉES MISES À JOUR AVEC SUCCÈS !\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}