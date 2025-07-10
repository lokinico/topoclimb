<?php

/**
 * Script de nettoyage du cache du container Symfony
 * Résout l'erreur: Return value must be of type ContainerBuilder, CachedContainer returned
 */

echo "🧹 Nettoyage du cache du container Symfony...\n";

// Dossiers de cache à nettoyer
$cacheDirs = [
    __DIR__ . '/storage/cache',
    __DIR__ . '/storage/framework/cache',
    __DIR__ . '/storage/framework/views',
    sys_get_temp_dir()
];

$deletedFiles = 0;

foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    
    echo "📁 Nettoyage de: $dir\n";
    
    // Supprimer tous les fichiers de cache
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedFiles++;
        } elseif (is_dir($file)) {
            // Supprimer récursivement les sous-dossiers
            $subFiles = glob($file . '/*');
            foreach ($subFiles as $subFile) {
                if (is_file($subFile)) {
                    unlink($subFile);
                    $deletedFiles++;
                }
            }
            @rmdir($file);
        }
    }
}

// Rechercher et supprimer spécifiquement les fichiers CachedContainer
$patterns = [
    __DIR__ . '/storage/**/CachedContainer*',
    __DIR__ . '/storage/**/cached_container*',
    __DIR__ . '/**/CachedContainer*.php',
    __DIR__ . '/**/cached_container*.php'
];

foreach ($patterns as $pattern) {
    $files = glob($pattern, GLOB_BRACE);
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedFiles++;
            echo "🗑️ Supprimé: " . basename($file) . "\n";
        }
    }
}

// Nettoyer le cache PHP OPcache si disponible
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "🔄 Cache OPcache réinitialisé\n";
}

echo "✅ Nettoyage terminé: $deletedFiles fichiers supprimés\n";
echo "🚀 Le container sera reconstruit à la prochaine requête\n";

?>