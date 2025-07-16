<?php
/**
 * Debug spécifique pour la page régions
 */
header('Content-Type: text/plain');

echo "=== DEBUG PAGE RÉGIONS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Charger l'application
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/bootstrap.php';
    
    // Récupérer la base de données
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    
    echo "✅ Connexion à la base réussie\n\n";
    
    // Test 1: Vérifier la table climbing_regions
    echo "=== TEST 1: TABLE CLIMBING_REGIONS ===\n";
    $regions = $db->query("SELECT COUNT(*) as count FROM climbing_regions WHERE active = 1");
    $count = $regions[0]['count'] ?? 0;
    echo "📊 Nombre de régions actives: $count\n";
    
    if ($count > 0) {
        $regions = $db->query("SELECT id, name, description, country_id FROM climbing_regions WHERE active = 1 LIMIT 5");
        echo "🗺️ Régions trouvées:\n";
        foreach ($regions as $region) {
            echo "  - {$region['name']} (ID: {$region['id']}, country_id: {$region['country_id']})\n";
        }
    }
    
    // Test 2: Vérifier la table climbing_countries
    echo "\n=== TEST 2: TABLE CLIMBING_COUNTRIES ===\n";
    $countries = $db->query("SELECT COUNT(*) as count FROM climbing_countries WHERE active = 1");
    $count = $countries[0]['count'] ?? 0;
    echo "📊 Nombre de pays actifs: $count\n";
    
    if ($count > 0) {
        $countries = $db->query("SELECT id, name, code FROM climbing_countries WHERE active = 1 LIMIT 5");
        echo "🌍 Pays trouvés:\n";
        foreach ($countries as $country) {
            echo "  - {$country['name']} (ID: {$country['id']}, code: {$country['code']})\n";
        }
    }
    
    // Test 3: Requête exacte du contrôleur
    echo "\n=== TEST 3: REQUÊTE EXACTE DU CONTRÔLEUR ===\n";
    $sql = "SELECT r.id, r.name, r.description, r.coordinates_lat, r.coordinates_lng,
                   r.altitude, r.created_at, c.name as country_name, c.code as country_code
            FROM climbing_regions r 
            LEFT JOIN climbing_countries c ON r.country_id = c.id 
            WHERE r.active = 1
            ORDER BY r.name ASC
            LIMIT 500";
    
    $regions = $db->query($sql);
    echo "📊 Résultats de la requête complète: " . count($regions) . "\n";
    
    if (count($regions) > 0) {
        echo "🗺️ Régions avec pays:\n";
        foreach (array_slice($regions, 0, 5) as $region) {
            echo "  - {$region['name']} ({$region['country_name']}) - ID: {$region['id']}\n";
        }
    } else {
        echo "❌ Aucune région trouvée avec la requête complète\n";
    }
    
    // Test 4: Simuler les filtres par défaut
    echo "\n=== TEST 4: FILTRES PAR DÉFAUT ===\n";
    $filters = [
        'country_id' => null,
        'search' => null,
        'sort' => 'r.name',
        'order' => 'asc'
    ];
    echo "🔍 Filtres simulés: " . json_encode($filters) . "\n";
    
    // Test 5: Vérifier les données spécifiques de vos régions
    echo "\n=== TEST 5: VOS RÉGIONS SPÉCIFIQUES ===\n";
    $your_regions = ['Gastlosen', 'Charmey', 'Fribourg'];
    foreach ($your_regions as $region_name) {
        $region = $db->query("SELECT * FROM climbing_regions WHERE name = ? AND active = 1", [$region_name]);
        if (!empty($region)) {
            echo "✅ $region_name trouvée (ID: {$region[0]['id']}, country_id: {$region[0]['country_id']})\n";
        } else {
            echo "❌ $region_name non trouvée\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "🔍 Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DU DEBUG ===\n";
?>