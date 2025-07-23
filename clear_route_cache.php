<?php
/**
 * Clear route cache script for production issues
 */

require_once 'bootstrap.php';

echo "TopoclimbCH Route Cache Management\n";
echo "==================================\n\n";

$cacheDir = BASE_PATH . '/cache/routes';
$cacheFile = $cacheDir . '/routes.php';

echo "Cache directory: {$cacheDir}\n";
echo "Cache file: {$cacheFile}\n\n";

// Check if cache directory exists
if (!is_dir($cacheDir)) {
    echo "Cache directory does not exist. Creating...\n";
    if (mkdir($cacheDir, 0755, true)) {
        echo "✓ Cache directory created successfully\n";
    } else {
        echo "✗ Failed to create cache directory\n";
        exit(1);
    }
} else {
    echo "✓ Cache directory exists\n";
}

// Check if cache file exists
if (file_exists($cacheFile)) {
    echo "✓ Route cache file exists\n";
    echo "  Size: " . filesize($cacheFile) . " bytes\n";
    echo "  Modified: " . date('Y-m-d H:i:s', filemtime($cacheFile)) . "\n";
    
    // Read and analyze cache content
    echo "\nAnalyzing cached routes...\n";
    $cachedRoutes = require $cacheFile;
    
    if (is_array($cachedRoutes)) {
        $getRoutes = $cachedRoutes['GET'] ?? [];
        echo "Cached GET routes: " . count($getRoutes) . "\n";
        
        // Look for map routes in cache
        $hasMapRoute = false;
        $hasMapNewRoute = false;
        
        foreach ($getRoutes as $pattern => $routeData) {
            $path = $routeData['path'] ?? 'unknown';
            if ($path === '/map') {
                $hasMapRoute = true;
                echo "✓ Found /map route in cache\n";
            }
            if ($path === '/map-new') {
                $hasMapNewRoute = true;
                echo "✓ Found /map-new route in cache\n";
            }
        }
        
        if (!$hasMapRoute) {
            echo "✗ /map route NOT found in cache\n";
        }
        if (!$hasMapNewRoute) {
            echo "✗ /map-new route NOT found in cache\n";
        }
        
        // If /map-new is missing, clear the cache
        if (!$hasMapNewRoute) {
            echo "\n⚠ /map-new route missing from cache. Clearing cache...\n";
            if (unlink($cacheFile)) {
                echo "✓ Cache file deleted successfully\n";
                echo "The cache will be regenerated on the next request.\n";
            } else {
                echo "✗ Failed to delete cache file\n";
            }
        } else {
            echo "\nCache appears to be complete. To force regeneration, delete manually:\n";
            echo "rm {$cacheFile}\n";
        }
    } else {
        echo "✗ Cache file contains invalid data. Deleting...\n";
        if (unlink($cacheFile)) {
            echo "✓ Invalid cache file deleted\n";
        } else {
            echo "✗ Failed to delete invalid cache file\n";
        }
    }
} else {
    echo "ℹ Route cache file does not exist (normal for development mode)\n";
}

// Show current environment
echo "\nEnvironment Information:\n";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'Not set') . "\n";

// Load dotenv if not already loaded
if (!isset($_ENV['APP_ENV'])) {
    echo "Loading environment variables...\n";
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
    echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'Still not set') . "\n";
}

echo "\nDone.\n";