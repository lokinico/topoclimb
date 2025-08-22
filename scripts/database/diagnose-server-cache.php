<?php
/**
 * DIAGNOSTIC COMPLET - Identifier le cache serveur qui bloque /map
 * À exécuter pour identifier où se situe le problème de cache
 */

echo "🔍 DIAGNOSTIC CACHE SERVEUR COMPLET\n";
echo "===================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Objectif: Identifier pourquoi /map reste buggé\n\n";

$diagnostics = [];
$issues = [];

// 1. ÉTAT DES FICHIERS TEMPLATES
echo "1. ÉTAT DES TEMPLATES\n";
echo "====================\n";

$mapTemplate = __DIR__ . '/resources/views/map/index.twig';
$layoutTemplate = __DIR__ . '/resources/views/layouts/fullscreen.twig';

if (file_exists($mapTemplate)) {
    $content = file_get_contents($mapTemplate);
    $lastModified = date('Y-m-d H:i:s', filemtime($mapTemplate));
    $size = filesize($mapTemplate);
    
    echo "✅ Template carte existe\n";
    echo "   Modifié: $lastModified\n";
    echo "   Taille: $size bytes\n";
    
    if (strpos($content, 'CACHE BUST:') !== false) {
        preg_match('/CACHE BUST: (.*?) -/', $content, $matches);
        $cacheBust = $matches[1] ?? 'inconnu';
        echo "   Cache Bust: $cacheBust\n";
        $diagnostics[] = "Template avec cache bust: $cacheBust";
    } else {
        echo "   ⚠️ Pas de cache bust\n";
        $issues[] = "Template sans cache bust";
    }
} else {
    echo "❌ Template carte MANQUANT\n";
    $issues[] = "Template carte manquant";
}

if (file_exists($layoutTemplate)) {
    $content = file_get_contents($layoutTemplate);
    $lastModified = date('Y-m-d H:i:s', filemtime($layoutTemplate));
    
    echo "✅ Layout fullscreen existe\n";
    echo "   Modifié: $lastModified\n";
    
    if (strpos($content, 'FORCE REFRESH:') !== false) {
        preg_match('/FORCE REFRESH: (.*?) -->/', $content, $matches);
        $forceRefresh = $matches[1] ?? 'inconnu';
        echo "   Force Refresh: $forceRefresh\n";
        $diagnostics[] = "Layout avec force refresh: $forceRefresh";
    }
}

echo "\n";

// 2. ÉTAT DU CACHE TWIG
echo "2. ÉTAT CACHE TWIG\n";
echo "==================\n";

$cacheDir = __DIR__ . '/storage/cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    $count = count($files);
    echo "📁 Dossier cache: $count fichiers\n";
    
    if ($count > 0) {
        $latestFile = '';
        $latestTime = 0;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) > $latestTime) {
                $latestTime = filemtime($file);
                $latestFile = basename($file);
            }
        }
        if ($latestFile) {
            echo "   Dernier fichier: $latestFile\n";
            echo "   Modifié: " . date('Y-m-d H:i:s', $latestTime) . "\n";
            
            // Si récent = cache pas vidé
            if (time() - $latestTime < 300) { // 5 minutes
                $issues[] = "Cache Twig pas complètement vidé (fichier récent: $latestFile)";
                echo "   ⚠️ Cache peut-être pas complètement vidé\n";
            } else {
                $diagnostics[] = "Cache Twig semble vidé (dernier fichier ancien)";
            }
        }
    } else {
        echo "✅ Cache Twig complètement vide\n";
        $diagnostics[] = "Cache Twig vide";
    }
} else {
    echo "❌ Dossier cache introuvable\n";
    $issues[] = "Dossier cache manquant";
}

echo "\n";

// 3. INFORMATION SERVEUR WEB
echo "3. CONFIGURATION SERVEUR\n";
echo "========================\n";

echo "Serveur: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'inconnu') . "\n";

// Détecter Plesk/cPanel
if (isset($_SERVER['SERVER_ADMIN']) && strpos($_SERVER['SERVER_ADMIN'], 'plesk') !== false) {
    echo "🏢 Panel: Plesk détecté\n";
    $diagnostics[] = "Serveur Plesk détecté";
} elseif (file_exists('/usr/local/cpanel')) {
    echo "🏢 Panel: cPanel détecté\n";
    $diagnostics[] = "Serveur cPanel détecté";
}

// Modules Apache
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $cacheModules = array_filter($modules, function($mod) {
        return strpos(strtolower($mod), 'cache') !== false;
    });
    
    if (!empty($cacheModules)) {
        echo "🔧 Modules cache Apache: " . implode(', ', $cacheModules) . "\n";
        $issues[] = "Modules cache Apache actifs: " . implode(', ', $cacheModules);
    }
}

echo "\n";

// 4. HEADERS HTTP DE CACHE
echo "4. TEST HEADERS HTTP\n";
echo "===================\n";

$currentUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];

// Simuler headers que le serveur pourrait envoyer
$possibleCacheHeaders = [
    'Cache-Control',
    'Expires',
    'ETag',
    'Last-Modified',
    'Pragma',
    'X-Cache',
    'X-Varnish',
    'CF-Cache-Status' // Cloudflare
];

echo "URL de base: $currentUrl\n";
echo "Headers cache potentiels à vérifier:\n";
foreach ($possibleCacheHeaders as $header) {
    echo "   - $header\n";
}

// 5. FICHIERS .HTACCESS
echo "\n5. FICHIERS .HTACCESS\n";
echo "====================\n";

$htaccessFiles = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/public/.htaccess'
];

foreach ($htaccessFiles as $htaccess) {
    if (file_exists($htaccess)) {
        $content = file_get_contents($htaccess);
        echo "📄 " . str_replace(__DIR__ . '/', '', $htaccess) . " existe\n";
        
        // Chercher règles de cache
        if (strpos($content, 'Cache-Control') !== false || 
            strpos($content, 'Expires') !== false || 
            strpos($content, 'mod_expires') !== false) {
            echo "   ⚠️ Contient des règles de cache\n";
            $issues[] = "Fichier .htaccess avec règles de cache: $htaccess";
        }
        
        if (strpos($content, 'mod_rewrite') !== false) {
            echo "   ✅ Contient des règles de réécriture\n";
            $diagnostics[] = "Réécriture d'URL active";
        }
    }
}

echo "\n";

// 6. COMPARAISON FICHIERS TEST
echo "6. COMPARAISON FICHIERS\n";
echo "======================\n";

$testFile = __DIR__ . '/public/test-carte.html';
if (file_exists($testFile)) {
    $testModified = date('Y-m-d H:i:s', filemtime($testFile));
    echo "✅ test-carte.html modifié: $testModified\n";
    $diagnostics[] = "Fichier test disponible pour comparaison";
    
    // Ce fichier fonctionne, donc le problème n'est pas JS/CSS
    echo "   📋 Ce fichier FONCTIONNE = problème pas JS/CSS\n";
} else {
    echo "❌ test-carte.html manquant\n";
    $issues[] = "Fichier test manquant pour comparaison";
}

// 7. PROCESSUS EN COURS
echo "\n7. PROCESSUS CACHE\n";
echo "==================\n";

// OPCache
if (function_exists('opcache_get_status')) {
    $opcacheStatus = opcache_get_status();
    echo "🔧 OPCache: " . ($opcacheStatus['opcache_enabled'] ? 'Activé' : 'Désactivé') . "\n";
    if ($opcacheStatus['opcache_enabled']) {
        echo "   Fichiers en cache: " . $opcacheStatus['opcache_statistics']['num_cached_scripts'] . "\n";
        $diagnostics[] = "OPCache actif avec " . $opcacheStatus['opcache_statistics']['num_cached_scripts'] . " scripts";
    }
}

// APCu
if (function_exists('apcu_cache_info')) {
    echo "🔧 APCu: Activé\n";
    $diagnostics[] = "APCu cache utilisateur actif";
}

echo "\n";

// RAPPORT FINAL
echo "🎯 RAPPORT DIAGNOSTIC\n";
echo "====================\n";
echo "Problèmes identifiés: " . count($issues) . "\n";
echo "Informations collectées: " . count($diagnostics) . "\n\n";

if (!empty($issues)) {
    echo "❌ PROBLÈMES POTENTIELS:\n";
    foreach ($issues as $i => $issue) {
        echo "   " . ($i + 1) . ". $issue\n";
    }
    echo "\n";
}

if (!empty($diagnostics)) {
    echo "ℹ️ INFORMATIONS SYSTÈME:\n";
    foreach ($diagnostics as $i => $diag) {
        echo "   • $diag\n";
    }
    echo "\n";
}

// RECOMMANDATIONS
echo "🔧 RECOMMANDATIONS TECHNIQUES:\n";
echo "==============================\n";

if (in_array("Modules cache Apache actifs", array_map(function($i) { return explode(':', $i)[0]; }, $issues))) {
    echo "1. 🚨 URGENT: Désactiver temporairement les modules cache Apache\n";
    echo "   - Dans Plesk: Domaine > Apache & nginx > Directives supplémentaires\n";
    echo "   - Ajouter: LoadModule cache_module modules/mod_cache.so (commenté)\n\n";
}

if (count(array_filter($issues, function($i) { return strpos($i, '.htaccess') !== false; })) > 0) {
    echo "2. 🔧 Vérifier les règles .htaccess de cache\n";
    echo "   - Renommer temporairement .htaccess en .htaccess.bak\n";
    echo "   - Tester si /map fonctionne sans .htaccess\n\n";
}

echo "3. 🔄 Test de contournement immédiat:\n";
echo "   - Créer /map-debug.php qui fait un simple echo du template\n";
echo "   - Si ça marche = problème de routage/contrôleur\n";
echo "   - Si ça marche pas = problème infrastructure\n\n";

echo "4. 🌐 Test headers HTTP:\n";
echo "   - Utiliser curl -I $currentUrl/map\n";
echo "   - Chercher headers Cache-Control, X-Cache, etc.\n\n";

// Créer script de test immédiat
$testScript = __DIR__ . '/test-map-debug.php';
$testContent = '<?php
// TEST IMMÉDIAT - Bypass complet du système
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo "<h1>TEST DEBUG MAP</h1>";
echo "<p>Si vous voyez ceci, le serveur exécute PHP correctement</p>";
echo "<p>Timestamp: " . date("Y-m-d H:i:s") . "</p>";
echo "<hr>";

$template = __DIR__ . "/resources/views/map/index.twig";
if (file_exists($template)) {
    echo "<h2>Template existe:</h2>";
    echo "<pre>" . htmlspecialchars(substr(file_get_contents($template), 0, 500)) . "...</pre>";
} else {
    echo "<h2>❌ Template manquant</h2>";
}
?>';

file_put_contents($testScript, $testContent);

echo "5. ✅ Script test créé: test-map-debug.php\n";
echo "   - Accédez à: $currentUrl/test-map-debug.php\n";
echo "   - Compare avec: $currentUrl/map\n\n";

// Sauvegarder rapport
$reportFile = __DIR__ . '/diagnostic-cache-' . date('Ymd_His') . '.txt';
ob_start();
echo "DIAGNOSTIC CACHE SERVEUR\n";
echo "========================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
echo "PROBLÈMES:\n";
foreach ($issues as $issue) {
    echo "• $issue\n";
}
echo "\nINFORMATIONS:\n";
foreach ($diagnostics as $diag) {
    echo "• $diag\n";
}
$reportContent = ob_get_clean();
file_put_contents($reportFile, $reportContent);

echo "📄 Rapport sauvé: " . basename($reportFile) . "\n\n";

echo "🎯 PROCHAINE ÉTAPE IMMÉDIATE:\n";
echo "=============================\n";
echo "1. Testez: $currentUrl/test-map-debug.php\n";
echo "2. Comparez avec: $currentUrl/map\n";
echo "3. Si différent = cache serveur confirmé\n";
echo "4. Si identique = problème autre\n\n";

echo "Si le test-map-debug.php ne fonctionne pas non plus:\n";
echo "→ Problème niveau serveur/PHP/permissions\n\n";
echo "Si le test-map-debug.php fonctionne mais pas /map:\n";
echo "→ Problème cache serveur Apache/Nginx/Plesk\n\n";

$success = count($issues) < 3; // Succès si moins de 3 problèmes majeurs
exit($success ? 0 : 1);
?>