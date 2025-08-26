<?php
/**
 * Script de diagnostic pour vérifier l'état de la production
 * Vérification des structures de tables et des erreurs courantes
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔍 DIAGNOSTIC PRODUCTION - " . date('Y-m-d H:i:s') . "\n";
echo "================================================\n\n";

try {
    $db = new Database();
    
    // Test de connexion
    echo "📡 Test connexion base de données...\n";
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✅ Connexion DB OK\n\n";
    } else {
        echo "❌ Problème connexion DB\n\n";
    }
    
    // Vérification des tables critiques
    $tables_to_check = [
        'climbing_sectors',
        'climbing_regions', 
        'climbing_sites',
        'climbing_routes'
    ];
    
    foreach ($tables_to_check as $table) {
        echo "📋 Structure de $table:\n";
        try {
            // Détecter le type de DB et utiliser la bonne syntaxe
            $test_mysql = false;
            try {
                $db->fetchOne("SELECT VERSION()");
                $test_mysql = true;
            } catch (Exception $e) {
                $test_mysql = false;
            }
            
            if ($test_mysql) {
                // MySQL
                $columns = $db->fetchAll("SHOW COLUMNS FROM $table");
                foreach ($columns as $col) {
                    echo "  - {$col['Field']} ({$col['Type']})\n";
                }
            } else {
                // SQLite
                $columns = $db->fetchAll("PRAGMA table_info($table)");
                foreach ($columns as $col) {
                    echo "  - {$col['name']} ({$col['type']})\n";
                }
            }
            echo "\n";
        } catch (Exception $e) {
            echo "❌ Erreur lecture $table: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Test spécifique pour la requête qui échoue
    echo "🧪 Test requête sectors avec colonne 'code'...\n";
    try {
        $sectors = $db->fetchAll("
            SELECT s.id, s.name, s.code, s.active, 
                   r.name as region_name,
                   st.name as site_name
            FROM climbing_sectors s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE s.active = 1
            LIMIT 3
        ");
        echo "✅ Requête sectors OK - " . count($sectors) . " résultats\n";
        foreach ($sectors as $sector) {
            echo "  - {$sector['name']} (code: {$sector['code']})\n";
        }
    } catch (Exception $e) {
        echo "❌ ERREUR requête sectors: " . $e->getMessage() . "\n";
        
        // Test requête alternative sans colonne 'code'
        echo "\n🔄 Test requête alternative...\n";
        try {
            $sectors = $db->fetchAll("
                SELECT s.id, s.name, s.active, 
                       r.name as region_name,
                       st.name as site_name
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_sites st ON s.site_id = st.id
                WHERE s.active = 1
                LIMIT 3
            ");
            echo "✅ Requête alternative OK - " . count($sectors) . " résultats\n";
        } catch (Exception $e2) {
            echo "❌ ERREUR requête alternative: " . $e2->getMessage() . "\n";
        }
    }
    
    echo "\n";
    
    // Vérification de l'environnement
    echo "🌍 Informations environnement:\n";
    echo "  - PHP Version: " . phpversion() . "\n";
    
    // Détecter le type de DB sans getDsn()
    $db_type = 'Unknown';
    try {
        $version = $db->fetchOne("SELECT VERSION()");
        $db_type = 'MySQL v' . $version['VERSION()'];
    } catch (Exception $e) {
        try {
            $version = $db->fetchOne("SELECT sqlite_version() as version");
            $db_type = 'SQLite v' . $version['version'];
        } catch (Exception $e2) {
            $db_type = 'Unknown';
        }
    }
    
    echo "  - DB Type: " . $db_type . "\n";
    echo "  - Working Dir: " . getcwd() . "\n";
    
} catch (Exception $e) {
    echo "💥 ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Diagnostic terminé\n";
?>