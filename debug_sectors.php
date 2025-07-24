<?php
/**
 * Script de diagnostic pour les secteurs
 * Exécutez ce script pour diagnostiquer le problème des secteurs
 */

require_once __DIR__ . '/vendor/autoload.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Core\Filtering\SectorFilter;

try {
    echo "=== DIAGNOSTIC DES SECTEURS ===\n\n";
    
    // 1. Vérifier la connexion à la base de données
    echo "1. Test de connexion à la base de données...\n";
    $db = Database::getInstance();
    echo "✅ Connexion réussie\n\n";
    
    // 2. Compter le nombre total de secteurs
    echo "2. Nombre total de secteurs en base...\n";
    $totalSectors = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors");
    echo "📊 Total: " . ($totalSectors['count'] ?? 0) . " secteurs\n\n";
    
    // 3. Afficher les premiers secteurs
    echo "3. Premiers secteurs (limite 10)...\n";
    $sectorsData = $db->fetchAll("
        SELECT 
            s.id, 
            s.name, 
            s.region_id, 
            r.name as region_name,
            s.active,
            s.created_at
        FROM climbing_sectors s 
        LEFT JOIN climbing_regions r ON s.region_id = r.id 
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    
    if (empty($sectorsData)) {
        echo "❌ Aucun secteur trouvé en base de données\n";
        echo "   Vérifiez que la table climbing_sectors contient des données\n\n";
    } else {
        foreach ($sectorsData as $sector) {
            $active = $sector['active'] ? '✅' : '❌';
            echo "   {$active} ID: {$sector['id']} | {$sector['name']} | Région: {$sector['region_name']}\n";
        }
        echo "\n";
    }
    
    // 4. Tester le filtre par défaut
    echo "4. Test du filtre par défaut...\n";
    $filter = new SectorFilter([]);
    $paginatedSectors = Sector::filterAndPaginate($filter, 1, 20, 'name', 'ASC');
    
    echo "📄 Résultats paginés:\n";
    echo "   - Total: " . $paginatedSectors->getTotal() . "\n";
    echo "   - Page courante: " . $paginatedSectors->getCurrentPage() . "\n";
    echo "   - Par page: " . $paginatedSectors->getPerPage() . "\n";
    echo "   - Items retournés: " . count($paginatedSectors->getItems()) . "\n\n";
    
    // 5. Vérifier les régions
    echo "5. Vérification des régions...\n";
    $regions = $db->fetchAll("SELECT id, name, active FROM climbing_regions ORDER BY name");
    echo "📍 " . count($regions) . " régions trouvées:\n";
    foreach ($regions as $region) {
        $active = $region['active'] ? '✅' : '❌';
        echo "   {$active} ID: {$region['id']} | {$region['name']}\n";
    }
    echo "\n";
    
    // 6. Vérifier les secteurs avec coordonnées
    echo "6. Secteurs avec coordonnées GPS...\n";
    $sectorsWithCoords = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM climbing_sectors 
        WHERE coordinates_lat IS NOT NULL 
        AND coordinates_lng IS NOT NULL
    ");
    echo "🗺️ " . ($sectorsWithCoords['count'] ?? 0) . " secteurs ont des coordonnées GPS\n\n";
    
    // 7. Vérifier les permissions (simulation)
    echo "7. Test des permissions...\n";
    echo "ℹ️ Le contrôleur vérifie canViewSectors() qui requiert une authentification\n";
    echo "   Assurez-vous d'être connecté sur le site\n\n";
    
    echo "=== RÉSUMÉ ===\n";
    if ($totalSectors['count'] > 0) {
        echo "✅ Des secteurs existent en base de données\n";
        if ($paginatedSectors->getTotal() > 0) {
            echo "✅ Le système de pagination fonctionne\n";
            echo "🔍 Le problème vient probablement de l'authentification ou des filtres\n";
        } else {
            echo "❌ Le système de pagination ne retourne aucun résultat\n";
            echo "🔍 Vérifiez la logique de filtrage dans SectorFilter\n";
        }
    } else {
        echo "❌ Aucun secteur en base de données\n";
        echo "🔍 Importez des données ou créez des secteurs de test\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📋 Trace:\n" . $e->getTraceAsString() . "\n";
}