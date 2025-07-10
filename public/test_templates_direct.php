<?php
// Test direct des templates sans passer par le routeur

echo "<h1>🧪 Test Templates Direct</h1>";

try {
    // Chargement du bootstrap
    require_once dirname(__DIR__) . '/bootstrap.php';
    echo "✅ Bootstrap chargé<br>";

    // Test Container
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container construit<br>";

    // Test View service
    $view = $container->get(\TopoclimbCH\Core\View::class);
    echo "✅ View service récupéré: " . get_class($view) . "<br>";

    // Test template simple
    echo "<h2>Test template simple</h2>";
    try {
        $simpleHtml = $view->render('layouts/simple', ['title' => 'Test Direct', 'message' => 'Template fonctionne !']);
        echo "✅ Template simple fonctionne<br>";
        echo "Longueur HTML: " . strlen($simpleHtml) . " caractères<br>";
    } catch (\Exception $e) {
        echo "❌ Erreur template simple: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    }

    // Test template homepage
    echo "<h2>Test template homepage</h2>";
    try {
        $homepageData = [
            'title' => 'Test Homepage',
            'description' => 'Test description',
            'stats' => [
                'regions_count' => '3',
                'sectors_count' => '25',
                'routes_count' => '301',
                'users_count' => '5'
            ],
            'popular_sectors' => [],
            'recent_books' => [],
            'trending_routes' => []
        ];
        $homepageHtml = $view->render('home/index', $homepageData);
        echo "✅ Template homepage fonctionne<br>";
        echo "Longueur HTML: " . strlen($homepageHtml) . " caractères<br>";
    } catch (\Exception $e) {
        echo "❌ Erreur template homepage: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "<br>";
        echo "<h3>Stack trace:</h3>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }

} catch (\Exception $e) {
    echo "❌ Erreur générale: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<h3>Stack trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>