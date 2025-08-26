<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔍 VÉRIFICATION STRUCTURE BASE DE DONNÉES\n";
echo "=========================================\n\n";

try {
    $db = new Database();
    echo "✅ Database: Connexion réussie\n\n";
    
    // Lister toutes les tables
    echo "📋 Tables disponibles:\n";
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    foreach ($tables as $table) {
        echo "  - " . $table['name'] . "\n";
    }
    echo "\n";
    
    // Vérifier spécifiquement la table users
    $userTables = ['climbing_users', 'users'];
    foreach ($userTables as $tableName) {
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM $tableName")['count'];
            echo "✅ Table $tableName: $count entrées\n";
            
            // Afficher structure
            $columns = $db->fetchAll("PRAGMA table_info($tableName)");
            echo "   Colonnes:\n";
            foreach ($columns as $col) {
                echo "     - {$col['name']} ({$col['type']})\n";
            }
            echo "\n";
            
            // Afficher quelques entrées
            $entries = $db->fetchAll("SELECT * FROM $tableName LIMIT 3");
            echo "   Exemples d'entrées:\n";
            foreach ($entries as $entry) {
                echo "     - ID: {$entry['id']}, Email: " . ($entry['email'] ?? 'N/A') . "\n";
            }
            echo "\n";
            
        } catch (Exception $e) {
            echo "❌ Table $tableName: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}