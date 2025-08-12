<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Ajout des colonnes manquantes à climbing_sites ===\n\n";
    
    $db = new Database();
    
    // Colonnes à ajouter
    $columnsToAdd = [
        'code' => 'VARCHAR(50) UNIQUE',
        'access_info' => 'TEXT',
        'parking_info' => 'TEXT',
        'best_season' => 'VARCHAR(100)',
        'created_by' => 'INTEGER',
        'updated_by' => 'INTEGER'
    ];
    
    foreach ($columnsToAdd as $columnName => $columnType) {
        try {
            $sql = "ALTER TABLE climbing_sites ADD COLUMN {$columnName} {$columnType}";
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
    echo "\n=== Structure finale de la table climbing_sites ===\n";
    $columns = $db->fetchAll('PRAGMA table_info(climbing_sites)');
    foreach ($columns as $col) {
        echo "- {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . "\n";
    }
    
    // Compter les sites existants
    $sitesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites WHERE active = 1")['count'];
    echo "\n📊 Sites actifs existants: {$sitesCount}\n";
    
    echo "\n✅ Table climbing_sites mise à jour !\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GLOBALE: " . $e->getMessage() . "\n";
}