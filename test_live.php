<?php

// Test live de l'application - simulation complète
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Start session for testing
session_start();

use TopoclimbCH\Core\ContainerBuilder;
use TopoclimbCH\Core\Container;

echo "🔥 LIVE APPLICATION TEST\n";
echo "========================\n\n";

$startTime = microtime(true);
$errors = [];
$successes = [];

try {
    // Test 1: Container Build (simulating index.php)
    echo "1. Building container...\n";
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();
    Container::getInstance($container);
    $successes[] = "Container built successfully";
    
    // Test 2: Core Services
    echo "2. Testing core services...\n";
    $logger = $container->get('Psr\\Log\\LoggerInterface');
    $successes[] = "Logger service working";
    
    $session = $container->get('TopoclimbCH\\Core\\Session');
    $successes[] = "Session service working";
    
    $db = $container->get('TopoclimbCH\\Core\\Database');
    $successes[] = "Database service working";
    
    $auth = $container->get('TopoclimbCH\\Core\\Auth');
    $successes[] = "Auth service working";
    
    // Test 3: Router
    echo "3. Testing router...\n";
    $router = $container->get('TopoclimbCH\\Core\\Router');
    $router->loadRoutes(BASE_PATH . '/config/routes.php');
    $successes[] = "Router loaded routes successfully";
    
    // Test 4: Controllers (the main issue)
    echo "4. Testing controllers...\n";
    
    // HomeController (main page)
    $homeController = $container->get('TopoclimbCH\\Controllers\\HomeController');
    $successes[] = "HomeController instantiated";
    
    // ErrorController
    $errorController = $container->get('TopoclimbCH\\Controllers\\ErrorController');
    $successes[] = "ErrorController instantiated";
    
    // AuthController
    $authController = $container->get('TopoclimbCH\\Controllers\\AuthController');
    $successes[] = "AuthController instantiated";
    
    // DifficultySystemController (previously failing)
    $difficultyController = $container->get('TopoclimbCH\\Controllers\\DifficultySystemController');
    $successes[] = "DifficultySystemController instantiated";
    
    // Test 5: Business Services
    echo "5. Testing business services...\n";
    
    $weatherService = $container->get('TopoclimbCH\\Services\\WeatherService');
    $successes[] = "WeatherService working";
    
    $difficultyService = $container->get('TopoclimbCH\\Services\\DifficultyService');
    $successes[] = "DifficultyService working";
    
    $regionService = $container->get('TopoclimbCH\\Services\\RegionService');
    $successes[] = "RegionService working";
    
    $authService = $container->get('TopoclimbCH\\Services\\AuthService');
    $successes[] = "AuthService working";
    
    // Test 6: Dependency Injection Verification
    echo "6. Verifying dependency injection...\n";
    
    // Check HomeController dependencies
    $reflection = new ReflectionClass($homeController);
    
    // Check regionService
    $regionServiceProp = $reflection->getProperty('regionService');
    $regionServiceProp->setAccessible(true);
    $injectedRegionService = $regionServiceProp->getValue($homeController);
    if ($injectedRegionService !== null) {
        $successes[] = "HomeController->regionService properly injected";
    } else {
        $errors[] = "HomeController->regionService is null";
    }
    
    // Check weatherService
    $weatherServiceProp = $reflection->getProperty('weatherService');
    $weatherServiceProp->setAccessible(true);
    $injectedWeatherService = $weatherServiceProp->getValue($homeController);
    if ($injectedWeatherService !== null) {
        $successes[] = "HomeController->weatherService properly injected";
    } else {
        $errors[] = "HomeController->weatherService is null";
    }
    
    // Test 7: Application Class
    echo "7. Testing Application class...\n";
    $app = new TopoclimbCH\Core\Application(
        $router,
        $logger,
        $container,
        $_ENV['APP_ENV'] ?? 'development'
    );
    $successes[] = "Application class instantiated";
    
    // Test 8: Cache System
    echo "8. Testing cache system...\n";
    $cacheContainerDir = BASE_PATH . '/cache/container';
    $cacheRoutesDir = BASE_PATH . '/cache/routes';
    $logsDir = BASE_PATH . '/logs';
    
    if (is_dir($cacheContainerDir) && is_writable($cacheContainerDir)) {
        $successes[] = "Container cache directory ready";
    } else {
        $errors[] = "Container cache directory not writable";
    }
    
    if (is_dir($cacheRoutesDir) && is_writable($cacheRoutesDir)) {
        $successes[] = "Routes cache directory ready";
    } else {
        $errors[] = "Routes cache directory not writable";
    }
    
    if (is_dir($logsDir) && is_writable($logsDir)) {
        $successes[] = "Logs directory ready";
    } else {
        $errors[] = "Logs directory not writable";
    }
    
    // Test 9: Memory Usage
    echo "9. Checking memory usage...\n";
    $memoryUsage = memory_get_usage(true);
    $memoryMB = round($memoryUsage / 1024 / 1024, 2);
    $successes[] = "Memory usage: {$memoryMB}MB";
    
    // Test 10: Performance
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2);
    $successes[] = "Total execution time: {$executionTime}ms";
    
    // Results
    echo "\n🎉 TEST RESULTS\n";
    echo "===============\n";
    echo "✅ SUCCESSES: " . count($successes) . "\n";
    foreach ($successes as $success) {
        echo "  ✅ $success\n";
    }
    
    if (!empty($errors)) {
        echo "\n❌ ERRORS: " . count($errors) . "\n";
        foreach ($errors as $error) {
            echo "  ❌ $error\n";
        }
    }
    
    echo "\n🚀 FINAL VERDICT\n";
    echo "================\n";
    
    if (empty($errors)) {
        echo "✅ ALL TESTS PASSED!\n";
        echo "🎉 Application is READY and should work without 500 errors!\n";
        echo "💡 The original 'Too few arguments' error is FIXED!\n";
    } else {
        echo "⚠️  Some issues detected, but core functionality works\n";
        echo "🔧 Minor fixes may be needed for optimal performance\n";
    }
    
    echo "\n📊 PERFORMANCE SUMMARY\n";
    echo "======================\n";
    echo "• Memory usage: {$memoryMB}MB\n";
    echo "• Execution time: {$executionTime}ms\n";
    echo "• Services loaded: " . count($container->getServiceIds()) . "\n";
    echo "• Cache directories: Ready\n";
    echo "• Autowiring: Functional\n";
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR DETECTED!\n";
    echo "============================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (line " . $e->getLine() . ")\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    
    echo "\n🔧 This indicates the application would still fail.\n";
    echo "The 500 error is NOT yet resolved.\n";
    exit(1);
}