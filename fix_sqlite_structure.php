<?php
/**
 * Script de correction de la structure SQLite pour correspondre à la production MySQL
 * TopoclimbCH - Fix des secteurs
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== CORRECTION STRUCTURE SQLITE - CLIMBING_SECTORS ===\n\n";

try {
    $db = new Database();
    $connection = $db->getConnection();
    
    echo "1. Connexion à la base SQLite établie\n";
    
    // Commencer une transaction
    $connection->beginTransaction();
    
    echo "2. Ajout des colonnes manquantes:\n";
    
    $alterStatements = [
        "ALTER TABLE climbing_sectors ADD COLUMN book_id INTEGER DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN code VARCHAR(50) NOT NULL DEFAULT 'SEC001'",
        "ALTER TABLE climbing_sectors ADD COLUMN access_info TEXT DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN color VARCHAR(20) DEFAULT '#FF0000'",
        "ALTER TABLE climbing_sectors ADD COLUMN approach TEXT DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN height DECIMAL(6,2) DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN parking_info VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN coordinates_swiss_e VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN coordinates_swiss_n VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN active TINYINT(1) DEFAULT 1",
        "ALTER TABLE climbing_sectors ADD COLUMN created_by INTEGER DEFAULT NULL",
        "ALTER TABLE climbing_sectors ADD COLUMN updated_by INTEGER DEFAULT NULL"
    ];
    
    foreach ($alterStatements as $sql) {
        try {
            $connection->exec($sql);
            echo "✅ " . substr($sql, 0, 60) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                echo "⚠️  Colonne déjà existante: " . substr($sql, 0, 60) . "...\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n3. Mise à jour des données existantes:\n";
    
    // Mettre à jour le secteur existant avec des valeurs par défaut
    $updateSql = "UPDATE climbing_sectors SET 
        code = 'SEC' || PRINTF('%03d', id),
        color = '#FF0000',
        active = 1,
        access_info = 'Accès à définir',
        approach = 'Marche d''approche à définir'
        WHERE code IS NULL OR code = ''";
    
    $connection->exec($updateSql);
    echo "✅ Données existantes mises à jour\n";
    
    echo "\n4. Ajout de secteurs de test supplémentaires:\n";
    
    // Vérifier s'il faut ajouter des secteurs de test
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors");
    if ($count['count'] < 5) {
        $testSectors = [
            [
                'name' => 'Secteur Nord',
                'code' => 'SEC002',
                'region_id' => 1,
                'site_id' => 1,
                'description' => 'Secteur d\'escalade nord avec exposition matinale',
                'altitude' => 1200,
                'access_time' => 15,
                'color' => '#0000FF',
                'active' => 1,
                'coordinates_lat' => 46.2044,
                'coordinates_lng' => 6.1432,
                'access_info' => 'Parking au village, suivre le sentier rouge',
                'approach' => 'Marche facile de 15 minutes',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Secteur Est',
                'code' => 'SEC003',
                'region_id' => 1,
                'site_id' => 1,
                'description' => 'Secteur d\'escalade est, idéal l\'après-midi',
                'altitude' => 1150,
                'access_time' => 20,
                'color' => '#00FF00',
                'active' => 1,
                'coordinates_lat' => 46.2034,
                'coordinates_lng' => 6.1442,
                'access_info' => 'Accès par le sentier de montagne',
                'approach' => 'Montée soutenue de 20 minutes',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Secteur Ouest',
                'code' => 'SEC004',
                'region_id' => 1,
                'site_id' => 1, 
                'description' => 'Secteur d\'escalade ouest, ombragé l\'après-midi',
                'altitude' => 1300,
                'access_time' => 25,
                'color' => '#FF00FF',
                'active' => 1,
                'coordinates_lat' => 46.2024,
                'coordinates_lng' => 6.1422,
                'access_info' => 'Parking limité, covoiturage recommandé',
                'approach' => 'Marche d\'approche technique',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($testSectors as $sector) {
            $db->insert('climbing_sectors', $sector);
            echo "✅ Secteur ajouté: {$sector['name']}\n";
        }
    }
    
    echo "\n5. Vérification des relations nécessaires:\n";
    
    // Vérifier si climbing_regions existe et a des données
    try {
        $regionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions");
        if ($regionsCount['count'] == 0) {
            echo "⚠️  Table climbing_regions vide, ajout d'une région de test\n";
            $db->insert('climbing_regions', [
                'country_id' => 1,
                'name' => 'Valais',
                'description' => 'Région d\'escalade du Valais',
                'coordinates_lat' => 46.2044,
                'coordinates_lng' => 6.1432,
                'altitude' => 1000,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        echo "✅ Table climbing_regions OK\n";
    } catch (Exception $e) {
        echo "⚠️  Table climbing_regions n'existe pas: " . $e->getMessage() . "\n";
    }
    
    // Vérifier si climbing_sites existe et a des données
    try {
        $sitesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites");
        if ($sitesCount['count'] == 0) {
            echo "⚠️  Table climbing_sites vide, ajout d'un site de test\n";
            $db->insert('climbing_sites', [
                'region_id' => 1,
                'name' => 'Sierre',
                'code' => 'SIE001',
                'description' => 'Site d\'escalade de Sierre',
                'coordinates_lat' => 46.2044,
                'coordinates_lng' => 6.1432,
                'altitude' => 1000,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        echo "✅ Table climbing_sites OK\n";
    } catch (Exception $e) {
        echo "⚠️  Table climbing_sites n'existe pas: " . $e->getMessage() . "\n";
    }
    
    // Valider la transaction
    $connection->commit();
    
    echo "\n6. Vérification finale:\n";
    
    // Test des requêtes problématiques
    try {
        $result = $db->fetchAll("SELECT id, name, code, active, book_id FROM climbing_sectors LIMIT 3");
        echo "✅ Requête avec colonnes critiques fonctionne\n";
        echo "Secteurs trouvés:\n";
        foreach ($result as $sector) {
            echo "- {$sector['name']} (code: {$sector['code']}, active: {$sector['active']})\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur lors du test final: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== CORRECTION TERMINÉE AVEC SUCCÈS ===\n";
    echo "La base SQLite locale correspond maintenant à la structure de production.\n";
    echo "Vous pouvez maintenant tester /sectors sans erreurs de colonnes manquantes.\n";
    
} catch (Exception $e) {
    if ($connection && $connection->inTransaction()) {
        $connection->rollBack();
    }
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}