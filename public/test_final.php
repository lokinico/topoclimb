<?php
// Chargement du bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

echo "<h1>🧪 Test Final TopoclimbCH</h1>";

echo "<h2>📋 Configuration</h2>";
echo "BASE_PATH: " . BASE_PATH . "<br>";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'undefined') . "<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

try {
    echo "<h2>🧪 Test Container</h2>";
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container construit: " . get_class($container) . "<br>";

    echo "<h2>🧪 Test Application</h2>";
    $app = new \TopoclimbCH\Core\Application($container);
    echo "✅ Application initialisée<br>";

    echo "<h2>🎯 TOUS LES TESTS RÉUSSIS</h2>";
    echo "<p style='color: green; font-weight: bold;'>✅ TopoclimbCH est prêt à fonctionner !</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erreur</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Fichier: " . $e->getFile() . ":" . $e->getLine() . "</p>";
}
?>