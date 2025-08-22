<?php
/**
 * Script de FORCE REFRESH pour corriger la carte
 * À exécuter IMMÉDIATEMENT après déploiement si /map reste buggé
 */

echo "🚨 FIX CACHE CARTE - FORCE REFRESH IMMÉDIAT\n";
echo "===========================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$fixed = [];
$errors = [];

try {
    // 1. SUPPRIMER COMPLÈTEMENT le cache Twig
    echo "1. SUPPRESSION COMPLÈTE cache Twig...\n";
    $cacheDir = __DIR__ . '/storage/cache';
    if (is_dir($cacheDir)) {
        // Supprimer récursivement TOUT
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        $deleted = 0;
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                if (rmdir($file->getRealPath())) {
                    $deleted++;
                }
            } else {
                if (unlink($file->getRealPath())) {
                    $deleted++;
                }
            }
        }
        
        // Récréer le dossier vide
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        echo "   ✅ $deleted éléments cache supprimés TOTALEMENT\n";
        $fixed[] = "Cache Twig purgé complètement";
    } else {
        echo "   ⚠️ Dossier cache introuvable\n";
    }

    // 2. MODIFIER le template de carte avec timestamp UNIQUE
    echo "2. MODIFICATION template carte avec timestamp...\n";
    $mapTemplate = __DIR__ . '/resources/views/map/index.twig';
    if (file_exists($mapTemplate)) {
        $content = file_get_contents($mapTemplate);
        $timestamp = date('Y-m-d H:i:s');
        $uniqueId = uniqid();
        
        // Ajouter/Modifier le commentaire de cache bust
        $cacheBustComment = "{# CACHE BUST: $timestamp - $uniqueId #}";
        
        if (strpos($content, '{# CACHE BUST:') !== false) {
            // Remplacer l'ancien
            $content = preg_replace('/\{# CACHE BUST:.*? #\}/', $cacheBustComment, $content);
        } else {
            // Ajouter au début
            $content = $cacheBustComment . "\n" . $content;
        }
        
        if (file_put_contents($mapTemplate, $content)) {
            echo "   ✅ Template carte marqué: $timestamp - $uniqueId\n";
            $fixed[] = "Template carte modifié";
        } else {
            echo "   ❌ Impossible de modifier le template\n";
            $errors[] = "Template non modifiable";
        }
    } else {
        echo "   ❌ Template carte introuvable\n";
        $errors[] = "Template introuvable: $mapTemplate";
    }

    // 3. MODIFIER le layout fullscreen aussi
    echo "3. MODIFICATION layout fullscreen...\n";
    $layoutTemplate = __DIR__ . '/resources/views/layouts/fullscreen.twig';
    if (file_exists($layoutTemplate)) {
        $content = file_get_contents($layoutTemplate);
        $timestamp = date('Y-m-d H:i:s');
        
        // Ajouter/Modifier le commentaire HTML
        $cacheBustComment = "    <!-- FORCE REFRESH: $timestamp -->";
        
        if (strpos($content, '<!-- FORCE REFRESH:') !== false) {
            $content = preg_replace('/<!-- FORCE REFRESH:.*? -->/', $cacheBustComment, $content);
        } else {
            $content = str_replace('<head>', "<head>\n$cacheBustComment", $content);
        }
        
        if (file_put_contents($layoutTemplate, $content)) {
            echo "   ✅ Layout fullscreen marqué: $timestamp\n";
            $fixed[] = "Layout fullscreen modifié";
        }
    }

    // 4. CRÉER un fichier de force refresh
    echo "4. CRÉATION marqueur force refresh...\n";
    $refreshMarker = __DIR__ . '/FORCE_REFRESH_' . date('Ymd_His') . '.txt';
    $markerContent = "FORCE REFRESH CARTE\n";
    $markerContent .= "==================\n";
    $markerContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $markerContent .= "Problème: Cache carte persistant\n";
    $markerContent .= "Action: Templates modifiés avec timestamps uniques\n";
    $markerContent .= "Test: Comparer /test-carte.html vs /map\n\n";
    $markerContent .= "Si /map reste buggé après ça:\n";
    $markerContent .= "- Problème de cache serveur (Plesk/Apache)\n";
    $markerContent .= "- Ou cache navigateur tenace\n";
    
    file_put_contents($refreshMarker, $markerContent);
    echo "   ✅ Marqueur créé: " . basename($refreshMarker) . "\n";
    $fixed[] = "Marqueur de refresh créé";

    // 5. RESET OPCache si disponible
    echo "5. RESET OPCache...\n";
    if (function_exists('opcache_reset')) {
        if (opcache_reset()) {
            echo "   ✅ OPCache reseté\n";
            $fixed[] = "OPCache reseté";
        } else {
            echo "   ⚠️ OPCache reset échoué\n";
        }
    } else {
        echo "   ℹ️ OPCache non disponible\n";
    }

} catch (Exception $e) {
    $errors[] = "Erreur générale: " . $e->getMessage();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// Rapport final
echo "\n🎯 RÉSUMÉ FORCE REFRESH:\n";
echo "========================\n";
echo "Actions réussies: " . count($fixed) . "\n";
echo "Erreurs: " . count($errors) . "\n\n";

if (!empty($fixed)) {
    echo "✅ ACTIONS RÉUSSIES:\n";
    foreach ($fixed as $action) {
        echo "   • $action\n";
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

echo "🔥 INSTRUCTIONS APRÈS FORCE REFRESH:\n";
echo "====================================\n";
echo "1. Testez IMMÉDIATEMENT: /test-carte.html\n";
echo "2. Puis testez: /map (DOIT être identique)\n";
echo "3. Si /map reste différent:\n";
echo "   - Videz cache navigateur (Ctrl+F5)\n";
echo "   - Vérifiez cache serveur Plesk\n";
echo "   - Contactez support avec ce rapport\n\n";

$success = count($errors) === 0;
echo $success ? "🎉 FORCE REFRESH TERMINÉ AVEC SUCCÈS\n" : "⚠️ FORCE REFRESH TERMINÉ AVEC ERREURS\n";

exit($success ? 0 : 1);
?>