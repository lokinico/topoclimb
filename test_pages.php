<?php
/**
 * Script de test pour vérifier les pages principales
 */

require_once __DIR__ . '/bootstrap.php';

echo "🔍 Test des pages principales TopoclimbCH\n";
echo "==========================================\n\n";

// Test 1: Page d'accueil
echo "📝 Test 1: Page d'accueil (/)\n";
try {
    // Simuler une requête GET vers /
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['PATH_INFO'] = '/';
    
    // Inclure et tester index.php
    ob_start();
    include __DIR__ . '/public/index.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'error') === false && strlen($output) > 100) {
        echo "✅ Page d'accueil fonctionne (". strlen($output) . " caractères générés)\n";
    } else {
        echo "❌ Page d'accueil a des problèmes\n";
        echo "Début de sortie: " . substr($output, 0, 200) . "...\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur page d'accueil: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Page de carte
echo "📝 Test 2: Page de carte (/map)\n";
try {
    // Réinitialiser les variables
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/map';
    $_SERVER['PATH_INFO'] = '/map';
    
    // Simuler l'appel au contrôleur directement
    ob_start();
    include __DIR__ . '/public/index.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'error') === false && strlen($output) > 100) {
        echo "✅ Page de carte fonctionne (". strlen($output) . " caractères générés)\n";
    } else {
        echo "❌ Page de carte a des problèmes\n";
        echo "Début de sortie: " . substr($output, 0, 200) . "...\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur page de carte: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Vérification de la base de données
echo "📝 Test 3: Connexion base de données\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    
    // Test simple
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✅ Base de données connectée\n";
    } else {
        echo "❌ Problème de connexion base de données\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur base de données: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Templates Twig
echo "📝 Test 4: Templates Twig\n";
try {
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    $view = $container->get(\TopoclimbCH\Core\View::class);
    
    // Test template simple
    $testHtml = $view->render('layouts/base', ['title' => 'Test']);
    if (strlen($testHtml) > 50) {
        echo "✅ Templates Twig fonctionnent\n";
    } else {
        echo "❌ Problème avec les templates Twig\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur templates: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "✅ Tests terminés\n";