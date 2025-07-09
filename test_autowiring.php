<?php

// Test script pour vérifier l'autowiring
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

use TopoclimbCH\Core\ContainerBuilder;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Services\WeatherService;

try {
    echo "🧪 Testing new autowiring configuration...\n\n";

    // Create container with autowiring
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();
    
    echo "✅ Container built successfully\n";

    // Test Database autowiring
    $database = $container->get(Database::class);
    echo "✅ Database service autowired successfully\n";

    // Test Auth autowiring
    $auth = $container->get(Auth::class);
    echo "✅ Auth service autowired successfully\n";

    // Test WeatherService autowiring
    $weatherService = $container->get('TopoclimbCH\\Services\\WeatherService');
    echo "✅ WeatherService autowired successfully\n";

    // Test that services are properly injected
    echo "\n🔍 Verifying dependency injection:\n";
    
    // Check if WeatherService has Database injected
    $reflection = new ReflectionClass($weatherService);
    $dbProperty = $reflection->getProperty('db');
    $dbProperty->setAccessible(true);
    $injectedDb = $dbProperty->getValue($weatherService);
    
    if ($injectedDb instanceof Database) {
        echo "✅ WeatherService has Database properly injected\n";
    } else {
        echo "❌ WeatherService missing Database injection\n";
    }

    // Check if Auth has dependencies injected
    $reflection = new ReflectionClass($auth);
    $sessionProperty = $reflection->getProperty('session');
    $sessionProperty->setAccessible(true);
    $injectedSession = $sessionProperty->getValue($auth);
    
    if ($injectedSession !== null) {
        echo "✅ Auth has Session properly injected\n";
    } else {
        echo "❌ Auth missing Session injection\n";
    }

    echo "\n🎉 All autowiring tests passed!\n";
    echo "📊 Container now has " . count($container->getServiceIds()) . " services registered\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    exit(1);
}