<?php
/**
 * VIDAGE CACHE PROPRE - Sans interférence .htaccess
 * À utiliser maintenant que les règles cache .htaccess sont supprimées
 */

echo "🧹 VIDAGE CACHE APPLICATION PROPRE\n";
echo "===================================\n";
echo "Date: " . date("Y-m-d H:i:s") . "\n\n";

$cleaned = 0;
$errors = 0;

// 1. Cache Twig complet
if (is_dir(__DIR__ . "/storage/cache")) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . "/storage/cache", RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        try {
            if ($file->isDir()) {
                if (rmdir($file->getRealPath())) $cleaned++;
            } else {
                if (unlink($file->getRealPath())) $cleaned++;
            }
        } catch (Exception $e) {
            $errors++;
        }
    }
    echo "✅ Cache Twig: $cleaned éléments supprimés\n";
}

// 2. Sessions anciennes
if (is_dir(__DIR__ . "/storage/sessions")) {
    $sessions = glob(__DIR__ . "/storage/sessions/sess_*");
    $oldSessions = 0;
    foreach ($sessions as $session) {
        if (filemtime($session) < time() - 3600) { // 1h
            unlink($session);
            $oldSessions++;
        }
    }
    echo "✅ Sessions anciennes: $oldSessions supprimées\n";
}

// 3. OPCache
if (function_exists("opcache_reset")) {
    if (opcache_reset()) {
        echo "✅ OPCache reseté\n";
    } else {
        echo "⚠️ Échec OPCache (non critique)\n";
    }
}

// 4. Headers application pour forcer refresh
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

echo "\n🎯 VIDAGE TERMINÉ\n";
echo "Cache application: $cleaned éléments\n";
echo "Erreurs: $errors\n";
echo "\nMAINTENANT LES ROUTES PEUVENT SE RAFRAÎCHIR NORMALEMENT\n";
echo "Test: /map devrait afficher la nouvelle version\n";
?>