<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Migration secteurs et livres production ===\n\n";
    
    $db = new Database();
    
    // 1. Ajouter colonnes manquantes à climbing_sectors
    echo "1. Mise à jour climbing_sectors...\n";
    $sectorsColumns = [
        'code' => 'VARCHAR(50)',
        'site_id' => 'INT',
        'altitude' => 'INT',
        'height' => 'DECIMAL(8,2)',
        'coordinates_lat' => 'DECIMAL(10,8)',
        'coordinates_lng' => 'DECIMAL(11,8)',
        'coordinates_swiss_e' => 'VARCHAR(20)',
        'coordinates_swiss_n' => 'VARCHAR(20)',
        'access_info' => 'TEXT',
        'access_time' => 'INT',
        'approach' => 'TEXT',
        'parking_info' => 'TEXT',
        'color' => 'VARCHAR(7) DEFAULT "#FF0000"'
    ];
    
    foreach ($sectorsColumns as $column => $type) {
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_sectors LIKE '{$column}'");
            if (empty($columns)) {
                $db->query("ALTER TABLE climbing_sectors ADD COLUMN {$column} {$type}");
                echo "✅ Colonne {$column} ajoutée à climbing_sectors\n";
            } else {
                echo "⚠️ Colonne {$column} existe déjà\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur {$column}: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Générer des codes pour les secteurs sans code
    echo "\n2. Génération codes secteurs...\n";
    try {
        $sectorsWithoutCode = $db->fetchAll("SELECT id, name FROM climbing_sectors WHERE code IS NULL OR code = ''");
        foreach ($sectorsWithoutCode as $sector) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $sector['name']), 0, 5)) . sprintf('%02d', $sector['id']);
            $db->query("UPDATE climbing_sectors SET code = ? WHERE id = ?", [$code, $sector['id']]);
            echo "✅ Code généré: {$sector['name']} -> {$code}\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur génération codes secteurs: " . $e->getMessage() . "\n";
    }
    
    // 3. Ajouter colonnes manquantes à climbing_books
    echo "\n3. Mise à jour climbing_books...\n";
    $booksColumns = [
        'title' => 'VARCHAR(255) NOT NULL',
        'description' => 'TEXT',
        'author' => 'VARCHAR(255)',
        'publisher' => 'VARCHAR(255)',
        'publication_year' => 'INT',
        'isbn' => 'VARCHAR(20)',
        'price' => 'DECIMAL(8,2)',
        'pages' => 'INT',
        'language' => 'VARCHAR(10) DEFAULT "fr"',
        'active' => 'TINYINT(1) DEFAULT 1',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    foreach ($booksColumns as $column => $type) {
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_books LIKE '{$column}'");
            if (empty($columns)) {
                $db->query("ALTER TABLE climbing_books ADD COLUMN {$column} {$type}");
                echo "✅ Colonne {$column} ajoutée à climbing_books\n";
            } else {
                echo "⚠️ Colonne {$column} existe déjà\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur {$column}: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Vérifier et créer table expositions si nécessaire
    echo "\n4. Vérification table climbing_expositions...\n";
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS climbing_expositions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(5) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                active TINYINT(1) DEFAULT 1,
                UNIQUE KEY unique_code (code)
            )
        ");
        echo "✅ Table climbing_expositions créée/vérifiée\n";
        
        // Ajouter expositions de base
        $expositions = [
            ['Nord', 'N', 'Exposition nord'],
            ['Sud', 'S', 'Exposition sud'],
            ['Est', 'E', 'Exposition est'],
            ['Ouest', 'O', 'Exposition ouest'],
            ['Nord-Est', 'NE', 'Exposition nord-est'],
            ['Sud-Est', 'SE', 'Exposition sud-est'],
            ['Nord-Ouest', 'NO', 'Exposition nord-ouest'],
            ['Sud-Ouest', 'SO', 'Exposition sud-ouest']
        ];
        
        foreach ($expositions as $expo) {
            $existing = $db->fetchOne("SELECT id FROM climbing_expositions WHERE code = ?", [$expo[1]]);
            if (!$existing) {
                $db->query("INSERT INTO climbing_expositions (name, code, description) VALUES (?, ?, ?)", $expo);
                echo "✅ Exposition ajoutée: {$expo[0]}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "⚠️ Erreur expositions: " . $e->getMessage() . "\n";
    }
    
    // 5. Vérification finale
    echo "\n5. Vérification finale...\n";
    $sectorsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors")['count'];
    $booksCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_books")['count'];
    $expositionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_expositions WHERE active = 1")['count'];
    
    echo "✅ Secteurs: {$sectorsCount}\n";
    echo "✅ Livres: {$booksCount}\n";
    echo "✅ Expositions: {$expositionsCount}\n";
    
    echo "\n🎉 MIGRATION SECTEURS ET LIVRES TERMINÉE !\n";
    echo "⚠️ Note: Redémarrez l'application après cette migration\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GLOBALE MIGRATION: " . $e->getMessage() . "\n";
}