<?php

/**
 * Script de correction des colonnes manquantes dans climbing_media pour production
 */

require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔧 CORRECTION COLONNES MÉDIAS PRODUCTION\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $db = new Database();
    
    // Vérifier la structure actuelle
    echo "📊 Vérification structure climbing_media...\n";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_media");
    $existingColumns = array_column($columns, 'Field');
    
    echo "✅ Colonnes existantes: " . implode(', ', $existingColumns) . "\n\n";
    
    // Colonnes à ajouter
    $requiredColumns = [
        'entity_type' => "ADD COLUMN entity_type VARCHAR(50) DEFAULT NULL AFTER id",
        'file_type' => "ADD COLUMN file_type VARCHAR(20) DEFAULT 'image' AFTER filename"
    ];
    
    $columnsAdded = 0;
    
    foreach ($requiredColumns as $columnName => $alterQuery) {
        if (!in_array($columnName, $existingColumns)) {
            echo "➕ Ajout colonne '$columnName'...\n";
            try {
                $db->execute("ALTER TABLE climbing_media $alterQuery");
                echo "✅ Colonne '$columnName' ajoutée avec succès\n";
                $columnsAdded++;
            } catch (Exception $e) {
                echo "❌ Erreur ajout '$columnName': " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ Colonne '$columnName' déjà présente\n";
        }
    }
    
    echo "\n📈 RÉSULTATS :\n";
    echo "✅ Colonnes ajoutées: $columnsAdded\n";
    
    if ($columnsAdded > 0) {
        echo "🎯 Mise à jour des données existantes...\n";
        
        // Mettre à jour entity_type pour les médias existants
        $db->execute("UPDATE climbing_media SET entity_type = 'generic' WHERE entity_type IS NULL");
        echo "✅ entity_type mis à jour\n";
        
        // Mettre à jour file_type pour les médias existants
        $db->execute("UPDATE climbing_media SET file_type = 'image' WHERE file_type IS NULL");
        echo "✅ file_type mis à jour\n";
        
        echo "\n🎉 CORRECTION TERMINÉE AVEC SUCCÈS !\n";
        echo "Les erreurs de médias en production sont maintenant corrigées.\n";
    } else {
        echo "\n✅ AUCUNE CORRECTION NÉCESSAIRE\n";
        echo "Toutes les colonnes sont déjà présentes.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Vérifiez la connexion à la base de données.\n";
}

echo "\n✅ Script terminé.\n";