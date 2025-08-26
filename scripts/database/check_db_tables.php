<?php

// Vérifier les tables de la base de données
try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Tables dans la base de données ===\n\n";
    
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "AUCUNE TABLE TROUVÉE!\n";
        echo "La base de données est vide ou corrompue.\n";
    } else {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
        
        echo "\nTotal: " . count($tables) . " tables\n";
    }
    
    // Vérifier les tables critiques
    echo "\n=== Vérification des tables critiques ===\n";
    $criticalTables = ['users', 'climbing_regions', 'climbing_sectors'];
    
    foreach ($criticalTables as $table) {
        if (in_array($table, $tables)) {
            echo "✓ $table existe\n";
        } else {
            echo "✗ $table MANQUANTE!\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}