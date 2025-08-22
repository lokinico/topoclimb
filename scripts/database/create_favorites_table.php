<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== Création Table Favoris ===\n\n";

try {
    $db = new Database();
    
    // Créer la table des favoris
    $createFavorites = "
        CREATE TABLE IF NOT EXISTS user_favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('sector', 'route', 'site', 'region')),
            entity_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, entity_type, entity_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $db->query($createFavorites);
    echo "✅ Table user_favorites créée avec succès\n";
    
    // Créer des index pour la performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_favorites_user ON user_favorites(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_favorites_entity ON user_favorites(entity_type, entity_id)",
        "CREATE INDEX IF NOT EXISTS idx_favorites_user_type ON user_favorites(user_id, entity_type)"
    ];
    
    foreach ($indexes as $index) {
        $db->query($index);
    }
    echo "✅ Index de performance créés\n";
    
    // Ajouter quelques favoris de test si des utilisateurs existent
    $users = $db->fetchAll("SELECT id FROM users LIMIT 3");
    $sectors = $db->fetchAll("SELECT id FROM climbing_sectors LIMIT 5");
    
    if (!empty($users) && !empty($sectors)) {
        echo "\n--- Ajout de favoris de test ---\n";
        
        $testFavorites = [
            ['user_id' => $users[0]['id'], 'entity_type' => 'sector', 'entity_id' => $sectors[0]['id']],
            ['user_id' => $users[0]['id'], 'entity_type' => 'sector', 'entity_id' => $sectors[1]['id']],
        ];
        
        foreach ($testFavorites as $favorite) {
            try {
                $db->query(
                    "INSERT OR IGNORE INTO user_favorites (user_id, entity_type, entity_id) VALUES (?, ?, ?)",
                    [$favorite['user_id'], $favorite['entity_type'], $favorite['entity_id']]
                );
                echo "✅ Favori ajouté: User {$favorite['user_id']} -> {$favorite['entity_type']} {$favorite['entity_id']}\n";
            } catch (Exception $e) {
                echo "⚠️ Favori déjà existant: {$e->getMessage()}\n";
            }
        }
    }
    
    // Vérifier la structure
    echo "\n--- Structure de la table ---\n";
    $columns = $db->fetchAll("PRAGMA table_info(user_favorites)");
    foreach ($columns as $col) {
        echo "  - {$col['name']} ({$col['type']}) " . ($col['notnull'] ? "NOT NULL" : "NULL") . "\n";
    }
    
    echo "\n🎉 Système de favoris prêt !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Création terminée ===\n";