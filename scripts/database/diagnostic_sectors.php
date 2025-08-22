<?php
/**
 * Script de diagnostic des secteurs - TopoclimbCH
 * Analyse les différences entre la structure de production et locale
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\SectorService;

echo "=== DIAGNOSTIC SECTEURS - TopoclimbCH ===\n\n";

try {
    // 1. Test de connexion base de données
    echo "1. Test connexion base de données:\n";
    $db = new Database();
    $connection = $db->getConnection();
    echo "✅ Connexion établie avec succès\n";
    
    // Détection du type de base
    $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "📊 Type de base: $driver\n\n";
    
    // 2. Vérification structure climbing_sectors
    echo "2. Structure table climbing_sectors:\n";
    
    if ($driver === 'sqlite') {
        $result = $db->query("PRAGMA table_info(climbing_sectors)");
        $columns = $result->fetchAll();
        echo "Colonnes disponibles en SQLite:\n";
        foreach ($columns as $col) {
            echo "- {$col['name']} ({$col['type']})\n";
        }
    } else {
        // MySQL/MariaDB
        $result = $db->query("DESCRIBE climbing_sectors");
        $columns = $result->fetchAll();
        echo "Colonnes disponibles en MySQL:\n";
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
    }
    
    echo "\n";
    
    // 3. Vérification données secteurs
    echo "3. Contenu table climbing_sectors:\n";
    $sectors = $db->fetchAll("SELECT COUNT(*) as count FROM climbing_sectors");
    $totalCount = $sectors[0]['count'];
    echo "Nombre total de secteurs: $totalCount\n";
    
    if ($totalCount > 0) {
        $sampleSectors = $db->fetchAll("SELECT * FROM climbing_sectors LIMIT 3");
        echo "\nEchantillon de secteurs:\n";
        foreach ($sampleSectors as $sector) {
            echo "- ID:{$sector['id']} | Nom: {$sector['name']}";
            if (isset($sector['region_id'])) echo " | Region: {$sector['region_id']}";
            if (isset($sector['site_id'])) echo " | Site: {$sector['site_id']}";
            if (isset($sector['code'])) echo " | Code: {$sector['code']}";
            if (isset($sector['active'])) echo " | Active: {$sector['active']}";
            echo "\n";
        }
    }
    
    echo "\n";
    
    // 4. Test du SectorService
    echo "4. Test SectorService:\n";
    $sectorService = new SectorService($db);
    
    try {
        // Test getPaginatedSectors
        echo "Test getPaginatedSectors()...\n";
        
        // Créer un mock filter simple
        $mockFilter = new class {
            public function toArray() { return []; }
            public function getLimit() { return 10; }
            public function getOffset() { return 0; }
            public function getPage() { return 1; }
        };
        
        $paginatedSectors = $sectorService->getPaginatedSectors($mockFilter);
        echo "✅ getPaginatedSectors() fonctionne\n";
        echo "Type retourné: " . get_class($paginatedSectors) . "\n";
        
        // Vérifier si c'est un Paginator ou SimplePaginator
        if (method_exists($paginatedSectors, 'getData')) {
            $data = $paginatedSectors->getData();
            echo "Nombre de secteurs récupérés: " . count($data) . "\n";
            
            if (!empty($data)) {
                $firstSector = $data[0];
                echo "Premier secteur: " . (is_array($firstSector) ? $firstSector['name'] : $firstSector->name) . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur SectorService: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n";
    
    // 5. Comparaison avec structure de production attendue
    echo "5. Comparaison avec structure de production:\n";
    $expectedColumns = [
        'id', 'book_id', 'region_id', 'site_id', 'name', 'code', 'description',
        'access_info', 'color', 'access_time', 'altitude', 'approach', 'height',
        'parking_info', 'coordinates_lat', 'coordinates_lng', 'coordinates_swiss_e',
        'coordinates_swiss_n', 'active', 'created_at', 'updated_at', 'created_by', 'updated_by'
    ];
    
    $actualColumns = array_column($columns, $driver === 'sqlite' ? 'name' : 'Field');
    
    echo "Colonnes manquantes par rapport à la production:\n";
    $missing = array_diff($expectedColumns, $actualColumns);
    foreach ($missing as $col) {
        echo "❌ $col\n";
    }
    
    echo "\nColonnes supplémentaires par rapport à la production:\n";
    $extra = array_diff($actualColumns, $expectedColumns);
    foreach ($extra as $col) {
        echo "➕ $col\n";
    }
    
    if (empty($missing) && empty($extra)) {
        echo "✅ Structure parfaitement alignée avec la production\n";
    }
    
    echo "\n";
    
    // 6. Test requête problématique
    echo "6. Test des requêtes problématiques:\n";
    
    // Test requête avec 'code'
    echo "Test avec colonne 'code':\n";
    try {
        $result = $db->fetchAll("SELECT id, name, code FROM climbing_sectors LIMIT 1");
        echo "✅ Requête avec 'code' fonctionne\n";
    } catch (Exception $e) {
        echo "❌ Erreur avec 'code': " . $e->getMessage() . "\n";
    }
    
    // Test requête avec 'active'
    echo "Test avec colonne 'active':\n";
    try {
        $result = $db->fetchAll("SELECT id, name, active FROM climbing_sectors LIMIT 1");
        echo "✅ Requête avec 'active' fonctionne\n";
    } catch (Exception $e) {
        echo "❌ Erreur avec 'active': " . $e->getMessage() . "\n";
    }
    
    // Test requête avec 'book_id'
    echo "Test avec colonne 'book_id':\n";
    try {
        $result = $db->fetchAll("SELECT id, name, book_id FROM climbing_sectors LIMIT 1");
        echo "✅ Requête avec 'book_id' fonctionne\n";
    } catch (Exception $e) {
        echo "❌ Erreur avec 'book_id': " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 7. Recommandations
    echo "7. RECOMMANDATIONS:\n";
    
    if (count($missing) > 0) {
        echo "🔧 Il faut mettre à jour la structure locale pour correspondre à la production\n";
        echo "   Colonnes manquantes critiques:\n";
        foreach ($missing as $col) {
            if (in_array($col, ['code', 'active', 'book_id'])) {
                echo "   - $col (CRITIQUE - utilisée dans le code)\n";
            }
        }
    }
    
    if ($totalCount === 0) {
        echo "📝 Il faut importer des données de test dans climbing_sectors\n";
    }
    
    echo "\n=== FIN DIAGNOSTIC ===\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}