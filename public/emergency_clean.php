<?php

/**
 * Script d'urgence pour nettoyer le cache du container
 */

echo "<h1>🧹 Nettoyage d'urgence du cache</h1>";

$basePath = dirname(__DIR__);
$deletedFiles = 0;

// Fonction récursive pour supprimer tous les fichiers
function deleteAllFiles($dir) {
    global $deletedFiles;
    
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $fullPath = $dir . '/' . $file;
        
        if (is_file($fullPath)) {
            unlink($fullPath);
            $deletedFiles++;
            echo "🗑️ Supprimé: $fullPath<br>";
        } elseif (is_dir($fullPath)) {
            deleteAllFiles($fullPath);
            rmdir($fullPath);
            echo "📁 Dossier supprimé: $fullPath<br>";
        }
    }
}

// Répertoires à nettoyer complètement
$cacheDirs = [
    $basePath . '/storage/cache',
    $basePath . '/storage/framework/cache',
    $basePath . '/storage/framework/views',
    $basePath . '/cache'
];

echo "<h2>🗂️ Nettoyage des répertoires de cache</h2>";

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        echo "<p>📁 Nettoyage de: $dir</p>";
        deleteAllFiles($dir);
    } else {
        echo "<p>⚠️ Répertoire non trouvé: $dir</p>";
    }
}

// Recherche et suppression de fichiers spécifiques
echo "<h2>🔍 Recherche de fichiers CachedContainer</h2>";

function findAndDeleteCachedContainer($dir) {
    global $deletedFiles;
    
    if (!is_dir($dir)) {
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        $filename = $file->getFilename();
        $filepath = $file->getPathname();
        
        // Supprimer tous les fichiers contenant "cached" ou "container"
        if (stripos($filename, 'cached') !== false || 
            stripos($filename, 'container') !== false ||
            stripos($filename, 'CachedContainer') !== false) {
            
            if (is_file($filepath)) {
                unlink($filepath);
                $deletedFiles++;
                echo "🎯 Supprimé fichier spécifique: $filepath<br>";
            }
        }
    }
}

// Rechercher dans tout le projet
findAndDeleteCachedContainer($basePath);

// Nettoyer OPcache si disponible
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p>🔄 OPcache réinitialisé</p>";
}

echo "<h2>✅ Nettoyage terminé</h2>";
echo "<p><strong>Fichiers supprimés: $deletedFiles</strong></p>";
echo "<p style='color: green;'>🚀 Vous pouvez maintenant tester votre site !</p>";
echo "<p><a href='/'>Tester la page d'accueil</a></p>";

?>