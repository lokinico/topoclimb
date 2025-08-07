<?php

require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== Vérification de la structure de la table climbing_routes ===\n\n";

try {
    $db = new Database();
    
    // Obtenir la structure de la table
    $columns = $db->fetchAll("PRAGMA table_info(climbing_routes)");
    
    echo "Colonnes de la table climbing_routes:\n";
    foreach ($columns as $column) {
        echo "- {$column['name']} ({$column['type']}) " . 
             ($column['notnull'] ? "NOT NULL" : "NULL") . 
             ($column['pk'] ? " PRIMARY KEY" : "") . "\n";
    }
    echo "\n";
    
    // Tester avec les vraies colonnes
    $routes = $db->fetchAll("SELECT * FROM climbing_routes LIMIT 3");
    
    if (!empty($routes)) {
        echo "Exemple de données dans climbing_routes:\n";
        foreach ($routes as $route) {
            echo "Route ID {$route['id']}:\n";
            foreach ($route as $key => $value) {
                echo "  $key: " . ($value ?? 'NULL') . "\n";
            }
            echo "\n";
            break; // Afficher seulement le premier
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}