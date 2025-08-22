<?php
/**
 * SOLUTION RADICALE - Force refresh serveur Plesk
 * Attaque tous les niveaux de cache possibles
 */

echo "🚨 FORCE REFRESH SERVEUR PLESK - SOLUTION RADICALE\n";
echo "=================================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Cible: Tous les caches serveur qui bloquent /map\n\n";

$actions = [];
$errors = [];

try {
    // 1. CRÉER .htaccess anti-cache AGRESSIF pour /map
    echo "1. CRÉATION .htaccess anti-cache agressif...\n";
    $htaccessContent = '# FORCE NO CACHE - ' . date('Y-m-d H:i:s') . '
<IfModule mod_headers.c>
    # Headers anti-cache agressifs
    Header always set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
    Header always set Pragma "no-cache"
    Header always set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
    Header always set Last-Modified "' . gmdate('D, d M Y H:i:s') . ' GMT"
    Header always set ETag ""
    Header unset ETag
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive Off
</IfModule>

# Désactiver cache pour routes spécifiques
<LocationMatch "/map">
    Header always set Cache-Control "no-cache, no-store, must-revalidate"
    Header always set Pragma "no-cache"
    Header always set Expires "0"
</LocationMatch>
';
    
    $htaccessPath = __DIR__ . '/.htaccess';
    $backupPath = __DIR__ . '/.htaccess.backup-' . date('Ymd-His');
    
    // Backup ancien .htaccess si existe
    if (file_exists($htaccessPath)) {
        copy($htaccessPath, $backupPath);
        echo "   ✅ Backup ancien .htaccess: " . basename($backupPath) . "\n";
    }
    
    // Ajouter les règles anti-cache
    if (file_exists($htaccessPath)) {
        $existing = file_get_contents($htaccessPath);
        $newContent = $htaccessContent . "\n\n# ANCIEN CONTENU:\n" . $existing;
    } else {
        $newContent = $htaccessContent;
    }
    
    file_put_contents($htaccessPath, $newContent);
    echo "   ✅ .htaccess mis à jour avec headers anti-cache\n";
    $actions[] = ".htaccess modifié avec headers anti-cache agressifs";

    // 2. MODIFIER le contrôleur pour headers PHP anti-cache
    echo "\n2. MODIFICATION MapController avec headers anti-cache...\n";
    $controllerPath = __DIR__ . '/src/Controllers/MapController.php';
    
    if (file_exists($controllerPath)) {
        $controllerContent = file_get_contents($controllerPath);
        $backupController = __DIR__ . '/src/Controllers/MapController.php.backup-' . date('Ymd-His');
        file_put_contents($backupController, $controllerContent);
        
        // Ajouter headers anti-cache au début de la méthode index
        $searchFor = 'public function index()';
        $replaceWith = 'public function index()
    {
        // HEADERS ANTI-CACHE AGRESSIFS - ' . date('Y-m-d H:i:s') . '
        header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        
        // Suite méthode originale';
        
        if (strpos($controllerContent, $searchFor) !== false) {
            $newControllerContent = str_replace($searchFor . "\n    {", $replaceWith, $controllerContent);
            file_put_contents($controllerPath, $newControllerContent);
            echo "   ✅ MapController modifié avec headers anti-cache\n";
            $actions[] = "MapController modifié avec headers PHP anti-cache";
        } else {
            echo "   ⚠️ Méthode index() non trouvée dans MapController\n";
        }
    } else {
        echo "   ❌ MapController non trouvé\n";
        $errors[] = "MapController manquant";
    }

    // 3. CRÉER fichier de test unique avec timestamp
    echo "\n3. CRÉATION test unique avec timestamp...\n";
    $timestamp = time();
    $testFileName = "map-test-$timestamp.php";
    $testFilePath = __DIR__ . "/public/$testFileName";
    
    $testContent = '<?php
// TEST UNIQUE TIMESTAMP: ' . $timestamp . '
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html><head><title>Test ' . $timestamp . '</title></head>
<body>
<h1>🎯 TEST UNIQUE: ' . date('Y-m-d H:i:s') . '</h1>
<p>Timestamp: ' . $timestamp . '</p>
<p>Si vous voyez ce contenu = serveur exécute PHP</p>
<p>Si contenu identique à /map = cache serveur confirmé</p>
</body></html>';
    
    file_put_contents($testFilePath, $testContent);
    echo "   ✅ Test unique créé: $testFileName\n";
    $actions[] = "Test unique avec timestamp créé";

    // 4. VIDER tous les caches possibles
    echo "\n4. VIDAGE tous les caches...\n";
    
    // Cache Twig
    $cacheDir = __DIR__ . '/storage/cache';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        foreach ($cacheFiles as $file) {
            if (is_file($file)) unlink($file);
        }
        echo "   ✅ Cache Twig vidé\n";
    }
    
    // Sessions
    $sessionDir = __DIR__ . '/storage/sessions';
    if (is_dir($sessionDir)) {
        $sessionFiles = glob($sessionDir . '/sess_*');
        foreach ($sessionFiles as $file) {
            if (is_file($file)) unlink($file);
        }
        echo "   ✅ Sessions vidées\n";
    }
    
    // OPCache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "   ✅ OPCache reset\n";
    }
    
    $actions[] = "Tous les caches application vidés";

    // 5. CRÉER directive pour Plesk
    echo "\n5. CRÉATION directive Plesk...\n";
    $pleskDirective = '# DIRECTIVE PLESK ANTI-CACHE - ' . date('Y-m-d H:i:s') . '

# Désactiver tous les modules de cache Apache
<IfModule mod_cache.c>
    CacheDisable /
</IfModule>

<IfModule mod_cache_disk.c>
    CacheDisable /
</IfModule>

# Headers HTTP agressifs
<IfModule mod_headers.c>
    Header always set X-Force-Refresh "' . $timestamp . '"
    Header always set Cache-Control "no-cache, no-store, must-revalidate"
    Header always set Pragma "no-cache"
    Header always set Expires "0"
</IfModule>

# Désactiver ETag
FileETag None

# Version PHP avec timestamp
Header always set X-PHP-Version "' . PHP_VERSION . '-' . $timestamp . '"
';
    
    file_put_contents(__DIR__ . '/plesk-directive-' . $timestamp . '.txt', $pleskDirective);
    echo "   ✅ Directive Plesk créée: plesk-directive-$timestamp.txt\n";
    $actions[] = "Directive Plesk anti-cache générée";

} catch (Exception $e) {
    $errors[] = "Erreur: " . $e->getMessage();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// RAPPORT FINAL
echo "\n🎯 RÉSUMÉ FORCE REFRESH SERVEUR\n";
echo "===============================\n";
echo "Actions réussies: " . count($actions) . "\n";
echo "Erreurs: " . count($errors) . "\n\n";

if (!empty($actions)) {
    echo "✅ ACTIONS RÉALISÉES:\n";
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

// INSTRUCTIONS
echo "🚀 INSTRUCTIONS POST-FORCE REFRESH:\n";
echo "===================================\n";
echo "1. 📤 Uploadez TOUS les fichiers sur Plesk\n\n";

echo "2. 🏢 Dans Plesk > Domaine > Apache & nginx >\n";
echo "   Copiez le contenu de: plesk-directive-$timestamp.txt\n";
echo "   Dans: \"Directives Apache supplémentaires\"\n\n";

if (isset($testFileName)) {
    echo "3. 🔍 Tests immédiats:\n";
    echo "   a) /$testFileName (DOIT afficher timestamp: $timestamp)\n";
    echo "   b) /map (DOIT être différent du précédent état)\n";
    echo "   c) /test-map-direct.php (référence qui fonctionne)\n\n";
}

echo "4. 🔄 Redémarrer Apache/Nginx dans Plesk:\n";
echo "   Plesk > Domaine > Apache & nginx > Redémarrer\n\n";

echo "5. 📱 Test navigateur:\n";
echo "   - Ouvrir mode privé\n";
echo "   - Tester /map\n";
echo "   - Doit voir la nouvelle version\n\n";

echo "🚨 SI APRÈS ÇA /map NE FONCTIONNE TOUJOURS PAS:\n";
echo "==============================================\n";
echo "• Le problème est niveau infrastructure Plesk\n";
echo "• Contacter support avec ce rapport\n";
echo "• Ou utiliser temporairement /test-map-direct.php\n\n";

echo "📋 FICHIERS CRÉÉS (à conserver):\n";
if (isset($backupPath)) echo "• " . basename($backupPath) . " (backup .htaccess)\n";
if (isset($backupController)) echo "• " . basename($backupController) . " (backup controller)\n";
if (isset($testFileName)) echo "• $testFileName (test timestamp)\n";
echo "• plesk-directive-$timestamp.txt (config Plesk)\n";

$success = count($errors) === 0;
echo "\n" . ($success ? "🎉 FORCE REFRESH SERVEUR TERMINÉ" : "⚠️ FORCE REFRESH AVEC ERREURS") . "\n";

exit($success ? 0 : 1);
?>