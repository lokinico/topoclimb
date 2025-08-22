<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== CORRECTION TABLE DIFFICULTY_SYSTEMS ===\n\n";

// Test colonne 'code'
try {
    $result = $db->fetchOne("SELECT code FROM climbing_difficulty_systems LIMIT 1");
    echo "✅ Colonne 'code' existe\n";
} catch (Exception $e) {
    echo "❌ Colonne 'code' manquante: " . $e->getMessage() . "\n";
    
    // Ajouter la colonne code
    try {
        if (str_contains($e->getMessage(), 'no such column')) {
            // SQLite
            $db->query("ALTER TABLE climbing_difficulty_systems ADD COLUMN code VARCHAR(20)");
        } else {
            // MySQL
            $db->query("ALTER TABLE climbing_difficulty_systems ADD COLUMN code VARCHAR(20) AFTER name");
        }
        echo "✅ Colonne 'code' ajoutée\n";
        
        // Remplir avec des valeurs par défaut
        $systems = $db->fetchAll("SELECT id, name FROM climbing_difficulty_systems");
        foreach ($systems as $system) {
            $code = strtolower(substr($system['name'], 0, 5));
            $db->query("UPDATE climbing_difficulty_systems SET code = ? WHERE id = ?", [$code, $system['id']]);
        }
        echo "✅ Codes générés automatiquement\n";
        
    } catch (Exception $e2) {
        echo "❌ Erreur ajout colonne: " . $e2->getMessage() . "\n";
    }
}

echo "\n=== TEST REQUÊTE ROUTECONTROLLER ===\n";

// Test de la requête problématique
try {
    $difficulty_systems = $db->fetchAll(
        "SELECT id, name, code FROM climbing_difficulty_systems WHERE active = 1 ORDER BY name ASC"
    );
    echo "✅ Requête RouteController: " . count($difficulty_systems) . " systèmes trouvés\n";
    
    if (!empty($difficulty_systems)) {
        echo "📋 Systèmes disponibles:\n";
        foreach (array_slice($difficulty_systems, 0, 3) as $sys) {
            echo "  - {$sys['name']} ({$sys['code']})\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur requête: " . $e->getMessage() . "\n";
}

echo "\n=== STRUCTURE TABLE ===\n";
try {
    // SQLite
    $columns = $db->fetchAll("PRAGMA table_info(climbing_difficulty_systems)");
    echo "📋 Structure climbing_difficulty_systems:\n";
    foreach ($columns as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
} catch (Exception $e) {
    try {
        // MySQL
        $columns = $db->query("SHOW COLUMNS FROM climbing_difficulty_systems")->fetchAll();
        echo "📋 Structure climbing_difficulty_systems:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } catch (Exception $e2) {
        echo "❌ Erreur structure: " . $e2->getMessage() . "\n";
    }
}