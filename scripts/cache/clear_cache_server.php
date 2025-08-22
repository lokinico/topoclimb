<?php
/**
 * Script pour vider le cache Twig
 * À exécuter sur le serveur de production
 */

// Chemins des caches à vider
$cachePaths = [
    __DIR__ . '/cache/views',
    __DIR__ . '/storage/cache',
    __DIR__ . '/cache'
];

echo "=== VIDAGE DU CACHE TWIG ===\n";

foreach ($cachePaths as $path) {
    if (is_dir($path)) {
        echo "Vidage de: $path\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
                $count++;
            }
        }
        
        echo "✅ $count fichiers supprimés\n";
    } else {
        echo "❌ Répertoire non trouvé: $path\n";
    }
}

echo "\n🔄 Cache vidé avec succès !\n";
echo "Rechargez la page des secteurs maintenant.\n";