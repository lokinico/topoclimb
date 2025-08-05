<?php
require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== STRUCTURE TABLE CLIMBING_SECTORS ===\n\n";

try {
    // Voir la structure de la table
    $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    echo "Colonnes de climbing_sectors:\n";
    foreach ($columns as $col) {
        echo "  - {$col['name']} ({$col['type']}) " . ($col['notnull'] ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\n";
    
    // Test simple sans colonne active
    $sectors = $db->fetchAll("SELECT id, name FROM climbing_sectors LIMIT 5");
    echo "Secteurs trouvés: " . count($sectors) . "\n";
    foreach ($sectors as $sector) {
        echo "  - ID {$sector['id']}: {$sector['name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}