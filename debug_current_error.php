<?php

// Debug script pour identifier l'erreur actuelle
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== Debug TopoclimbCH - " . date('Y-m-d H:i:s') . " ===\n\n";

try {
    // 1. Vérifier les fichiers essentiels
    echo "1. Vérification des fichiers essentiels:\n";
    $essentialFiles = [
        'public/index.php',
        'src/Core/Application.php',
        'config/routes.php',
        'storage/climbing_sqlite.db'
    ];
    
    foreach ($essentialFiles as $file) {
        if (file_exists($file)) {
            echo "✓ $file existe\n";
        } else {
            echo "✗ ERREUR: $file manquant!\n";
        }
    }
    
    // 2. Tester l'autoload
    echo "\n2. Test de l'autoload:\n";
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        echo "✓ Autoload chargé\n";
    } else {
        echo "✗ ERREUR: vendor/autoload.php manquant!\n";
        exit(1);
    }
    
    // 3. Tester la connexion à la base de données
    echo "\n3. Test de la base de données:\n";
    try {
        $db = new PDO('sqlite:storage/climbing_sqlite.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✓ Base de données accessible - {$result['count']} utilisateurs\n";
    } catch (Exception $e) {
        echo "✗ ERREUR DB: " . $e->getMessage() . "\n";
    }
    
    // 4. Tester le chargement de l'application
    echo "\n4. Test de l'application:\n";
    try {
        require_once 'src/Core/Application.php';
        $app = new \TopoclimbCH\Core\Application();
        echo "✓ Application instanciée\n";
    } catch (Exception $e) {
        echo "✗ ERREUR APP: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
    
    // 5. Vérifier les routes récentes
    echo "\n5. Vérification des routes récentes:\n";
    try {
        $routes = require 'config/routes.php';
        echo "✓ Routes chargées - " . count($routes) . " routes définies\n";
        
        // Chercher les nouvelles routes d'équipement et de checklists
        $equipmentRoutes = array_filter($routes, function($route) {
            return strpos($route['path'], '/equipment') === 0;
        });
        
        $checklistRoutes = array_filter($routes, function($route) {
            return strpos($route['path'], '/checklists') === 0;
        });
        
        echo "  - " . count($equipmentRoutes) . " routes d'équipement\n";
        echo "  - " . count($checklistRoutes) . " routes de checklists\n";
        
    } catch (Exception $e) {
        echo "✗ ERREUR ROUTES: " . $e->getMessage() . "\n";
    }
    
    // 6. Vérifier les nouveaux modèles
    echo "\n6. Vérification des nouveaux modèles:\n";
    $newModels = [
        'src/Models/EquipmentCategory.php',
        'src/Models/ChecklistTemplate.php'
    ];
    
    foreach ($newModels as $model) {
        if (file_exists($model)) {
            echo "✓ $model existe\n";
        } else {
            echo "✗ $model manquant\n";
        }
    }
    
    // 7. Test simple de requête HTTP
    echo "\n7. Test de requête simulée:\n";
    try {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        
        ob_start();
        include 'public/index.php';
        $output = ob_get_clean();
        
        if (strlen($output) > 0) {
            echo "✓ Page d'accueil générée (" . strlen($output) . " caractères)\n";
        } else {
            echo "✗ Aucun contenu généré\n";
        }
        
    } catch (Exception $e) {
        echo "✗ ERREUR: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fin du diagnostic ===\n";