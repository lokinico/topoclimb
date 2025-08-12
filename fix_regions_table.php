<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Ajout des colonnes manquantes à climbing_regions ===\n\n";
    
    $db = new Database();
    
    // Colonnes à ajouter
    $columnsToAdd = [
        'best_season' => 'VARCHAR(100)',
        'access_info' => 'TEXT',
        'parking_info' => 'TEXT'
    ];
    
    foreach ($columnsToAdd as $columnName => $columnType) {
        try {
            $sql = "ALTER TABLE climbing_regions ADD COLUMN {$columnName} {$columnType}";
            $db->query($sql);
            echo "✅ Colonne '{$columnName}' ajoutée ({$columnType})\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'duplicate column') !== false) {
                echo "⚠️ Colonne '{$columnName}' déjà existante\n";
            } else {
                echo "❌ Erreur pour '{$columnName}': " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Vérification finale
    echo "\n=== Structure finale de la table ===\n";
    $columns = $db->fetchAll('PRAGMA table_info(climbing_regions)');
    foreach ($columns as $col) {
        echo "- {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }
    
    echo "\n✅ Table climbing_regions mise à jour !\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GLOBALE: " . $e->getMessage() . "\n";
}