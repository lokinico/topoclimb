<?php
// diagnose_code_column.php - Diagnostic urgent colonne 'code' 
echo "=== DIAGNOSTIC COLONNE 'code' - TopoclimbCH ===\n\n";

try {
    // Utiliser bootstrap pour initialiser l'application
    require_once __DIR__ . '/bootstrap.php';
    
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Connexion DB réussie\n\n";

    // 1. VÉRIFIER SI LA COLONNE 'code' EXISTE  
    echo "1. STRUCTURE TABLE climbing_sectors:\n";
    echo "=====================================\n";
    
    // Adapter pour SQLite
    $columns = $db->fetchAll("PRAGMA table_info(climbing_sectors)");
    
    $hasCodeColumn = false;
    foreach ($columns as $column) {
        // Format SQLite: cid, name, type, notnull, dflt_value, pk
        echo sprintf("%-20s | %-15s | NotNull:%-3s | PK:%-3s | %s\n", 
            $column['name'], 
            $column['type'], 
            $column['notnull'] ? 'YES' : 'NO',
            $column['pk'] ? 'YES' : 'NO',
            $column['dflt_value'] ?? 'NULL'
        );
        
        if ($column['name'] === 'code') {
            $hasCodeColumn = true;
            echo "👉 COLONNE 'code' TROUVÉE : " . $column['type'] . "\n";
        }
    }
    
    if (!$hasCodeColumn) {
        echo "❌ COLONNE 'code' MANQUANTE !\n";
    }
    
    echo "\n";

    // 2. TESTER LA REQUÊTE QUI ÉCHOUE
    echo "2. TEST REQUÊTE PROBLÉMATIQUE:\n";
    echo "===============================\n";
    
    try {
        $testQuery = "SELECT s.id, s.name, s.code FROM climbing_sectors s LIMIT 1";
        echo "Requête: $testQuery\n";
        
        $result = $db->fetchAll($testQuery);
        echo "✅ Requête réussie - " . count($result) . " résultat(s)\n";
        
        if (!empty($result)) {
            echo "Exemple de données:\n";
            print_r($result[0]);
        }
    } catch (Exception $e) {
        echo "❌ ERREUR REQUÊTE: " . $e->getMessage() . "\n";
    }

    // 3. COMPTER LES SECTEURS
    echo "\n3. COMPTAGE SECTEURS:\n";
    echo "=====================\n";
    
    try {
        $totalResult = $db->fetchOne("SELECT COUNT(*) as total FROM climbing_sectors");
        echo "Total secteurs: " . ($totalResult['total'] ?? 0) . "\n";
        
        $activeResult = $db->fetchOne("SELECT COUNT(*) as active FROM climbing_sectors WHERE active = 1");
        echo "Secteurs actifs: " . ($activeResult['active'] ?? 0) . "\n";
        
    } catch (Exception $e) {
        echo "❌ ERREUR COMPTAGE: " . $e->getMessage() . "\n";
    }

    // 4. VÉRIFIER CACHE/PERMISSIONS
    echo "\n4. VÉRIFICATIONS TECHNIQUES:\n";
    echo "=============================\n";
    
    echo "Base de données: " . $db->fetchOne("SELECT DATABASE() as db")['db'] . "\n";
    echo "Version MySQL: " . $db->fetchOne("SELECT VERSION() as version")['version'] . "\n";
    
    // 5. SUGGESTIONS SELON RÉSULTATS
    echo "\n5. DIAGNOSTIC ET SOLUTIONS:\n";
    echo "============================\n";
    
    if (!$hasCodeColumn) {
        echo "🔧 SOLUTION SQLite: Ajouter la colonne 'code' manquante\n";
        echo "ALTER TABLE climbing_sectors ADD COLUMN code TEXT DEFAULT '';\n\n";
        
        echo "📋 MISE À JOUR DONNÉES:\n";  
        echo "UPDATE climbing_sectors SET code = 'SEC' || printf('%03d', id) WHERE code = '' OR code IS NULL;\n";
    } else {
        echo "🤔 La colonne existe mais requête échoue - Causes possibles:\n";
        echo "- Cache MySQL/PHP\n";
        echo "- Permissions insuffisantes\n";
        echo "- Encodage caractères\n";
        echo "- Version de la table différente\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";