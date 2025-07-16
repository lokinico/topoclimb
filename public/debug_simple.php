<?php
/**
 * Debug simple étape par étape
 */
header('Content-Type: text/plain');

echo "=== DEBUG SIMPLE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    echo "1. Chargement de l'autoloader...\n";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ Autoloader chargé\n";
    
    echo "2. Chargement du bootstrap...\n";
    require_once __DIR__ . '/bootstrap.php';
    echo "✅ Bootstrap chargé\n";
    
    echo "3. Création du container...\n";
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container créé\n";
    
    echo "4. Récupération de la base de données...\n";
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    echo "✅ Base de données récupérée\n";
    
    echo "5. Test de requête simple...\n";
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='climbing_regions'");
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
    
} catch (Exception $e) {
    echo "❌ ERREUR à l'étape en cours\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIN DEBUG SIMPLE ===\n";
?>