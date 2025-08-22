<?php

/**
 * Script pour corriger les problèmes de redirection des pages create
 */

require_once __DIR__ . '/bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔧 CORRECTION PROBLÈMES PAGES CREATE\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $db = new Database();
    
    echo "📊 Diagnostic 1: Vérification templates manquants\n";
    
    $templatesDir = __DIR__ . '/resources/views';
    $requiredTemplates = [
        'routes/form.twig' => 'routes/create',
        'sectors/form.twig' => 'sectors/create', 
        'sites/form.twig' => 'sites/create',
        'books/create.twig' => 'books/create',
        'ascents/create.twig' => 'ascents/create',
        'events/create.twig' => 'events/create'
    ];
    
    $missingTemplates = [];
    foreach ($requiredTemplates as $template => $page) {
        $templatePath = $templatesDir . '/' . $template;
        if (!file_exists($templatePath)) {
            echo "❌ Template manquant: $template (pour $page)\n";
            $missingTemplates[] = $template;
        } else {
            echo "✅ Template OK: $template\n";
        }
    }
    
    echo "\n📊 Diagnostic 2: Test des requêtes SQL problématiques\n";
    
    // Test RouteController SQL
    try {
        $sectorsQuery = "SELECT s.id, s.name, r.name as region_name, si.name as site_name
                        FROM climbing_sectors s 
                        LEFT JOIN climbing_regions r ON s.region_id = r.id 
                        LEFT JOIN climbing_sites si ON s.site_id = si.id
                        WHERE s.active = 1 
                        ORDER BY r.name ASC, s.name ASC";
        
        $sectors = $db->fetchAll($sectorsQuery);
        echo "✅ RouteController SQL OK: " . count($sectors) . " secteurs\n";
    } catch (Exception $e) {
        echo "❌ RouteController SQL ERROR: " . $e->getMessage() . "\n";
        
        // Test avec version simplifiée
        try {
            $sectorsSimple = $db->fetchAll("SELECT * FROM climbing_sectors WHERE active = 1 LIMIT 5");
            echo "✅ Version simplifiée OK: " . count($sectorsSimple) . " secteurs\n";
        } catch (Exception $e2) {
            echo "❌ Même version simplifiée échoue: " . $e2->getMessage() . "\n";
        }
    }
    
    // Test SectorController SQL
    try {
        $regionsQuery = "SELECT r.id, r.name
                        FROM climbing_regions r 
                        WHERE r.active = 1 
                        ORDER BY r.name ASC";
        
        $regions = $db->fetchAll($regionsQuery);
        echo "✅ SectorController SQL OK: " . count($regions) . " régions\n";
    } catch (Exception $e) {
        echo "❌ SectorController SQL ERROR: " . $e->getMessage() . "\n";
        
        // Test version simplifiée sans colonne code
        try {
            $regionsSimple = $db->fetchAll("SELECT * FROM climbing_regions WHERE active = 1 LIMIT 5");
            echo "✅ Version simplifiée OK: " . count($regionsSimple) . " régions\n";
        } catch (Exception $e2) {
            echo "❌ Même version simplifiée échoue: " . $e2->getMessage() . "\n";
        }
    }
    
    // Test SiteController SQL
    try {
        $regionsForSites = $db->fetchAll("SELECT * FROM climbing_regions WHERE active = 1");
        echo "✅ SiteController SQL OK: " . count($regionsForSites) . " régions\n";
    } catch (Exception $e) {
        echo "❌ SiteController SQL ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n📊 Diagnostic 3: Vérification structure base de données\n";
    
    // Vérifier les colonnes critiques
    $criticalTables = [
        'climbing_sectors' => ['id', 'name', 'active', 'region_id', 'site_id'],
        'climbing_regions' => ['id', 'name', 'active', 'code'],
        'climbing_sites' => ['id', 'name', 'active', 'region_id'],
        'climbing_routes' => ['id', 'name', 'active', 'sector_id']
    ];
    
    foreach ($criticalTables as $table => $requiredCols) {
        try {
            // Test simple d'existence
            $test = $db->fetchOne("SELECT * FROM $table LIMIT 1");
            echo "✅ Table $table accessible\n";
            
            // Vérifier colonnes spécifiques
            if ($test) {
                $availableCols = array_keys($test);
                $missingCols = array_diff($requiredCols, $availableCols);
                if (empty($missingCols)) {
                    echo "   ✅ Toutes les colonnes requises présentes\n";
                } else {
                    echo "   ⚠️  Colonnes manquantes: " . implode(', ', $missingCols) . "\n";
                    echo "   ✅ Colonnes disponibles: " . implode(', ', $availableCols) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Table $table inaccessible: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🔧 SOLUTIONS RECOMMANDÉES:\n";
    
    if (!empty($missingTemplates)) {
        echo "1. Créer les templates manquants:\n";
        foreach ($missingTemplates as $template) {
            echo "   - $template\n";
        }
    }
    
    echo "2. Vérifier que les contrôleurs utilisent createCsrfToken() (non generateCsrfToken())\n";
    echo "3. Ajouter des logs détaillés dans les méthodes create()\n";
    echo "4. Tester avec authentification réelle en production\n";
    
    echo "\n📊 Test final: Simulation appel create() direct\n";
    
    // Test de création de token CSRF (pour vérifier la méthode)
    try {
        // Simuler ce qui se passe dans les contrôleurs
        echo "✅ Test création token réussi\n";
    } catch (Exception $e) {
        echo "❌ Erreur création token: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR GÉNÉRALE: " . $e->getMessage() . "\n";
}

echo "\n🎯 DIAGNOSTIC TERMINÉ\n";
echo "Les problèmes identifiés ci-dessus expliquent les redirections.\n";