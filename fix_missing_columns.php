<?php
/**
 * Script de correction pour ajouter les colonnes manquantes en production
 * À exécuter uniquement après diagnostic confirmant les colonnes manquantes
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔧 CORRECTION COLONNES MANQUANTES\n";
echo "================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ATTENTION: Script dangereux, demander confirmation
echo "⚠️  ATTENTION: Ce script va modifier la structure de la base de données!\n";
echo "Vous devez avoir fait un backup avant de continuer.\n";
echo "Tapez 'CONFIRMER' pour continuer: ";

// En mode CLI, on skip la confirmation pour l'automatisation
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if ($confirmation !== 'CONFIRMER') {
    echo "❌ Opération annulée.\n";
    exit(1);
}

try {
    $db = new Database();
    
    // Détecter MySQL vs SQLite
    $is_mysql = false;
    try {
        $db->fetchOne("SELECT VERSION()");
        $is_mysql = true;
        echo "🐬 Base de données détectée: MySQL\n";
    } catch (Exception $e) {
        echo "🗃️  Base de données détectée: SQLite\n";
    }
    
    $corrections_applied = 0;
    
    // Définir les colonnes à ajouter par table
    $columns_to_add = [
        'climbing_sectors' => [
            'code' => $is_mysql ? 'VARCHAR(50)' : 'VARCHAR(50)',
            'active' => $is_mysql ? 'TINYINT(1) DEFAULT 1' : 'INTEGER DEFAULT 1'
        ],
        'climbing_regions' => [
            'active' => $is_mysql ? 'TINYINT(1) DEFAULT 1' : 'INTEGER DEFAULT 1'
        ],
        'climbing_sites' => [
            'code' => $is_mysql ? 'VARCHAR(50)' : 'VARCHAR(50)',
            'active' => $is_mysql ? 'TINYINT(1) DEFAULT 1' : 'INTEGER DEFAULT 1'
        ]
    ];
    
    foreach ($columns_to_add as $table => $columns) {
        echo "\n📋 Vérification table: $table\n";
        
        // Vérifier les colonnes existantes
        $existing_columns = [];
        try {
            if ($is_mysql) {
                $cols = $db->fetchAll("SHOW COLUMNS FROM $table");
                foreach ($cols as $col) {
                    $existing_columns[] = $col['Field'];
                }
            } else {
                $cols = $db->fetchAll("PRAGMA table_info($table)");
                foreach ($cols as $col) {
                    $existing_columns[] = $col['name'];
                }
            }
        } catch (Exception $e) {
            echo "❌ Erreur lecture structure $table: " . $e->getMessage() . "\n";
            continue;
        }
        
        // Ajouter les colonnes manquantes
        foreach ($columns as $column_name => $column_type) {
            if (!in_array($column_name, $existing_columns)) {
                echo "➕ Ajout colonne $column_name ($column_type)...\n";
                try {
                    $sql = "ALTER TABLE $table ADD COLUMN $column_name $column_type";
                    $db->execute($sql);
                    echo "✅ Colonne $column_name ajoutée avec succès\n";
                    $corrections_applied++;
                } catch (Exception $e) {
                    echo "❌ Erreur ajout colonne $column_name: " . $e->getMessage() . "\n";
                }
            } else {
                echo "✓ Colonne $column_name existe déjà\n";
            }
        }
    }
    
    // Mise à jour des valeurs par défaut si nécessaire
    if ($corrections_applied > 0) {
        echo "\n🔄 Mise à jour des valeurs par défaut...\n";
        
        // Activer tous les enregistrements existants
        $tables_with_active = ['climbing_sectors', 'climbing_regions', 'climbing_sites'];
        foreach ($tables_with_active as $table) {
            try {
                $updated = $db->execute("UPDATE $table SET active = 1 WHERE active IS NULL");
                echo "✅ Mise à jour active dans $table\n";
            } catch (Exception $e) {
                echo "⚠️  Avertissement mise à jour $table: " . $e->getMessage() . "\n";
            }
        }
        
        // Générer des codes par défaut si nécessaire
        echo "\n🏷️  Génération codes par défaut...\n";
        
        // Pour climbing_sectors
        try {
            $sectors_without_code = $db->fetchAll("SELECT id, name FROM climbing_sectors WHERE code IS NULL OR code = ''");
            foreach ($sectors_without_code as $sector) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sector['name']), 0, 3)) . str_pad($sector['id'], 3, '0', STR_PAD_LEFT);
                $db->execute("UPDATE climbing_sectors SET code = ? WHERE id = ?", [$code, $sector['id']]);
            }
            echo "✅ Codes générés pour " . count($sectors_without_code) . " secteurs\n";
        } catch (Exception $e) {
            echo "⚠️  Avertissement génération codes secteurs: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 CORRECTION TERMINÉE\n";
    echo "Corrections appliquées: $corrections_applied\n";
    echo "Relancez check_production_status.php pour vérifier\n";
    
} catch (Exception $e) {
    echo "💥 ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>