<?php

/**
 * Script de nettoyage du cache OPcache pour déploiement Plesk
 * Compatible avec les paramètres PHP topoclimb.ch
 */

echo "🧹 Nettoyage du cache OPcache pour déploiement...\n";

// 1. Nettoyer le cache OPcache (disponible car opcache.enable=on)
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ Cache OPcache réinitialisé\n";
    } else {
        echo "❌ Erreur lors de la réinitialisation du cache OPcache\n";
    }
} else {
    echo "ℹ️ OPcache non disponible ou désactivé\n";
}

// 2. Nettoyer le cache de réalisation (realpath cache)
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "✅ Cache de réalisation nettoyé\n";
}

// 3. Forcer le rechargement des classes PHP
if (function_exists('opcache_invalidate')) {
    $filesToInvalidate = [
        __DIR__ . '/bootstrap.php',
        __DIR__ . '/public/index.php',
        __DIR__ . '/src/Core/Application.php',
        __DIR__ . '/src/Core/Router.php',
        __DIR__ . '/src/Core/Container.php'
    ];
    
    foreach ($filesToInvalidate as $file) {
        if (file_exists($file)) {
            opcache_invalidate($file, true);
            echo "🔄 Invalidé: " . basename($file) . "\n";
        }
    }
}

// 4. Nettoyer les dossiers de cache applicatif
$cacheDirs = [
    __DIR__ . '/storage/cache',
    __DIR__ . '/storage/framework/cache',
    __DIR__ . '/storage/framework/views',
    __DIR__ . '/storage/sessions'
];

$deletedFiles = 0;

foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    
    echo "📁 Nettoyage de: $dir\n";
    
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedFiles++;
        }
    }
}

// 5. Nettoyer spécifiquement les fichiers de cache problématiques
$patterns = [
    __DIR__ . '/storage/**/CachedContainer*',
    __DIR__ . '/storage/**/cached_container*',
    __DIR__ . '/**/CachedContainer*.php',
    sys_get_temp_dir() . '/CachedContainer*'
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

// 6. Vider le cache des sessions PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();

echo "✅ Nettoyage terminé: $deletedFiles fichiers supprimés\n";
echo "🚀 Cache complètement nettoyé pour le déploiement\n";

?>