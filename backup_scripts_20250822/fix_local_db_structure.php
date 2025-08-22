<?php
require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

echo "=== MISE À JOUR STRUCTURE DB LOCALE ===\n\n";

$db = new Database();

try {
    // Vérifier la structure actuelle
    echo "1. Structure actuelle de climbing_sectors:\n";
    $currentColumns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    foreach ($currentColumns as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
    
    // Colonnes manquantes selon STRUCTURE_DB_PRODUCTION.md
    $missingColumns = [
        'book_id' => 'INTEGER DEFAULT NULL',
        'code' => 'VARCHAR(50) NOT NULL DEFAULT ""',
        'access_info' => 'TEXT DEFAULT NULL',
        'color' => 'VARCHAR(20) DEFAULT "#FF0000"',
        'approach' => 'TEXT DEFAULT NULL',
        'height' => 'DECIMAL(6,2) DEFAULT NULL',
        'parking_info' => 'VARCHAR(255) DEFAULT NULL',
        'coordinates_swiss_e' => 'VARCHAR(100) DEFAULT NULL',
        'coordinates_swiss_n' => 'VARCHAR(100) DEFAULT NULL',
        'active' => 'TINYINT(1) DEFAULT 1',
        'created_by' => 'INTEGER DEFAULT NULL',
        'updated_by' => 'INTEGER DEFAULT NULL'
    ];
    
    echo "\n2. Ajout des colonnes manquantes:\n";
    
    foreach ($missingColumns as $column => $definition) {
        // Vérifier si la colonne existe déjà
        $exists = false;
        foreach ($currentColumns as $col) {
            if ($col['name'] === $column) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $sql = "ALTER TABLE climbing_sectors ADD COLUMN {$column} {$definition}";
            echo "  Ajout: {$column} -> ";
            
            try {
                $db->exec($sql);
                echo "✅ OK\n";
            } catch (Exception $e) {
                echo "❌ ERREUR: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  {$column} -> ✅ Existe déjà\n";
        }
    }
    
    // Ajouter des données de test
    echo "\n3. Ajout de secteurs de test:\n";
    
    // Vérifier s'il y a déjà des secteurs
    $count = $db->fetchOne("SELECT COUNT(*) as c FROM climbing_sectors")['c'];
    
    if ($count < 4) {
        // Ajouter quelques secteurs de test
        $testSectors = [
            [
                'name' => 'Secteur Sud',
                'code' => 'SUD',
                'description' => 'Secteur exposé sud avec excellentes voies',
                'region_id' => 1,
                'altitude' => 1200,
                'active' => 1
            ],
            [
                'name' => 'Secteur Nord',
                'code' => 'NORD',
                'description' => 'Secteur ombragé pour l\'été',
                'region_id' => 1,
                'altitude' => 1150,
                'active' => 1
            ],
            [
                'name' => 'Secteur Est',
                'code' => 'EST',
                'description' => 'Secteur matinal avec belles dalles',
                'region_id' => 1,
                'altitude' => 1100,
                'active' => 1
            ],
            [
                'name' => 'Secteur Ouest',
                'code' => 'OUEST',
                'description' => 'Secteur du soir avec surplombs',
                'region_id' => 1,
                'altitude' => 1250,
                'active' => 1
            ]
        ];
        
        foreach ($testSectors as $sector) {
            $sql = "INSERT INTO climbing_sectors (name, code, description, region_id, altitude, active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
            
            try {
                $db->execute($sql, [
                    $sector['name'],
                    $sector['code'],
                    $sector['description'],
                    $sector['region_id'],
                    $sector['altitude'],
                    $sector['active']
                ]);
                echo "  ✅ Ajouté: {$sector['name']}\n";
            } catch (Exception $e) {
                echo "  ❌ Erreur pour {$sector['name']}: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "  ✅ {$count} secteurs déjà présents\n";
    }
    
    // Vérifier la structure finale
    echo "\n4. Structure finale:\n";
    $finalColumns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    echo "Colonnes total: " . count($finalColumns) . "\n";
    
    // Test SectorService
    echo "\n5. Test SectorService:\n";
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
    echo "  Secteurs retournés: " . count($items) . "\n";
    
    foreach ($items as $sector) {
        echo "  - {$sector['name']} (code: {$sector['code']}, active: " . ($sector['active'] ?? 'NULL') . ")\n";
    }
    
    echo "\n✅ STRUCTURE LOCALE MISE À JOUR AVEC SUCCÈS !\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}