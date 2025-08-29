#!/usr/bin/env php
<?php
/**
 * Diagnostic complet de la table médias en production
 */

require_once 'bootstrap.php';

echo "🔍 Diagnostic table médias production\n";
echo "=====================================\n\n";

use TopoclimbCH\Core\Database;

$db = new Database();

try {
    echo "1. Test existence table climbing_media:\n";
    
    // Test 1: Table existe-t-elle ?
    try {
        $tables = $db->fetchAll("SHOW TABLES LIKE 'climbing_media'");
        if (empty($tables)) {
            echo "❌ Table climbing_media n'existe pas\n";
            
            // Chercher tables similaires
            echo "\n2. Recherche tables similaires:\n";
            $allTables = $db->fetchAll("SHOW TABLES LIKE '%media%'");
            if (empty($allTables)) {
                echo "❌ Aucune table contenant 'media'\n";
                
                $allTables = $db->fetchAll("SHOW TABLES LIKE '%image%'");
                if (empty($allTables)) {
                    echo "❌ Aucune table contenant 'image'\n";
                    
                    $allTables = $db->fetchAll("SHOW TABLES LIKE '%photo%'");
                    if (empty($allTables)) {
                        echo "❌ Aucune table contenant 'photo'\n";
                    } else {
                        echo "✅ Tables photo trouvées:\n";
                        foreach ($allTables as $table) {
                            echo "   - " . array_values($table)[0] . "\n";
                        }
                    }
                } else {
                    echo "✅ Tables image trouvées:\n";
                    foreach ($allTables as $table) {
                        echo "   - " . array_values($table)[0] . "\n";
                    }
                }
            } else {
                echo "✅ Tables media trouvées:\n";
                foreach ($allTables as $table) {
                    echo "   - " . array_values($table)[0] . "\n";
                }
            }
            
        } else {
            echo "✅ Table climbing_media existe\n";
            
            echo "\n2. Structure de la table:\n";
            $structure = $db->fetchAll("DESCRIBE climbing_media");
            
            foreach ($structure as $column) {
                $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                $default = $column['Default'] ? " DEFAULT '{$column['Default']}'" : '';
                echo "   - {$column['Field']} ({$column['Type']}) {$null}{$default}\n";
            }
            
            echo "\n3. Comptage données:\n";
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_media")['count'];
            echo "   📊 Total enregistrements: {$count}\n";
            
            if ($count > 0) {
                echo "\n4. Échantillon données (3 premiers):\n";
                $samples = $db->fetchAll("SELECT * FROM climbing_media LIMIT 3");
                
                foreach ($samples as $i => $sample) {
                    echo "   Enregistrement " . ($i + 1) . ":\n";
                    foreach ($sample as $field => $value) {
                        $displayValue = $value ? "'{$value}'" : 'NULL';
                        echo "     {$field}: {$displayValue}\n";
                    }
                    echo "\n";
                }
            }
            
            echo "\n5. Requête de test suggérée:\n";
            echo "   SELECT * FROM climbing_media LIMIT 5;\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Erreur accès table: {$e->getMessage()}\n";
    }
    
    echo "\n6. Tables disponibles (toutes):\n";
    try {
        $allTables = $db->fetchAll("SHOW TABLES");
        $tableCount = 0;
        foreach ($allTables as $table) {
            $tableName = array_values($table)[0];
            if (strpos($tableName, 'climbing') === 0) {
                echo "   ✅ {$tableName}\n";
                $tableCount++;
            }
        }
        echo "   📊 Total tables climbing_*: {$tableCount}\n";
        
    } catch (\Exception $e) {
        echo "❌ Erreur listage tables: {$e->getMessage()}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erreur générale: {$e->getMessage()}\n";
}

echo "\n✅ Diagnostic terminé\n";
echo "\n💡 Actions recommandées:\n";
echo "1. Si table climbing_media n'existe pas: Créer la table\n";
echo "2. Si table existe mais structure différente: Adapter les requêtes\n";
echo "3. Si aucune table média: Désactiver les fonctionnalités médias\n";
echo "4. Envoyer le résultat de ce script pour analyse complète\n";