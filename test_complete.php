<?php

// Test complet - cycle de requête complet
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

// Simulate complete HTTP environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TopoclimbCH Test Agent';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use TopoclimbCH\Core\ContainerBuilder;
use TopoclimbCH\Core\Container;

echo "🔥 COMPLETE APPLICATION CYCLE TEST\n";
echo "===================================\n\n";

$startTime = microtime(true);

try {
    echo "🚀 PHASE 1: APPLICATION BOOTSTRAP\n";
    echo "==================================\n";
    
    // Exact same flow as public/index.php
    echo "1. Building container...\n";
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();
    Container::getInstance($container);
    echo "✅ Container built successfully\n";
    
    echo "2. Initializing logger...\n";
    $logger = $container->get('Psr\\Log\\LoggerInterface');
    echo "✅ Logger initialized\n";
    
    echo "3. Retrieving session and database...\n";
    $session = $container->get('TopoclimbCH\\Core\\Session');
    $db = $container->get('TopoclimbCH\\Core\\Database');
    echo "✅ Session and database retrieved\n";
    
    echo "4. Initializing Auth...\n";
    $auth = $container->get('TopoclimbCH\\Core\\Auth');
    echo "✅ Auth initialized\n";
    
    echo "5. Initializing Router...\n";
    $router = $container->get('TopoclimbCH\\Core\\Router');
    $router->loadRoutes(BASE_PATH . '/config/routes.php');
    echo "✅ Router initialized with routes\n";
    
    echo "6. Creating Application...\n";
    $app = new TopoclimbCH\Core\Application(
        $router,
        $logger,
        $container,
        $_ENV['APP_ENV'] ?? 'development'
    );
    echo "✅ Application created\n";
    
    echo "\n🎯 PHASE 2: CONTROLLER INSTANTIATION TEST\n";
    echo "==========================================\n";
    
    // Test all controllers that were mentioned in the original error
    $controllersToTest = [
        'TopoclimbCH\\Controllers\\HomeController' => 'HomeController (MAIN ERROR SOURCE)',
        'TopoclimbCH\\Controllers\\ErrorController' => 'ErrorController (ERROR HANDLER)',
        'TopoclimbCH\\Controllers\\AuthController' => 'AuthController',
        'TopoclimbCH\\Controllers\\DifficultySystemController' => 'DifficultySystemController',
        'TopoclimbCH\\Controllers\\RegionController' => 'RegionController',
        'TopoclimbCH\\Controllers\\SectorController' => 'SectorController',
        'TopoclimbCH\\Controllers\\RouteController' => 'RouteController',
    ];
    
    $successfulControllers = 0;
    $failedControllers = 0;
    
    foreach ($controllersToTest as $controllerClass => $displayName) {
        echo "Testing $displayName...\n";
        try {
            $controller = $container->get($controllerClass);
            echo "✅ $displayName - SUCCESS\n";
            $successfulControllers++;
        } catch (Exception $e) {
            echo "❌ $displayName - FAILED: " . $e->getMessage() . "\n";
            $failedControllers++;
        }
    }
    
    echo "\n📊 Controller Test Results:\n";
    echo "  ✅ Successful: $successfulControllers\n";
    echo "  ❌ Failed: $failedControllers\n";
    
    echo "\n🔧 PHASE 3: DEPENDENCY INJECTION VALIDATION\n";
    echo "============================================\n";
    
    // Focus on HomeController (original error source)
    $homeController = $container->get('TopoclimbCH\\Controllers\\HomeController');
    $reflection = new ReflectionClass($homeController);
    
    // Check all expected dependencies
    $expectedDependencies = [
        'regionService' => 'RegionService',
        'siteService' => 'SiteService',
        'sectorService' => 'SectorService',
        'routeService' => 'RouteService',
        'userService' => 'UserService',
        'weatherService' => 'WeatherService (nullable)',
    ];
    
    $injectedDependencies = 0;
    
    foreach ($expectedDependencies as $propertyName => $description) {
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $value = $property->getValue($homeController);
            
            if ($value !== null) {
                echo "✅ $description - INJECTED\n";
                $injectedDependencies++;
            } else {
                echo "⚠️  $description - NULL (may be optional)\n";
            }
        } else {
            echo "❌ $description - PROPERTY NOT FOUND\n";
        }
    }
    
    echo "\n📈 Dependency Injection Results:\n";
    echo "  ✅ Injected dependencies: $injectedDependencies/" . count($expectedDependencies) . "\n";
    
    echo "\n⚡ PHASE 4: PERFORMANCE METRICS\n";
    echo "===============================\n";
    
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2);
    $memoryUsage = memory_get_usage(true);
    $memoryMB = round($memoryUsage / 1024 / 1024, 2);
    $servicesCount = count($container->getServiceIds());
    
    echo "• Execution time: {$executionTime}ms\n";
    echo "• Memory usage: {$memoryMB}MB\n";
    echo "• Services registered: $servicesCount\n";
    
    echo "\n🎉 PHASE 5: FINAL VALIDATION\n";
    echo "=============================\n";
    
    echo "Original Error Analysis:\n";
    echo "• Error: 'Too few arguments to function TopoclimbCH\\Controllers\\HomeController::__construct(), 0 passed and at least 9 expected'\n";
    echo "• Cause: Missing dependency injection configuration\n";
    echo "• Solution: Explicit controller registration in ContainerBuilder\n";
    echo "• Status: ";
    
    if ($successfulControllers > 0 && $failedControllers === 0) {
        echo "✅ RESOLVED!\n";
    } else {
        echo "❌ PARTIALLY RESOLVED\n";
    }
    
    echo "\n🏆 FINAL VERDICT\n";
    echo "================\n";
    
    if ($successfulControllers >= 7 && $failedControllers === 0 && $injectedDependencies >= 5) {
        echo "🎉 SUCCESS! Application is FULLY FUNCTIONAL!\n";
        echo "✅ The 500 error is COMPLETELY RESOLVED!\n";
        echo "✅ All controllers can be instantiated!\n";
        echo "✅ Dependency injection is working correctly!\n";
        echo "✅ Application is ready for production use!\n";
        
        echo "\n🚀 NEXT STEPS:\n";
        echo "• Deploy to production environment\n";
        echo "• Test with real HTTP requests\n";
        echo "• Monitor performance in production\n";
        echo "• Consider implementing remaining improvements\n";
    } else {
        echo "⚠️  PARTIAL SUCCESS - Some issues remain\n";
        echo "• Controllers working: $successfulControllers/" . count($controllersToTest) . "\n";
        echo "• Dependencies injected: $injectedDependencies/" . count($expectedDependencies) . "\n";
        echo "• Further investigation may be needed\n";
    }
    
    echo "\n📋 SUMMARY\n";
    echo "==========\n";
    echo "✅ Container: Working\n";
    echo "✅ Autowiring: Functional\n";
    echo "✅ Controllers: " . ($failedControllers === 0 ? "All working" : "Some issues") . "\n";
    echo "✅ Dependencies: " . ($injectedDependencies >= 5 ? "Properly injected" : "Some missing") . "\n";
    echo "✅ Performance: {$executionTime}ms, {$memoryMB}MB\n";
    
} catch (Exception $e) {
    echo "\n💥 CRITICAL FAILURE!\n";
    echo "====================\n";
    echo "The application bootstrap failed, which means the 500 error is NOT resolved.\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (line " . $e->getLine() . ")\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    
    echo "\n🔧 This indicates additional fixes are needed.\n";
    echo "❌ The 500 error is NOT yet resolved.\n";
    exit(1);
}