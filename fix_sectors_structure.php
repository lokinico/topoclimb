<?php
/**
 * Script de correction automatique pour la structure des secteurs
 */

echo "=== CORRECTION STRUCTURE SECTEURS ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sh139940_', 'sh139940_', 'RY[p]x1n4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion DB établie\n";
} catch (Exception $e) {
    echo "❌ Erreur connexion : " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Vérifier si la colonne 'code' existe
echo "\n=== VÉRIFICATION COLONNE 'code' ===\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM climbing_sectors LIKE 'code'")->fetchAll();
    if (empty($columns)) {
        echo "❌ Colonne 'code' absente, ajout en cours...\n";
        
        // Ajouter la colonne 'code'
        $pdo->exec("ALTER TABLE climbing_sectors ADD COLUMN code VARCHAR(50) NOT NULL DEFAULT '' AFTER id");
        echo "✅ Colonne 'code' ajoutée avec succès\n";
        
        // Générer des codes pour les secteurs existants
        echo "🔧 Génération des codes pour secteurs existants...\n";
        $sectors = $pdo->query("SELECT id, name FROM climbing_sectors WHERE code = ''")->fetchAll();
        
        $stmt = $pdo->prepare("UPDATE climbing_sectors SET code = ? WHERE id = ?");
        foreach ($sectors as $sector) {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $sector['name']), 0, 8)) . $sector['id'];
            $stmt->execute([$code, $sector['id']]);
        }
        echo "✅ " . count($sectors) . " codes générés\n";
    } else {
        echo "✅ Colonne 'code' déjà présente\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur colonne 'code' : " . $e->getMessage() . "\n";
}

// 2. Vérifier index unique sur code
echo "\n=== VÉRIFICATION INDEX UNIQUE ===\n";
try {
    $indexes = $pdo->query("SHOW INDEX FROM climbing_sectors WHERE Column_name = 'code'")->fetchAll();
    if (empty($indexes)) {
        echo "🔧 Ajout index unique sur colonne 'code'...\n";
        $pdo->exec("ALTER TABLE climbing_sectors ADD UNIQUE KEY unique_code (code)");
        echo "✅ Index unique ajouté\n";
    } else {
        echo "✅ Index sur 'code' déjà présent\n";
    }
} catch (Exception $e) {
    echo "⚠️ Index unique : " . $e->getMessage() . "\n";
}

// 3. Migrer données depuis ancienne table secteur si nécessaire
echo "\n=== MIGRATION DONNÉES ANCIENNES ===\n";
try {
    $oldSectors = $pdo->query("SELECT COUNT(*) FROM secteur")->fetchColumn();
    $newSectors = $pdo->query("SELECT COUNT(*) FROM climbing_sectors")->fetchColumn();
    
    echo "Ancienne table 'secteur' : $oldSectors secteurs\n";
    echo "Nouvelle table 'climbing_sectors' : $newSectors secteurs\n";
    
    if ($oldSectors > 0 && $newSectors == 0) {
        echo "🔧 Migration des données en cours...\n";
        
        $migrationSql = "
        INSERT INTO climbing_sectors (
            code, name, description, coordinates_lat, coordinates_lng, 
            altitude, access_time, approach, parking_info, color, 
            active, created_at, updated_at
        )
        SELECT 
            CONCAT('SEC', LPAD(id, 3, '0')),
            COALESCE(secteur, 'Secteur sans nom'),
            text,
            CASE 
                WHEN coordinates LIKE '%,%' THEN SUBSTRING(coordinates, 1, LOCATE(',', coordinates) - 1)
                ELSE NULL 
            END,
            CASE 
                WHEN coordinates LIKE '%,%' THEN SUBSTRING(coordinates, LOCATE(',', coordinates) + 1)
                ELSE NULL 
            END,
            CASE WHEN altitude REGEXP '^[0-9]+$' THEN altitude ELSE NULL END,
            CASE WHEN accesstime REGEXP '^[0-9]+$' THEN accesstime ELSE NULL END,
            Accès,
            parc,
            COALESCE(color, '#FF0000'),
            1,
            NOW(),
            NOW()
        FROM secteur
        WHERE secteur IS NOT NULL AND secteur != ''";
        
        $pdo->exec($migrationSql);
        $migrated = $pdo->query("SELECT COUNT(*) FROM climbing_sectors")->fetchColumn();
        echo "✅ $migrated secteurs migrés\n";
    }
} catch (Exception $e) {
    echo "⚠️ Migration : " . $e->getMessage() . "\n";
}

// 4. Test final
echo "\n=== TEST FINAL ===\n";
try {
    $sectors = $pdo->query("
        SELECT s.id, s.name, s.code, s.altitude 
        FROM climbing_sectors s 
        WHERE s.active = 1 
        ORDER BY s.name ASC 
        LIMIT 5
    ")->fetchAll();
    
    echo "✅ Requête test réussie - " . count($sectors) . " secteurs trouvés:\n";
    foreach ($sectors as $sector) {
        echo "  - {$sector['code']}: {$sector['name']} ({$sector['altitude']}m)\n";
    }
} catch (Exception $e) {
    echo "❌ Test final : " . $e->getMessage() . "\n";
}

echo "\n=== CORRECTION TERMINÉE ===\n";