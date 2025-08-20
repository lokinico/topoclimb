<?php

// Test de la requête SQL qui cause probablement l'exception dans RouteController::create
require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔍 DIAGNOSTIC REQUÊTE SQL DANS RouteController::create\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $db = new Database();
    
    echo "📊 Test 1: Requête secteurs dans RouteController::create\n";
    
    $sql = "SELECT s.id, s.name, r.name as region_name, si.name as site_name
            FROM climbing_sectors s 
            LEFT JOIN climbing_regions r ON s.region_id = r.id 
            LEFT JOIN climbing_sites si ON s.site_id = si.id
            WHERE s.active = 1 
            ORDER BY r.name ASC, s.name ASC";
    
    echo "SQL: $sql\n\n";
    
    $sectors = $db->fetchAll($sql);
    
    echo "✅ SUCCÈS: " . count($sectors) . " secteurs récupérés\n";
    
    foreach ($sectors as $index => $sector) {
        if ($index < 3) { // Afficher seulement les 3 premiers
            echo "   - ID: {$sector['id']}, Secteur: {$sector['name']}, Site: {$sector['site_name']}, Région: {$sector['region_name']}\n";
        }
    }
    
    if (count($sectors) > 3) {
        echo "   ... et " . (count($sectors) - 3) . " autres secteurs\n";
    }
    
    echo "\n📊 Test 2: Vérification templates routes/form.twig\n";
    
    $templatePath = __DIR__ . '/resources/views/routes/form.twig';
    if (file_exists($templatePath)) {
        echo "✅ Template routes/form.twig existe\n";
    } else {
        echo "❌ Template routes/form.twig MANQUANT\n";
        
        // Lister les templates disponibles
        $routesDir = __DIR__ . '/resources/views/routes/';
        if (is_dir($routesDir)) {
            $files = scandir($routesDir);
            $twigFiles = array_filter($files, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'twig';
            });
            echo "   Templates disponibles dans routes/: " . implode(', ', $twigFiles) . "\n";
        }
    }
    
    echo "\n📊 Test 3: Test de la méthode render avec données similaires\n";
    
    // Simuler l'appel render comme dans le contrôleur
    $testData = [
        'route' => (object)['sector_id' => null],
        'sectors' => array_slice($sectors, 0, 5), // Prendre seulement 5 secteurs pour le test
        'csrf_token' => 'test_token_' . uniqid(),
        'is_edit' => false
    ];
    
    echo "✅ Données préparées pour render:\n";
    echo "   - route: " . json_encode($testData['route']) . "\n";
    echo "   - sectors: " . count($testData['sectors']) . " secteurs\n";
    echo "   - csrf_token: " . $testData['csrf_token'] . "\n";
    echo "   - is_edit: " . ($testData['is_edit'] ? 'true' : 'false') . "\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR DÉTECTÉE:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getCode() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Vérifier si c'est une erreur de colonne manquante
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo "\n🔍 PROBLÈME IDENTIFIÉ: Colonne manquante en base de données\n";
        
        // Analyser quelle colonne manque
        if (strpos($e->getMessage(), 'active') !== false) {
            echo "   Colonne manquante: 'active' dans climbing_sectors\n";
        }
        if (strpos($e->getMessage(), 'region_id') !== false) {
            echo "   Colonne manquante: 'region_id' dans climbing_sectors\n";
        }
        if (strpos($e->getMessage(), 'site_id') !== false) {
            echo "   Colonne manquante: 'site_id' dans climbing_sectors\n";
        }
        
        echo "\n🔧 SOLUTION RECOMMANDÉE:\n";
        echo "   1. Vérifier la structure de la table climbing_sectors\n";
        echo "   2. Ajouter les colonnes manquantes\n";
        echo "   3. Ou adapter la requête SQL aux colonnes disponibles\n";
    }
}

echo "\n📊 Test 4: Vérification structure tables\n";

try {
    // Vérifier la structure de climbing_sectors
    $columns = $db->fetchAll("SHOW COLUMNS FROM climbing_sectors");
    echo "✅ Colonnes dans climbing_sectors:\n";
    foreach ($columns as $col) {
        echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification de climbing_sectors: " . $e->getMessage() . "\n";
}

echo "\n🎯 DIAGNOSTIC TERMINÉ\n";
echo "Si une erreur SQL a été détectée ci-dessus, c'est la cause des redirections.\n";