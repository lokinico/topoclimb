<?php
// sync_db_structure.php - Synchronise la DB locale avec la structure production
require_once __DIR__ . '/bootstrap.php';

echo "=== SYNCHRONISATION DB LOCALE AVEC PRODUCTION ===\n\n";

try {
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Base de données connectée\n";
    
    // 1. Mettre à jour climbing_sectors selon STRUCTURE_DB_PRODUCTION.md
    echo "\n1. MISE À JOUR TABLE climbing_sectors:\n";
    echo "=====================================\n";
    
    // Vérifier colonnes existantes
    $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    $existingColumns = array_column($columns, 'name');
    
    echo "Colonnes actuelles: " . implode(', ', $existingColumns) . "\n\n";
    
    // Colonnes de production selon STRUCTURE_DB_PRODUCTION.md
    $productionColumns = [
        'id' => 'INTEGER PRIMARY KEY',
        'book_id' => 'INTEGER DEFAULT NULL',
        'region_id' => 'INTEGER DEFAULT NULL', 
        'site_id' => 'INTEGER DEFAULT NULL',
        'name' => 'VARCHAR(255) NOT NULL',
        'code' => 'VARCHAR(50) NOT NULL DEFAULT ""',
        'description' => 'TEXT DEFAULT NULL',
        'access_info' => 'TEXT DEFAULT NULL',
        'color' => 'VARCHAR(20) DEFAULT "#FF0000"',
        'access_time' => 'INTEGER DEFAULT NULL',
        'altitude' => 'INTEGER DEFAULT NULL',
        'approach' => 'TEXT DEFAULT NULL',
        'height' => 'DECIMAL(6,2) DEFAULT NULL',
        'parking_info' => 'VARCHAR(255) DEFAULT NULL',
        'coordinates_lat' => 'DECIMAL(10,8) DEFAULT NULL',
        'coordinates_lng' => 'DECIMAL(11,8) DEFAULT NULL',
        'coordinates_swiss_e' => 'VARCHAR(100) DEFAULT NULL',
        'coordinates_swiss_n' => 'VARCHAR(100) DEFAULT NULL',
        'active' => 'INTEGER DEFAULT 1',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'created_by' => 'INTEGER DEFAULT NULL',
        'updated_by' => 'INTEGER DEFAULT NULL'
    ];
    
    // Ajouter colonnes manquantes
    foreach ($productionColumns as $colName => $colDef) {
        if (!in_array($colName, $existingColumns)) {
            try {
                $db->query("ALTER TABLE climbing_sectors ADD COLUMN $colName $colDef");
                echo "✅ Ajouté colonne: $colName\n";
            } catch (\Exception $e) {
                echo "⚠️  Erreur ajout $colName: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 2. Créer les tables manquantes
    echo "\n2. CRÉATION TABLES MANQUANTES:\n";
    echo "===============================\n";
    
    // Table climbing_exposures
    try {
        $db->query("CREATE TABLE IF NOT EXISTS climbing_exposures (
            id INTEGER PRIMARY KEY,
            code VARCHAR(5) NOT NULL,
            name VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            angle_min INTEGER DEFAULT NULL,
            angle_max INTEGER DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ Table climbing_exposures créée\n";
    } catch (\Exception $e) {
        echo "⚠️  Erreur climbing_exposures: " . $e->getMessage() . "\n";
    }
    
    // Table climbing_months
    try {
        $db->query("CREATE TABLE IF NOT EXISTS climbing_months (
            id INTEGER PRIMARY KEY,
            code VARCHAR(3) NOT NULL,
            name VARCHAR(20) NOT NULL,
            season VARCHAR(20) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ Table climbing_months créée\n";
    } catch (\Exception $e) {
        echo "⚠️  Erreur climbing_months: " . $e->getMessage() . "\n";
    }
    
    // Table climbing_sector_exposures
    try {
        $db->query("CREATE TABLE IF NOT EXISTS climbing_sector_exposures (
            id INTEGER PRIMARY KEY,
            sector_id INTEGER NOT NULL,
            exposure_id INTEGER NOT NULL,
            is_primary INTEGER DEFAULT 0,
            notes VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id),
            FOREIGN KEY (exposure_id) REFERENCES climbing_exposures(id)
        )");
        echo "✅ Table climbing_sector_exposures créée\n";
    } catch (\Exception $e) {
        echo "⚠️  Erreur climbing_sector_exposures: " . $e->getMessage() . "\n";
    }
    
    // Table climbing_sector_months  
    try {
        $db->query("CREATE TABLE IF NOT EXISTS climbing_sector_months (
            id INTEGER PRIMARY KEY,
            sector_id INTEGER NOT NULL,
            month_id INTEGER NOT NULL,
            quality VARCHAR(20) DEFAULT 'good',
            notes VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id),
            FOREIGN KEY (month_id) REFERENCES climbing_months(id)
        )");
        echo "✅ Table climbing_sector_months créée\n";
    } catch (\Exception $e) {
        echo "⚠️  Erreur climbing_sector_months: " . $e->getMessage() . "\n";
    }
    
    // 3. Vérification finale
    echo "\n3. VÉRIFICATION STRUCTURE FINALE:\n";
    echo "==================================\n";
    
    $finalColumns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    echo "Colonnes climbing_sectors: " . count($finalColumns) . "\n";
    
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'climbing_%'");
    echo "Tables climbing_*: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "  - " . $table['name'] . "\n";
    }
    
    echo "\n✅ SYNCHRONISATION TERMINÉE\n";
    
} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN SYNCHRONISATION ===\n";