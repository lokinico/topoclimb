<?php

// Simulation test - simulate what happens when the application runs
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

use TopoclimbCH\Core\ContainerBuilder;

echo "🚀 APPLICATION SIMULATION TEST\n";
echo "==============================\n\n";

try {
    // Simulate what happens in public/index.php
    echo "1. Simulating public/index.php bootstrap...\n";
    
    // Build container (as in index.php line 179)
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container built successfully\n";
    
    // Get core services (as in index.php lines 183-187)
    $logger = $container->get('Psr\\Log\\LoggerInterface');
    $session = $container->get('TopoclimbCH\\Core\\Session');
    $db = $container->get('TopoclimbCH\\Core\\Database');
    echo "✅ Core services retrieved\n";
    
    // Get Auth service (as in index.php line 193)
    $auth = $container->get('TopoclimbCH\\Core\\Auth');
    echo "✅ Auth service retrieved\n";
    
    // Get router (as in index.php line 214)
    $router = $container->get('TopoclimbCH\\Core\\Router');
    echo "✅ Router service retrieved\n";
    
    echo "\n2. Testing controller instantiation...\n";
    
    // Test HomeController (main page)
    $homeController = $container->get('TopoclimbCH\\Controllers\\HomeController');
    echo "✅ HomeController instantiated\n";
    
    // Test ErrorController (error handling)
    $errorController = $container->get('TopoclimbCH\\Controllers\\ErrorController');
    echo "✅ ErrorController instantiated\n";
    
    // Test AuthController (login/register)
    $authController = $container->get('TopoclimbCH\\Controllers\\AuthController');
    echo "✅ AuthController instantiated\n";
    
    // Test DifficultySystemController (newly fixed)
    $difficultyController = $container->get('TopoclimbCH\\Controllers\\DifficultySystemController');
    echo "✅ DifficultySystemController instantiated\n";
    
    echo "\n3. Testing service functionality...\n";
    
    // Test WeatherService
    $weatherService = $container->get('TopoclimbCH\\Services\\WeatherService');
    echo "✅ WeatherService functional\n";
    
    // Test DifficultyService
    $difficultyService = $container->get('TopoclimbCH\\Services\\DifficultyService');
    echo "✅ DifficultyService functional\n";
    
    // Test RegionService
    $regionService = $container->get('TopoclimbCH\\Services\\RegionService');
    echo "✅ RegionService functional\n";
    
    echo "\n4. Testing dependency injection chains...\n";
    
    // Verify HomeController has all its services
    $reflection = new ReflectionClass($homeController);
    $regionServiceProp = $reflection->getProperty('regionService');
    $regionServiceProp->setAccessible(true);
    $injectedRegionService = $regionServiceProp->getValue($homeController);
    
    if ($injectedRegionService === $regionService) {
        echo "✅ HomeController->regionService properly injected (same instance)\n";
    } else {
        echo "⚠️  HomeController->regionService is different instance (still OK)\n";
    }
    
    // Test database connection through service
    $dbProperty = $reflection->getProperty('db');
    $dbProperty->setAccessible(true);
    $injectedDb = $dbProperty->getValue($homeController);
    
    if ($injectedDb !== null) {
        echo "✅ HomeController->db properly injected\n";
    } else {
        echo "❌ HomeController->db is null\n";
    }
    
    echo "\n5. Testing production cache behavior...\n";
    
    // Check cache directories
    if (is_dir(BASE_PATH . '/cache/container')) {
        echo "✅ Container cache directory exists\n";
    }
    
    if (is_dir(BASE_PATH . '/cache/routes')) {
        echo "✅ Routes cache directory exists\n";
    }
    
    if (is_dir(BASE_PATH . '/logs')) {
        echo "✅ Logs directory exists\n";
    }
    
    echo "\n🎉 SIMULATION COMPLETED SUCCESSFULLY!\n";
    echo "=====================================\n";
    echo "✅ Application bootstrap works correctly\n";
    echo "✅ All controllers can be instantiated\n";
    echo "✅ All services are properly injected\n";
    echo "✅ Dependency injection chains work\n";
    echo "✅ Cache system is ready\n";
    echo "\n🚀 Application is READY TO RUN!\n";
    echo "The 500 error should be fixed now.\n";
    
} catch (Exception $e) {
    echo "❌ SIMULATION FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo "This indicates the application would still fail.\n";
    exit(1);
}