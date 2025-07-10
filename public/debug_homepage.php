<?php
// Debug script to test homepage functionality step by step

echo "<h1>🔍 Debug Homepage TopoclimbCH</h1>";

try {
    // 1. Test bootstrap
    echo "<h2>1. Bootstrap Test</h2>";
    require_once dirname(__DIR__) . '/bootstrap.php';
    echo "✅ Bootstrap loaded successfully<br>";
    echo "BASE_PATH: " . BASE_PATH . "<br>";
    
    // 2. Test autoloader
    echo "<h2>2. Autoloader Test</h2>";
    require BASE_PATH . '/vendor/autoload.php';
    echo "✅ Autoloader loaded successfully<br>";
    
    // 3. Test environment
    echo "<h2>3. Environment Test</h2>";
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
    echo "✅ Environment loaded successfully<br>";
    echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'undefined') . "<br>";
    
    // 4. Test Container
    echo "<h2>4. Container Test</h2>";
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container built successfully<br>";
    
    // 5. Test individual services
    echo "<h2>5. Services Test</h2>";
    
    $logger = $container->get(Psr\Log\LoggerInterface::class);
    echo "✅ Logger service retrieved<br>";
    
    $session = $container->get(\TopoclimbCH\Core\Session::class);
    echo "✅ Session service retrieved<br>";
    
    $db = $container->get(\TopoclimbCH\Core\Database::class);
    echo "✅ Database service retrieved<br>";
    
    $router = $container->get(\TopoclimbCH\Core\Router::class);
    echo "✅ Router service retrieved<br>";
    
    // 6. Test routes loading
    echo "<h2>6. Routes Test</h2>";
    $router->loadRoutes(BASE_PATH . '/config/routes.php');
    echo "✅ Routes loaded successfully<br>";
    
    // 7. Test Application
    echo "<h2>7. Application Test</h2>";
    $app = new \TopoclimbCH\Core\Application(
        $router,
        $logger,
        $container,
        'production'
    );
    echo "✅ Application initialized successfully<br>";
    
    echo "<h2>✅ All tests passed!</h2>";
    echo "<p>The homepage should work now. <a href='/'>Test Homepage</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error at step</h2>";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . ":" . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>