<?php
// Emergency cleanup for Container cache corruption
echo "<h1>🧹 Emergency Cache Cleanup</h1>";

$basePath = dirname(__DIR__);
$deleted = 0;

// Delete cache files
$cacheFiles = [
    $basePath . '/cache/container/container.php',
    $basePath . '/cache/container/',
    $basePath . '/storage/cache/',
    $basePath . '/storage/framework/cache/',
    $basePath . '/cache/'
];

foreach ($cacheFiles as $path) {
    if (file_exists($path)) {
        if (is_file($path)) {
            unlink($path);
            $deleted++;
            echo "🗑️ Deleted file: $path<br>";
        } elseif (is_dir($path)) {
            $files = glob($path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deleted++;
                    echo "🗑️ Deleted: $file<br>";
                }
            }
            echo "📁 Cleaned directory: $path<br>";
        }
    }
}

// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "🔄 OPcache cleared<br>";
}

echo "<h2>✅ Cleanup Complete</h2>";
echo "Files deleted: $deleted<br>";
echo "<a href='/'>Test Homepage</a> | <a href='/test_final.php'>Test Container</a>";
?>