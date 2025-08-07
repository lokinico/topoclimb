<?php

require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== Analyse des structures de tables Production vs Local ===\n\n";

try {
    $db = new Database();
    
    // Tables à analyser
    $tables = ['climbing_routes', 'climbing_books', 'climbing_sectors'];
    
    foreach ($tables as $table) {
        echo "--- TABLE: $table ---\n";
        
        try {
            // Pour MySQL en production, utiliser SHOW COLUMNS
            // Pour SQLite local, utiliser PRAGMA table_info
            $columns = $db->fetchAll("SHOW COLUMNS FROM $table");
            
            if (empty($columns)) {
                // Fallback pour SQLite
                $columns = $db->fetchAll("PRAGMA table_info($table)");
                echo "Structure SQLite (local):\n";
                foreach ($columns as $col) {
                    echo "  - {$col['name']} ({$col['type']})\n";
                }
            } else {
                echo "Structure MySQL (production):\n";
                foreach ($columns as $col) {
                    echo "  - {$col['Field']} ({$col['Type']})\n";
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erreur pour $table: " . $e->getMessage() . "\n";
            
            // Tenter de détecter quelques colonnes communes
            try {
                $sample = $db->fetchOne("SELECT * FROM $table LIMIT 1");
                if ($sample) {
                    echo "  Colonnes détectées via échantillon:\n";
                    foreach (array_keys($sample) as $key) {
                        echo "    - $key\n";
                    }
                }
            } catch (Exception $e2) {
                echo "  ❌ Impossible de lire $table: " . $e2->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    echo "=== Recommandations de corrections ===\n";
    echo "1. RouteController : Supprimer r.description de la requête\n";
    echo "2. BookController : Vérifier les colonnes disponibles pour climbing_books\n";
    echo "3. SectorController : Supprimer r.beauty_rating, r.danger_rating, r.grade_value\n";
    echo "4. Utiliser seulement les colonnes communes: id, name, sector_id, difficulty, length, created_at\n\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GÉNÉRALE: " . $e->getMessage() . "\n";
}