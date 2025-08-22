<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    echo "=== Création de la table climbing_countries ===\n\n";
    
    // Créer la table climbing_countries
    $db->query("
        CREATE TABLE IF NOT EXISTS climbing_countries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(3) NOT NULL UNIQUE,
            continent VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            active INTEGER DEFAULT 1
        )
    ");
    
    echo "✅ Table climbing_countries créée\n";
    
    // Insérer quelques pays de base
    $countries = [
        ['Suisse', 'CH', 'Europe'],
        ['France', 'FR', 'Europe'],
        ['Italie', 'IT', 'Europe'],
        ['Autriche', 'AT', 'Europe'],
        ['Allemagne', 'DE', 'Europe'],
        ['Espagne', 'ES', 'Europe']
    ];
    
    foreach ($countries as $country) {
        $existing = $db->fetchOne("SELECT id FROM climbing_countries WHERE code = ?", [$country[1]]);
        if (!$existing) {
            $db->query(
                "INSERT INTO climbing_countries (name, code, continent) VALUES (?, ?, ?)",
                $country
            );
            echo "✅ Pays ajouté: {$country[0]} ({$country[1]})\n";
        } else {
            echo "⚠️ Pays déjà existant: {$country[0]}\n";
        }
    }
    
    // Vérifier/corriger les country_id dans climbing_regions
    $regionsWithoutCountry = $db->fetchAll("
        SELECT id, name, country_id 
        FROM climbing_regions 
        WHERE country_id IS NULL OR country_id NOT IN (SELECT id FROM climbing_countries)
    ");
    
    if (!empty($regionsWithoutCountry)) {
        echo "\n=== Correction des country_id des régions ===\n";
        
        // Récupérer l'ID de la Suisse
        $swissCountry = $db->fetchOne("SELECT id FROM climbing_countries WHERE code = 'CH'");
        $swissId = $swissCountry['id'];
        
        foreach ($regionsWithoutCountry as $region) {
            $db->query(
                "UPDATE climbing_regions SET country_id = ? WHERE id = ?",
                [$swissId, $region['id']]
            );
            echo "✅ Région '{$region['name']}' assignée à la Suisse\n";
        }
    }
    
    // Vérification finale
    $countriesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_countries WHERE active = 1")['count'];
    $regionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions WHERE active = 1")['count'];
    
    echo "\n=== Résumé ===\n";
    echo "Pays actifs: {$countriesCount}\n";
    echo "Régions actives: {$regionsCount}\n";
    echo "✅ Base de données corrigée !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}