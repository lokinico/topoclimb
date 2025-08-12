<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Migration production MariaDB/MySQL ===\n\n";
    
    $db = new Database();
    
    // 1. Créer climbing_countries si elle n'existe pas
    echo "1. Vérification table climbing_countries...\n";
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS climbing_countries (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(3) NOT NULL,
                continent VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                active TINYINT(1) DEFAULT 1,
                UNIQUE KEY unique_code (code)
            )
        ");
        echo "✅ Table climbing_countries créée/vérifiée\n";
        
        // Ajouter quelques pays
        $countries = [
            ['Suisse', 'CH', 'Europe'],
            ['France', 'FR', 'Europe'],
            ['Italie', 'IT', 'Europe'],
            ['Autriche', 'AT', 'Europe']
        ];
        
        foreach ($countries as $country) {
            $existing = $db->fetchOne("SELECT id FROM climbing_countries WHERE code = ?", [$country[1]]);
            if (!$existing) {
                $db->query("INSERT INTO climbing_countries (name, code, continent) VALUES (?, ?, ?)", $country);
                echo "✅ Pays ajouté: {$country[0]}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "⚠️ Erreur countries: " . $e->getMessage() . "\n";
    }
    
    // 2. Ajouter colonnes manquantes à climbing_regions
    echo "\n2. Mise à jour climbing_regions...\n";
    $regionsColumns = [
        'best_season' => 'VARCHAR(100)',
        'access_info' => 'TEXT',
        'parking_info' => 'TEXT',
        'created_by' => 'INT'
    ];
    
    foreach ($regionsColumns as $column => $type) {
        try {
            // Vérifier si la colonne existe
            $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_regions LIKE '{$column}'");
            if (empty($columns)) {
                $db->query("ALTER TABLE climbing_regions ADD COLUMN {$column} {$type}");
                echo "✅ Colonne {$column} ajoutée à climbing_regions\n";
            } else {
                echo "⚠️ Colonne {$column} existe déjà\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur {$column}: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Ajouter colonnes manquantes à climbing_sites  
    echo "\n3. Mise à jour climbing_sites...\n";
    $sitesColumns = [
        'code' => 'VARCHAR(50)',
        'access_info' => 'TEXT',
        'parking_info' => 'TEXT',
        'best_season' => 'VARCHAR(100)',
        'created_by' => 'INT'
    ];
    
    foreach ($sitesColumns as $column => $type) {
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_sites LIKE '{$column}'");
            if (empty($columns)) {
                $db->query("ALTER TABLE climbing_sites ADD COLUMN {$column} {$type}");
                echo "✅ Colonne {$column} ajoutée à climbing_sites\n";
            } else {
                echo "⚠️ Colonne {$column} existe déjà\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur {$column}: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Générer des codes pour les sites sans code
    echo "\n4. Génération codes sites...\n";
    try {
        $sitesWithoutCode = $db->fetchAll("SELECT id, name FROM climbing_sites WHERE code IS NULL OR code = ''");
        foreach ($sitesWithoutCode as $site) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $site['name']), 0, 5)) . sprintf('%02d', $site['id']);
            $db->query("UPDATE climbing_sites SET code = ? WHERE id = ?", [$code, $site['id']]);
            echo "✅ Code généré: {$site['name']} -> {$code}\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur génération codes: " . $e->getMessage() . "\n";
    }
    
    // 5. Vérification finale
    echo "\n5. Vérification finale...\n";
    $regionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions")['count'];
    $sitesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites")['count'];
    $countriesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_countries")['count'];
    
    echo "✅ Régions: {$regionsCount}\n";
    echo "✅ Sites: {$sitesCount}\n";  
    echo "✅ Pays: {$countriesCount}\n";
    
    echo "\n🎉 MIGRATION PRODUCTION TERMINÉE !\n";
    echo "⚠️ Note: Redémarrez l'application après cette migration\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GLOBALE MIGRATION: " . $e->getMessage() . "\n";
}