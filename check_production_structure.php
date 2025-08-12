<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Vérification structure production ===\n\n";
    
    $db = new Database();
    
    // Vérifier la structure de climbing_regions
    echo "Structure climbing_regions (production):\n";
    try {
        $columns = $db->fetchAll("DESCRIBE climbing_regions");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur structure regions: " . $e->getMessage() . "\n";
    }
    
    echo "\nStructure climbing_sites (production):\n";
    try {
        $columns = $db->fetchAll("DESCRIBE climbing_sites");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur structure sites: " . $e->getMessage() . "\n";
    }
    
    echo "\nStructure climbing_countries (production):\n";
    try {
        $columns = $db->fetchAll("DESCRIBE climbing_countries");
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
        echo "La table climbing_countries n'existe probablement pas en production\n";
    }
    
    // Vérifier quelques données existantes
    echo "\nDonnées existantes:\n";
    $regionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions")['count'];
    echo "- Régions: {$regionsCount}\n";
    
    $sitesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites")['count'];
    echo "- Sites: {$sitesCount}\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR GLOBALE: " . $e->getMessage() . "\n";
}