<?php

// Simple test to verify container can build
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

use TopoclimbCH\Core\ContainerBuilder;

try {
    echo "Testing container build...\n";
    
    // Create container
    $containerBuilder = new ContainerBuilder();
    $container = $containerBuilder->build();
    
    echo "✅ Container built successfully!\n";
    
    // Test some key services
    $services = [
        'TopoclimbCH\\Core\\Database',
        'TopoclimbCH\\Core\\Auth',
        'TopoclimbCH\\Services\\WeatherService',
        'TopoclimbCH\\Services\\DifficultyService',
        'TopoclimbCH\\Controllers\\HomeController'
    ];
    
    foreach ($services as $service) {
        try {
            $instance = $container->get($service);
            echo "✅ $service - OK\n";
        } catch (Exception $e) {
            echo "❌ $service - ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Container test completed!\n";
    
} catch (Exception $e) {
    echo "❌ Container build failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    exit(1);
}