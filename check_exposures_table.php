<?php
require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== STRUCTURE TABLE CLIMBING_EXPOSURES ===\n\n";

try {
    // Voir si la table existe
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='climbing_exposures'");
    
    if (empty($tables)) {
        echo "❌ Table climbing_exposures n'existe pas\n";
        
        // Lister toutes les tables qui contiennent 'exposure'
        $exposureTables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%exposure%'");
        echo "Tables contenant 'exposure':\n";
        foreach ($exposureTables as $table) {
            echo "  - {$table['name']}\n";
        }
    } else {
        // Voir la structure de la table
        $columns = $db->fetchAll("PRAGMA table_info(climbing_exposures)");
        echo "Colonnes de climbing_exposures:\n";
        foreach ($columns as $col) {
            echo "  - {$col['name']} ({$col['type']}) " . ($col['notnull'] ? 'NOT NULL' : 'NULL') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}