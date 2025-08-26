<?php
/**
 * Test rapide pour vérifier si la page sectors fonctionne
 * Sans dépendances, peut être exécuté directement en production
 */

echo "🚀 TEST RAPIDE PRODUCTION SECTORS\n";
echo "=================================\n\n";

require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

try {
    // Test direct de la requête problématique
    
    $db = new Database();
    
    echo "1. 🧪 Test requête sectors basique...\n";
    $sectors = $db->fetchAll("SELECT * FROM climbing_sectors LIMIT 3");
    echo "   ✅ " . count($sectors) . " secteurs trouvés\n\n";
    
    echo "2. 🧪 Test avec colonne 'code'...\n";
    try {
        $sectors_with_code = $db->fetchAll("SELECT id, name, code FROM climbing_sectors LIMIT 3");
        echo "   ✅ Colonne 'code' disponible - " . count($sectors_with_code) . " résultats\n";
        foreach ($sectors_with_code as $s) {
            echo "      - {$s['name']} (code: {$s['code']})\n";
        }
    } catch (Exception $e) {
        echo "   ❌ ERREUR colonne 'code': " . $e->getMessage() . "\n";
    }
    
    echo "\n3. 🧪 Test avec colonne 'active'...\n";
    try {
        $active_sectors = $db->fetchAll("SELECT id, name, active FROM climbing_sectors WHERE active = 1 LIMIT 3");
        echo "   ✅ Colonne 'active' disponible - " . count($active_sectors) . " secteurs actifs\n";
    } catch (Exception $e) {
        echo "   ❌ ERREUR colonne 'active': " . $e->getMessage() . "\n";
    }
    
    echo "\n4. 🧪 Test requête complète sectors (comme dans l'app)...\n";
    try {
        $full_query = $db->fetchAll("
            SELECT s.id, s.name, s.code, s.active, 
                   r.name as region_name,
                   st.name as site_name
            FROM climbing_sectors s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE s.active = 1
            LIMIT 3
        ");
        echo "   ✅ Requête complète OK - " . count($full_query) . " résultats\n";
        echo "   🎉 LA PAGE SECTORS DEVRAIT FONCTIONNER !\n";
    } catch (Exception $e) {
        echo "   ❌ ERREUR requête complète: " . $e->getMessage() . "\n";
        echo "   🚨 C'est probablement le problème de la page sectors\n";
    }
    
} catch (Exception $e) {
    echo "💥 ERREUR BOOTSTRAP: " . $e->getMessage() . "\n";
    echo "Vérifiez que bootstrap.php existe et fonctionne\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test terminé - " . date('Y-m-d H:i:s') . "\n";
?>