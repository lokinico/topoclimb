<?php
// fix_sectors_code_column.php - Correction colonne 'code' secteurs
echo "=== CORRECTION COLONNE 'code' - SECTEURS ===\n\n";

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/src/Core/Database.php';
    
    $db = TopoclimbCH\Core\Database::getInstance();
    echo "✅ Connexion DB réussie\n\n";

    // Vérifier si la colonne existe
    $columns = $db->fetchAll("DESCRIBE climbing_sectors");
    $hasCodeColumn = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'code') {
            $hasCodeColumn = true;
            break;
        }
    }

    if (!$hasCodeColumn) {
        echo "🔧 AJOUT DE LA COLONNE 'code'...\n";
        
        // Ajouter la colonne code
        $db->query("ALTER TABLE climbing_sectors ADD COLUMN code VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Code secteur'");
        echo "✅ Colonne 'code' ajoutée\n";

        // Générer des codes uniques pour les secteurs existants
        echo "📝 Génération des codes secteurs...\n";
        
        $sectors = $db->fetchAll("SELECT id, name FROM climbing_sectors ORDER BY id ASC");
        
        foreach ($sectors as $sector) {
            // Générer un code basé sur le nom et l'ID
            $name = preg_replace('/[^a-zA-Z0-9]/', '', $sector['name']);
            $code = strtoupper(substr($name, 0, 3) . sprintf('%03d', $sector['id']));
            
            // S'assurer que le code est unique
            $counter = 1;
            $originalCode = $code;
            
            while (true) {
                $existingCode = $db->fetchOne(
                    "SELECT id FROM climbing_sectors WHERE code = ? AND id != ?", 
                    [$code, $sector['id']]
                );
                
                if (!$existingCode) {
                    break;
                }
                
                $code = $originalCode . $counter;
                $counter++;
            }
            
            // Mettre à jour le secteur avec le code généré
            $db->update(
                'climbing_sectors', 
                ['code' => $code], 
                'id = ?', 
                [$sector['id']]
            );
            
            echo "  Secteur #{$sector['id']} '{$sector['name']}' -> Code: $code\n";
        }
        
        echo "✅ Codes générés pour " . count($sectors) . " secteurs\n\n";

    } else {
        echo "✅ Colonne 'code' déjà présente\n\n";
    }

    // Test final
    echo "🧪 TEST FINAL:\n";
    echo "===============\n";
    
    $testResult = $db->fetchAll("SELECT id, name, code FROM climbing_sectors LIMIT 5");
    
    echo "Premiers secteurs avec codes:\n";
    foreach ($testResult as $sector) {
        echo sprintf("  #%-3d | %-30s | %s\n", 
            $sector['id'], 
            substr($sector['name'], 0, 30), 
            $sector['code']
        );
    }
    
    echo "\n✅ CORRECTION TERMINÉE AVEC SUCCÈS !\n";
    echo "👉 Vous pouvez maintenant retester l'affichage des secteurs.\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN CORRECTION ===\n";