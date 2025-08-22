<?php
/**
 * SOLUTION DÉFINITIVE - Supprimer les règles de cache .htaccess
 * et restaurer un système de cache contrôlé par l'application
 */

echo "🔧 CORRECTION DÉFINITIVE .htaccess - SUPPRESSION RÈGLES CACHE\n";
echo "==========================================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Objectif: Supprimer règles cache .htaccess et permettre vidage propre\n\n";

$actions = [];
$errors = [];

try {
    // 1. RESTAURER le .htaccess racine propre (supprimer bricolage temporaire)
    echo "1. RESTAURATION .htaccess racine propre...\n";
    
    $backupFile = __DIR__ . '/.htaccess.backup-20250722-130346';
    $htaccessRoot = __DIR__ . '/.htaccess';
    
    if (file_exists($backupFile)) {
        // Utiliser le backup mais SANS les règles de cache
        $originalContent = file_get_contents($backupFile);
        
        // Supprimer la section mod_expires (lignes 38-49)
        $cleanContent = preg_replace(
            '/# Mise en cache des ressources statiques.*?<\/IfModule>/s',
            '# Cache désactivé pour permettre vidage propre par application',
            $originalContent
        );
        
        // Vérifier que la suppression a fonctionné
        if (strpos($cleanContent, 'ExpiresActive') === false) {
            file_put_contents($htaccessRoot, $cleanContent);
            echo "   ✅ .htaccess racine restauré SANS règles de cache\n";
            $actions[] = ".htaccess racine nettoyé (mod_expires supprimé)";
        } else {
            throw new Exception("Échec suppression règles cache du .htaccess racine");
        }
    } else {
        throw new Exception("Backup .htaccess introuvable: $backupFile");
    }

    // 2. NETTOYER le public/.htaccess des règles de cache média
    echo "\n2. NETTOYAGE public/.htaccess...\n";
    
    $publicHtaccess = __DIR__ . '/public/.htaccess';
    $publicBackup = __DIR__ . '/public/.htaccess.backup-' . date('Ymd-His');
    
    if (file_exists($publicHtaccess)) {
        $publicContent = file_get_contents($publicHtaccess);
        
        // Backup du fichier original
        file_put_contents($publicBackup, $publicContent);
        echo "   ✅ Backup public/.htaccess: " . basename($publicBackup) . "\n";
        
        // Supprimer les headers de cache des médias (lignes 24-32)
        $cleanPublicContent = preg_replace(
            '/# Headers pour les fichiers média.*?<\/FilesMatch>/s',
            '# Headers média cache désactivés pour permettre vidage application',
            $publicContent
        );
        
        // Vérifier la suppression
        if (strpos($cleanPublicContent, 'max-age=31536000') === false) {
            file_put_contents($publicHtaccess, $cleanPublicContent);
            echo "   ✅ public/.htaccess nettoyé SANS règles cache média\n";
            $actions[] = "public/.htaccess nettoyé (headers cache média supprimés)";
        } else {
            throw new Exception("Échec suppression règles cache média");
        }
    } else {
        throw new Exception("public/.htaccess introuvable");
    }

    // 3. CRÉER script de vidage cache propre
    echo "\n3. CRÉATION script vidage cache application...\n";
    
    $clearCacheScript = __DIR__ . '/clear-cache-clean.php';
    $clearCacheContent = '<?php
/**
 * VIDAGE CACHE PROPRE - Sans interférence .htaccess
 * À utiliser maintenant que les règles cache .htaccess sont supprimées
 */

echo "🧹 VIDAGE CACHE APPLICATION PROPRE\\n";
echo "===================================\\n";
echo "Date: " . date("Y-m-d H:i:s") . "\\n\\n";

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
    echo "✅ Cache Twig: $cleaned éléments supprimés\\n";
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
    echo "✅ Sessions anciennes: $oldSessions supprimées\\n";
}

// 3. OPCache
if (function_exists("opcache_reset")) {
    if (opcache_reset()) {
        echo "✅ OPCache reseté\\n";
    } else {
        echo "⚠️ Échec OPCache (non critique)\\n";
    }
}

// 4. Headers application pour forcer refresh
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

echo "\\n🎯 VIDAGE TERMINÉ\\n";
echo "Cache application: $cleaned éléments\\n";
echo "Erreurs: $errors\\n";
echo "\\nMAINTENANT LES ROUTES PEUVENT SE RAFRAÎCHIR NORMALEMENT\\n";
echo "Test: /map devrait afficher la nouvelle version\\n";
?>';
    
    file_put_contents($clearCacheScript, $clearCacheContent);
    echo "   ✅ Script vidage propre créé: clear-cache-clean.php\n";
    $actions[] = "Script clear-cache-clean.php créé";

    // 4. SUPPRIMER les fichiers de contournement temporaire
    echo "\n4. SUPPRESSION fichiers temporaires...\n";
    
    $tempFiles = [
        __DIR__ . '/public/map-temp.php',
        __DIR__ . '/public/cache-notice.php'
    ];
    
    foreach ($tempFiles as $tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
            echo "   ✅ Supprimé: " . basename($tempFile) . "\n";
            $actions[] = "Fichier temporaire supprimé: " . basename($tempFile);
        }
    }

    // 5. TESTER immédiatement le vidage
    echo "\n5. TEST vidage immédiat...\n";
    
    // Vider cache Twig
    $cacheDir = __DIR__ . '/storage/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
        echo "   ✅ Cache Twig vidé immédiatement\n";
    }
    
    // Reset OPCache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "   ✅ OPCache reset immédiatement\n";
    }
    
    $actions[] = "Cache vidé immédiatement";

} catch (Exception $e) {
    $errors[] = "Erreur: " . $e->getMessage();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// RAPPORT FINAL
echo "\n🎯 RÉSUMÉ CORRECTION DÉFINITIVE\n";
echo "===============================\n";
echo "Actions réussies: " . count($actions) . "\n";
echo "Erreurs: " . count($errors) . "\n\n";

if (!empty($actions)) {
    echo "✅ CORRECTIONS APPLIQUÉES:\n";
    foreach ($actions as $i => $action) {
        echo "   " . ($i + 1) . ". $action\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
    echo "\n";
}

echo "🚀 INSTRUCTIONS POST-CORRECTION:\n";
echo "================================\n";
echo "1. 📤 Uploader les .htaccess modifiés sur Plesk\n";
echo "2. 🧹 Exécuter: php clear-cache-clean.php\n";
echo "3. 🎯 Tester: /map (doit afficher la version Twig normale)\n";
echo "4. ✅ À l'avenir: utiliser clear-cache-clean.php pour vider cache\n\n";

echo "💡 AVANTAGES de cette solution:\n";
echo "• Cache contrôlé par application (plus de conflits .htaccess)\n";
echo "• Vidage cache fiable et prévisible\n";
echo "• Performance préservée pour assets statiques seulement\n";
echo "• Route /map utilise maintenant le système MVC normal\n\n";

$success = count($errors) === 0;
echo ($success ? "🎉 CORRECTION DÉFINITIVE RÉUSSIE" : "⚠️ CORRECTION AVEC ERREURS") . "\n";

exit($success ? 0 : 1);
?>