<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

$db = new Database();

try {
    // Créer la table des systèmes de cotation
    $createSystemsTable = "
        CREATE TABLE IF NOT EXISTS climbing_difficulty_systems (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(50) NOT NULL UNIQUE,
            code VARCHAR(20) NOT NULL UNIQUE,
            description TEXT,
            country_code VARCHAR(2),
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $db->query($createSystemsTable);
    echo "✅ Table climbing_difficulty_systems créée\n";
    
    // Créer la table des grades
    $createGradesTable = "
        CREATE TABLE IF NOT EXISTS climbing_difficulty_grades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            system_id INTEGER NOT NULL,
            grade VARCHAR(20) NOT NULL,
            difficulty_order INTEGER NOT NULL,
            description TEXT,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (system_id) REFERENCES climbing_difficulty_systems(id)
        )
    ";
    
    $db->query($createGradesTable);
    echo "✅ Table climbing_difficulty_grades créée\n";
    
    // Insérer les systèmes de cotation standards
    $systems = [
        ['Française', 'FR', 'Système français (3a, 4b, 5c, 6a+, 7b, 8c+)', 'FR'],
        ['YDS', 'YDS', 'Yosemite Decimal System (5.6, 5.10a, 5.12d, 5.15c)', 'US'],
        ['UIAA', 'UIAA', 'Union Internationale des Associations d\'Alpinisme (III, V+, VII-, IX+, XII)', 'DE'],
        ['British', 'UK', 'Système britannique (E1 5c, E4 6a, E7 6b)', 'GB'],
        ['Font', 'FONT', 'Système de Fontainebleau pour blocs (3A, 4B, 6A+, 8C)', 'FR'],
        ['V-Scale', 'V', 'V-Scale pour blocs (V0, V5, V10, V17)', 'US']
    ];
    
    foreach ($systems as $system) {
        $existingSystem = $db->fetchOne(
            "SELECT id FROM climbing_difficulty_systems WHERE code = ?",
            [$system[1]]
        );
        
        if (!$existingSystem) {
            $db->insert('climbing_difficulty_systems', [
                'name' => $system[0],
                'code' => $system[1],
                'description' => $system[2],
                'country_code' => $system[3],
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "✅ Système {$system[0]} ajouté\n";
        } else {
            echo "ℹ️ Système {$system[0]} déjà existant\n";
        }
    }
    
    // Insérer des grades pour le système français (le plus utilisé)
    $frenchSystem = $db->fetchOne(
        "SELECT id FROM climbing_difficulty_systems WHERE code = 'FR'"
    );
    
    if ($frenchSystem) {
        $frenchGrades = [
            '1a', '1b', '1c', '2a', '2b', '2c', '3a', '3b', '3c', 
            '4a', '4b', '4c', '5a', '5b', '5c', 
            '6a', '6a+', '6b', '6b+', '6c', '6c+',
            '7a', '7a+', '7b', '7b+', '7c', '7c+',
            '8a', '8a+', '8b', '8b+', '8c', '8c+',
            '9a', '9a+', '9b', '9b+', '9c'
        ];
        
        $existingGrades = $db->fetchAll(
            "SELECT COUNT(*) as count FROM climbing_difficulty_grades WHERE system_id = ?",
            [$frenchSystem['id']]
        );
        
        if ($existingGrades[0]['count'] == 0) {
            foreach ($frenchGrades as $order => $grade) {
                $db->insert('climbing_difficulty_grades', [
                    'system_id' => $frenchSystem['id'],
                    'grade' => $grade,
                    'difficulty_order' => $order + 1,
                    'active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            echo "✅ Grades français ajoutés (" . count($frenchGrades) . " grades)\n";
        } else {
            echo "ℹ️ Grades français déjà existants\n";
        }
    }
    
    // Insérer des grades pour YDS
    $ydsSystem = $db->fetchOne(
        "SELECT id FROM climbing_difficulty_systems WHERE code = 'YDS'"
    );
    
    if ($ydsSystem) {
        $ydsGrades = [
            '5.1', '5.2', '5.3', '5.4', '5.5', '5.6', '5.7', '5.8', '5.9',
            '5.10a', '5.10b', '5.10c', '5.10d',
            '5.11a', '5.11b', '5.11c', '5.11d',
            '5.12a', '5.12b', '5.12c', '5.12d',
            '5.13a', '5.13b', '5.13c', '5.13d',
            '5.14a', '5.14b', '5.14c', '5.14d',
            '5.15a', '5.15b', '5.15c', '5.15d'
        ];
        
        $existingGrades = $db->fetchAll(
            "SELECT COUNT(*) as count FROM climbing_difficulty_grades WHERE system_id = ?",
            [$ydsSystem['id']]
        );
        
        if ($existingGrades[0]['count'] == 0) {
            foreach ($ydsGrades as $order => $grade) {
                $db->insert('climbing_difficulty_grades', [
                    'system_id' => $ydsSystem['id'],
                    'grade' => $grade,
                    'difficulty_order' => $order + 1,
                    'active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            echo "✅ Grades YDS ajoutés (" . count($ydsGrades) . " grades)\n";
        } else {
            echo "ℹ️ Grades YDS déjà existants\n";
        }
    }
    
    echo "\n🎯 Base de données des systèmes de cotation initialisée avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}