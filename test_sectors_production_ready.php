<?php
// test_sectors_production_ready.php - Test final secteurs prêt pour production
require_once __DIR__ . '/bootstrap.php';

echo "=== TEST SECTEURS PRODUCTION READY - TopoclimbCH ===\n\n";

try {
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Base de données: " . get_class($db) . "\n";
    
    // Test 1: Vérifier la structure de la table
    echo "\n1. VÉRIFICATION STRUCTURE TABLE:\n";
    echo "=================================\n";
    
    try {
        $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
        $hasCodeColumn = false;
        $hasActiveColumn = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'code') $hasCodeColumn = true;
            if ($column['name'] === 'active') $hasActiveColumn = true;
        }
        
        echo "Colonne 'code': " . ($hasCodeColumn ? "✅ PRÉSENTE" : "❌ MANQUANTE") . "\n";
        echo "Colonne 'active': " . ($hasActiveColumn ? "✅ PRÉSENTE" : "❌ MANQUANTE") . "\n";
        echo "Total colonnes: " . count($columns) . "\n";
        
    } catch (\Exception $e) {
        echo "❌ Erreur structure: " . $e->getMessage() . "\n";
    }
    
    // Test 2: Test des requêtes de fallback
    echo "\n2. TEST DES 4 NIVEAUX DE FALLBACK:\n";
    echo "===================================\n";
    
    $sectorService = new TopoclimbCH\Services\SectorService($db);
    
    // Test direct des requêtes dans l'ordre des fallbacks
    $queries = [
        "Niveau 1 (code existant)" => "SELECT s.id, s.name, s.code, s.region_id, r.name as region_name FROM climbing_sectors s LEFT JOIN climbing_regions r ON s.region_id = r.id WHERE s.active = 1 ORDER BY s.name ASC LIMIT 5",
        
        "Niveau 2 (code généré)" => "SELECT s.id, s.name, 'SEC' || printf('%03d', s.id) as code, s.region_id, r.name as region_name FROM climbing_sectors s LEFT JOIN climbing_regions r ON s.region_id = r.id WHERE s.active = 1 ORDER BY s.name ASC LIMIT 5",
        
        "Niveau 3 (minimal)" => "SELECT s.id, s.name, 'SEC' || s.id as code, s.region_id, r.name as region_name FROM climbing_sectors s LEFT JOIN climbing_regions r ON s.region_id = r.id ORDER BY s.name ASC LIMIT 5"
    ];
    
    foreach ($queries as $level => $query) {
        try {
            $result = $db->fetchAll($query);
            echo "✅ $level - " . count($result) . " résultats\n";
            
            if (!empty($result)) {
                echo "   Exemple: {$result[0]['name']} ({$result[0]['code']})\n";
            }
            
        } catch (\Exception $e) {
            echo "❌ $level - ÉCHOUE: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 3: Test du SectorService complet avec ses fallbacks
    echo "\n3. TEST SECTORSERVICE COMPLET:\n";
    echo "===============================\n";
    
    $sectors = $sectorService->getPaginatedSectors(null);
    
    if ($sectors && method_exists($sectors, 'getItems')) {
        $items = $sectors->getItems();
        echo "✅ SectorService retourne " . count($items) . " secteurs\n";
        
        if (!empty($items)) {
            echo "\nListe complète:\n";
            foreach ($items as $sector) {
                echo sprintf("  - #%d %s (%s) - Région: %s - %d voies\n",
                    $sector['id'],
                    $sector['name'],
                    $sector['code'] ?? 'N/A',
                    $sector['region_name'] ?? 'N/A',
                    $sector['routes_count'] ?? 0
                );
            }
        }
    } else {
        echo "❌ SectorService a échoué\n";
    }
    
    // Test 4: Instructions pour la production
    echo "\n4. INSTRUCTIONS PRODUCTION:\n";
    echo "============================\n";
    echo "Pour tester en production :\n";
    echo "1. Déployer ce code sur le serveur\n";
    echo "2. Aller sur: https://votre-site.ch/sectors?debug_sectors=allow\n";
    echo "3. La page devrait s'afficher même sans authentification\n";
    echo "4. Vérifier les logs pour voir quel niveau de fallback est utilisé\n";
    echo "5. Si ça marche: retirer le bypass debug et configurer l'auth correctement\n\n";
    
    echo "Scripts disponibles :\n";
    echo "- php diagnose_code_column.php (diagnostic structure DB)\n";
    echo "- php fix_sectors_code_column.php (ajouter colonne code si manquante)\n";
    
} catch (\Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";