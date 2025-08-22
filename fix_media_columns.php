<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== CORRECTION DES COLONNES MÉDIA ===\n\n";

// Vérification des colonnes manquantes
try {
    // Test colonne entity_type
    $result = $db->fetchOne("SELECT entity_type FROM climbing_media LIMIT 1");
    echo "✅ Colonne entity_type existe\n";
} catch (Exception $e) {
    echo "❌ Colonne entity_type manquante\n";
    
    // Ajouter entity_type
    try {
        $db->query("ALTER TABLE climbing_media ADD COLUMN entity_type VARCHAR(50) DEFAULT 'unknown'");
        echo "✅ Colonne entity_type ajoutée\n";
    } catch (Exception $e2) {
        echo "❌ Erreur ajout entity_type: " . $e2->getMessage() . "\n";
    }
}

try {
    // Test colonne file_type
    $result = $db->fetchOne("SELECT file_type FROM climbing_media LIMIT 1");
    echo "✅ Colonne file_type existe\n";
} catch (Exception $e) {
    echo "❌ Colonne file_type manquante\n";
    
    // Ajouter file_type
    try {
        $db->query("ALTER TABLE climbing_media ADD COLUMN file_type VARCHAR(50) DEFAULT 'image'");
        echo "✅ Colonne file_type ajoutée\n";
    } catch (Exception $e2) {
        echo "❌ Erreur ajout file_type: " . $e2->getMessage() . "\n";
    }
}

// Migration des données depuis climbing_media_relationships
try {
    $relations = $db->fetchAll("
        SELECT mr.media_id, mr.entity_type, mr.entity_id, m.media_type
        FROM climbing_media_relationships mr
        JOIN climbing_media m ON mr.media_id = m.id
        WHERE m.entity_type IS NULL OR m.entity_type = 'unknown'
    ");
    
    if (!empty($relations)) {
        echo "\n🔄 Migration des données relations → colonnes directes\n";
        
        foreach ($relations as $relation) {
            $db->query("
                UPDATE climbing_media 
                SET entity_type = ?, file_type = ?
                WHERE id = ?
            ", [
                $relation['entity_type'],
                $relation['media_type'] ?: 'image',
                $relation['media_id']
            ]);
        }
        
        echo "✅ " . count($relations) . " médias migrés\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur migration: " . $e->getMessage() . "\n";
}

echo "\n=== TEST DES REQUÊTES ===\n";

// Test requête site
try {
    $site_media = $db->fetchAll("
        SELECT m.id, m.title, m.file_path, m.entity_type, m.file_type
        FROM climbing_media m 
        WHERE m.entity_type = 'site' AND m.entity_id = 21
        LIMIT 5
    ");
    echo "✅ Requête médias site: " . count($site_media) . " résultats\n";
} catch (Exception $e) {
    echo "❌ Erreur médias site: " . $e->getMessage() . "\n";
}

// Test requête secteur
try {
    $sector_media = $db->fetchAll("
        SELECT m.id, m.title, m.file_path, m.file_type, m.created_at
        FROM climbing_media m 
        WHERE m.entity_type = 'sector' AND m.entity_id = 12
        LIMIT 5
    ");
    echo "✅ Requête médias secteur: " . count($sector_media) . " résultats\n";
} catch (Exception $e) {
    echo "❌ Erreur médias secteur: " . $e->getMessage() . "\n";
}

echo "\n=== STRUCTURE FINALE ===\n";
try {
    $columns = $db->query("SHOW COLUMNS FROM climbing_media")->fetchAll();
    echo "📋 Colonnes climbing_media:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur structure: " . $e->getMessage() . "\n";
}