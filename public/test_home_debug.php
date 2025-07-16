<?php
// Test pour déboguer les erreurs de la page d'accueil
echo "<h1>🔍 Test Debug Homepage</h1>";

try {
    // Chargement du bootstrap
    require_once dirname(__DIR__) . '/bootstrap.php';
    echo "✅ Bootstrap chargé<br>";

    // Test des services un par un
    echo "<h2>🧪 Test Services</h2>";
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container construit<br>";

    // Test du HomeController spécifiquement
    echo "<h2>🏠 Test HomeController</h2>";
    $homeController = $container->get(\TopoclimbCH\Controllers\HomeController::class);
    echo "✅ HomeController instancié<br>";

    // Test de la méthode index
    echo "<h2>📄 Test index() method</h2>";
    ob_start();
    $homeController->index();
    $output = ob_get_clean();
    echo "✅ index() exécuté avec succès<br>";
    echo "Longueur sortie: " . strlen($output) . " caractères<br>";

    echo "<h2>🎯 TOUS LES TESTS RÉUSSIS</h2>";
    echo "<p style='color: green; font-weight: bold;'>✅ La page d'accueil devrait fonctionner maintenant !</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erreur détectée</h2>";
    echo "<p style='color: red;'>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Fichier: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<h3>Stack trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>