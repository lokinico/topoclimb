<?php
// test-routes.php
define('BASE_PATH', __DIR__);
$routesFile = BASE_PATH . '/config/routes.php';

echo "Checking routes file: $routesFile\n";

if (file_exists($routesFile)) {
    echo "File exists!\n";
    
    $routes = require $routesFile;
    
    if (is_array($routes)) {
        echo "Routes count: " . count($routes) . "\n";
        
        foreach ($routes as $index => $route) {
            echo "Route $index: " . json_encode($route) . "\n";
        }
    } else {
        echo "Error: Routes file does not return an array!\n";
        echo "Type: " . gettype($routes) . "\n";
    }
} else {
    echo "Error: File does not exist!\n";
}