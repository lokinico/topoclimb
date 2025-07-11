<?php
/**
 * Test simple sans sessions pour vérifier la base
 */

require_once __DIR__ . '/bootstrap.php';

echo "🔍 Test simple TopoclimbCH\n";
echo "===========================\n\n";

// Test 1: Base de données uniquement
echo "📝 Test 1: Base de données\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✅ Base de données OK\n";
        
        // Test des données
        $regionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions")['count'] ?? 0;
        $sitesCount = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites")['count'] ?? 0;
        echo "📊 Régions: $regionsCount, Sites: $sitesCount\n";
    } else {
        echo "❌ Problème BDD\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur BDD: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Templates sans sessions
echo "📝 Test 2: Templates\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    
    // Éviter les services qui utilisent les sessions
    $twig = new \Twig\Environment(
        new \Twig\Loader\FilesystemLoader(__DIR__ . '/resources/views'),
        ['cache' => false, 'debug' => true]
    );
    
    $testHtml = $twig->render('layouts/base.twig', [
        'title' => 'Test Simple',
        'content' => 'Hello World'
    ]);
    
    if (strlen($testHtml) > 50) {
        echo "✅ Templates Twig OK (" . strlen($testHtml) . " chars)\n";
    } else {
        echo "❌ Templates trop courts\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur templates: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Modèles
echo "📝 Test 3: Modèles\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    
    // Injection manuelle pour éviter les singletons
    \TopoclimbCH\Models\Region::setDatabase($db);
    \TopoclimbCH\Models\Site::setDatabase($db);
    
    $regions = \TopoclimbCH\Models\Region::all();
    $sites = \TopoclimbCH\Models\Site::all();
    
    echo "✅ Modèles OK - Régions: " . count($regions) . ", Sites: " . count($sites) . "\n";
} catch (Exception $e) {
    echo "❌ Erreur modèles: " . $e->getMessage() . "\n";
}

echo "\n===========================\n";
echo "✅ Tests simples terminés\n";