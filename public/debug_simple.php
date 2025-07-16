<?php
/**
 * Debug simple étape par étape
 */
header('Content-Type: text/plain');

echo "=== DEBUG SIMPLE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    echo "1. Chargement de l'autoloader...\n";
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    echo "✅ Autoloader chargé\n";
    
    echo "2. Chargement du bootstrap...\n";
    require_once dirname(__DIR__) . '/bootstrap.php';
    echo "✅ Bootstrap chargé\n";
    
    echo "3. Création du container...\n";
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container créé\n";
    
    echo "4. Récupération de la base de données...\n";
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    echo "✅ Base de données récupérée\n";
    
    echo "5. Test de requête simple (MySQL)...\n";
    $result = $db->query("SHOW TABLES LIKE 'climbing_regions'");
    if (!empty($result)) {
        echo "✅ Table climbing_regions trouvée\n";
    } else {
        echo "❌ Table climbing_regions non trouvée\n";
    }
    
    echo "6. Test de comptage...\n";
    $count = $db->query("SELECT COUNT(*) as count FROM climbing_regions");
    echo "📊 Nombre total de régions: " . $count[0]['count'] . "\n";
    
    echo "7. Test de données actives...\n";
    $active_count = $db->query("SELECT COUNT(*) as count FROM climbing_regions WHERE active = 1");
    echo "📊 Nombre de régions actives: " . $active_count[0]['count'] . "\n";
    
    echo "8. Test de vos régions spécifiques...\n";
    $your_regions = ['Gastlosen', 'Charmey', 'Fribourg'];
    foreach ($your_regions as $region_name) {
        $region = $db->query("SELECT id, name, country_id FROM climbing_regions WHERE name = ? AND active = 1", [$region_name]);
        if (!empty($region)) {
            echo "✅ $region_name trouvée (ID: {$region[0]['id']}, country_id: {$region[0]['country_id']})\n";
        } else {
            echo "❌ $region_name non trouvée\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR à l'étape en cours\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIN DEBUG SIMPLE ===\n";
?>