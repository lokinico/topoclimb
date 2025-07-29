<?php
/**
 * Vérifier la structure de la table users
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    echo "🔍 STRUCTURE TABLE USERS\n";
    echo "========================\n";
    
    // Vérifier la structure de la table
    $structure = $db->query("PRAGMA table_info(users)")->fetchAll();
    
    echo "Colonnes de la table users:\n";
    foreach ($structure as $column) {
        echo "- {$column['name']} ({$column['type']}) " . 
             ($column['notnull'] ? 'NOT NULL' : 'NULL') . 
             ($column['pk'] ? ' PRIMARY KEY' : '') . "\n";
    }
    
    echo "\n📊 CONTENU TABLE USERS\n";
    echo "======================\n";
    
    $users = $db->query("SELECT * FROM users LIMIT 5")->fetchAll();
    
    if (empty($users)) {
        echo "❌ Aucun utilisateur trouvé\n";
    } else {
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
            echo "Email: " . ($user['email'] ?? 'N/A') . "\n";
            echo "Role: " . ($user['role'] ?? 'N/A') . "\n";
            echo "Status: " . ($user['status'] ?? 'N/A') . "\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}