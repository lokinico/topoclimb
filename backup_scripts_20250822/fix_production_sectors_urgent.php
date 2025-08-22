<?php
// fix_production_sectors_urgent.php - Correction urgente secteurs production
require_once __DIR__ . '/bootstrap.php';

echo "=== CORRECTION URGENTE SECTEURS PRODUCTION ===\n\n";

try {
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Base de données connectée\n";
    
    // Test 1: Vérifier si colonne 'code' existe
    echo "\n1. VÉRIFICATION COLONNE 'code':\n";
    echo "================================\n";
    
    try {
        // Test MySQL
        $result = $db->fetchAll("DESCRIBE climbing_sectors");
        $hasCodeColumn = false;
        
        foreach ($result as $column) {
            if ($column['Field'] === 'code') {
                $hasCodeColumn = true;
                break;
            }
        }
        
        echo "Colonne 'code': " . ($hasCodeColumn ? "✅ PRÉSENTE" : "❌ MANQUANTE") . "\n";
        
    } catch (\Exception $e) {
        echo "❌ Erreur vérification: " . $e->getMessage() . "\n";
        
        // Si DESCRIBE ne marche pas, essayer SQLite
        try {
            $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
            $hasCodeColumn = false;
            
            foreach ($columns as $column) {
                if ($column['name'] === 'code') {
                    $hasCodeColumn = true;
                    break;
                }
            }
            
            echo "Colonne 'code' (SQLite): " . ($hasCodeColumn ? "✅ PRÉSENTE" : "❌ MANQUANTE") . "\n";
            
        } catch (\Exception $e2) {
            echo "❌ Impossible de vérifier la structure: " . $e2->getMessage() . "\n";
        }
    }
    
    // Test 2: Test des requêtes par niveau de fallback
    echo "\n2. TEST REQUÊTES FALLBACK:\n";
    echo "==========================\n";
    
    $queries = [
        "Niveau 1 (avec code)" => "SELECT s.id, s.name, s.code, s.region_id FROM climbing_sectors s LIMIT 3",
        "Niveau 2 (code généré)" => "SELECT s.id, s.name, CONCAT('SEC', LPAD(s.id, 3, '0')) as code, s.region_id FROM climbing_sectors s LIMIT 3",
        "Niveau 3 (code simple)" => "SELECT s.id, s.name, CONCAT('SEC', s.id) as code, s.region_id FROM climbing_sectors s LIMIT 3",
        "Niveau 4 (minimal)" => "SELECT s.id, s.name, s.region_id FROM climbing_sectors s LIMIT 3"
    ];
    
    $workingQuery = null;
    
    foreach ($queries as $level => $query) {
        try {
            $result = $db->fetchAll($query);
            echo "✅ $level - " . count($result) . " secteurs\n";
            
            if (!$workingQuery && !empty($result)) {
                $workingQuery = $query;
                echo "   → Cette requête fonctionne et sera utilisée\n";
            }
            
        } catch (\Exception $e) {
            echo "❌ $level - ÉCHOUE: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 3: Recommandations
    echo "\n3. RECOMMANDATIONS:\n";
    echo "===================\n";
    
    if (isset($hasCodeColumn) && !$hasCodeColumn) {
        echo "🔧 SOLUTION A - Ajouter colonne 'code' en production:\n";
        echo "   ALTER TABLE climbing_sectors ADD COLUMN code VARCHAR(50) NOT NULL DEFAULT '';\n";
        echo "   UPDATE climbing_sectors SET code = CONCAT('SEC', LPAD(id, 3, '0')) WHERE code = '';\n\n";
        
        echo "🔧 SOLUTION B - S'assurer que le SectorService avec fallbacks est déployé:\n";
        echo "   git pull (pour récupérer la version avec les 4 niveaux de fallback)\n\n";
    }
    
    if ($workingQuery) {
        echo "🎯 SOLUTION IMMÉDIATE - Cette requête fonctionne:\n";
        echo "   " . $workingQuery . "\n\n";
        echo "   Utiliser cette requête comme fallback en attendant la correction complète.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN CORRECTION ===\n";