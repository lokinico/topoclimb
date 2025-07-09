<?php

/**
 * Clear cache script for TopoclimbCH
 * Usage: php clear_cache.php
 */

// Define BASE_PATH
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

function clearCache(): void
{
    $cacheDirectories = [
        BASE_PATH . '/cache/container',
        BASE_PATH . '/cache/routes',
        BASE_PATH . '/cache/views',
    ];

    $filesDeleted = 0;
    
    foreach ($cacheDirectories as $directory) {
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $filesDeleted++;
                    echo "Deleted: $file\n";
                }
            }
        }
    }
    
    echo "\n✅ Cache cleared successfully! $filesDeleted files deleted.\n";
    echo "🔄 Next request will rebuild cache in production mode.\n";
}

function warmupCache(): void
{
    echo "🔥 Warming up cache...\n";
    
    // Set environment to production for cache generation
    $_ENV['APP_ENV'] = 'production';
    
    // Load composer autoloader
    require_once BASE_PATH . '/vendor/autoload.php';
    
    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
    
    try {
        // Create container - this will cache it
        $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
        $container = $containerBuilder->build();
        echo "✅ Container cached\n";
        
        // Create router and load routes - this will cache them
        $router = $container->get(\TopoclimbCH\Core\Router::class);
        $router->loadRoutes(BASE_PATH . '/config/routes.php');
        echo "✅ Routes cached\n";
        
        echo "🎉 Cache warmed up successfully!\n";
    } catch (Exception $e) {
        echo "❌ Error warming up cache: " . $e->getMessage() . "\n";
    }
}

// Parse command line arguments
$action = $argv[1] ?? 'clear';

switch ($action) {
    case 'clear':
        clearCache();
        break;
    case 'warmup':
        warmupCache();
        break;
    case 'rebuild':
        clearCache();
        warmupCache();
        break;
    default:
        echo "Usage: php clear_cache.php [clear|warmup|rebuild]\n";
        echo "  clear   - Clear all cache files\n";
        echo "  warmup  - Generate cache files\n";
        echo "  rebuild - Clear and regenerate cache files\n";
        break;
}