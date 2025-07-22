<?php
/**
 * DIAGNOSTIC WEB-SAFE - Version HTML pour navigateur
 */

// Headers pour éviter les erreurs
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic Cache Serveur - <?= date('H:i:s') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        .section { margin: 20px 0; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .timestamp { background: #007bff; color: white; padding: 5px 10px; border-radius: 3px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .status-ok { background: #d4edda; padding: 10px; border-radius: 3px; margin: 5px 0; }
        .status-warn { background: #fff3cd; padding: 10px; border-radius: 3px; margin: 5px 0; }
        .status-error { background: #f8d7da; padding: 10px; border-radius: 3px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Cache Serveur</h1>
        <p class="timestamp">Exécuté le: <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        $diagnostics = [];
        $issues = [];
        $baseDir = dirname(__DIR__);
        
        // 1. ÉTAT DES TEMPLATES
        echo '<div class="section">';
        echo '<h2>1. État des Templates</h2>';
        
        $mapTemplate = $baseDir . '/resources/views/map/index.twig';
        $layoutTemplate = $baseDir . '/resources/views/layouts/fullscreen.twig';
        
        if (file_exists($mapTemplate)) {
            $content = file_get_contents($mapTemplate);
            $lastModified = date('Y-m-d H:i:s', filemtime($mapTemplate));
            $size = filesize($mapTemplate);
            
            echo '<div class="status-ok">';
            echo "<strong>✅ Template carte trouvé</strong><br>";
            echo "Modifié: $lastModified<br>";
            echo "Taille: $size bytes<br>";
            
            if (strpos($content, 'CACHE BUST:') !== false) {
                preg_match('/CACHE BUST: (.*?) -/', $content, $matches);
                $cacheBust = $matches[1] ?? 'inconnu';
                echo "Cache Bust: $cacheBust";
                $diagnostics[] = "Template avec cache bust: $cacheBust";
            } else {
                echo '<span class="warning">⚠️ Pas de cache bust</span>';
                $issues[] = "Template sans cache bust";
            }
            echo '</div>';
        } else {
            echo '<div class="status-error">❌ Template carte MANQUANT</div>';
            $issues[] = "Template carte manquant";
        }
        
        if (file_exists($layoutTemplate)) {
            $content = file_get_contents($layoutTemplate);
            $lastModified = date('Y-m-d H:i:s', filemtime($layoutTemplate));
            
            echo '<div class="status-ok">';
            echo "<strong>✅ Layout fullscreen trouvé</strong><br>";
            echo "Modifié: $lastModified<br>";
            
            if (strpos($content, 'FORCE REFRESH:') !== false) {
                preg_match('/FORCE REFRESH: (.*?) -->/', $content, $matches);
                $forceRefresh = $matches[1] ?? 'inconnu';
                echo "Force Refresh: $forceRefresh";
                $diagnostics[] = "Layout avec force refresh: $forceRefresh";
            }
            echo '</div>';
        }
        echo '</div>';
        
        // 2. CACHE TWIG
        echo '<div class="section">';
        echo '<h2>2. Cache Twig</h2>';
        
        $cacheDir = $baseDir . '/storage/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $count = count($files);
            
            if ($count == 0) {
                echo '<div class="status-ok">✅ Cache Twig vide (' . $count . ' fichiers)</div>';
                $diagnostics[] = "Cache Twig vide";
            } else {
                echo '<div class="status-warn">⚠️ Cache Twig contient ' . $count . ' fichiers</div>';
                
                // Dernier fichier modifié
                $latestTime = 0;
                $latestFile = '';
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) > $latestTime) {
                        $latestTime = filemtime($file);
                        $latestFile = basename($file);
                    }
                }
                
                if ($latestFile) {
                    $ageMinutes = floor((time() - $latestTime) / 60);
                    echo "<p>Dernier fichier: $latestFile (il y a $ageMinutes minutes)</p>";
                    
                    if ($ageMinutes < 5) {
                        $issues[] = "Cache Twig pas complètement vidé (fichier récent)";
                        echo '<div class="status-error">❌ Cache récent détecté - pas vidé complètement</div>';
                    }
                }
            }
        } else {
            echo '<div class="status-error">❌ Dossier cache introuvable</div>';
            $issues[] = "Dossier cache manquant";
        }
        echo '</div>';
        
        // 3. INFORMATION SERVEUR
        echo '<div class="section">';
        echo '<h2>3. Configuration Serveur</h2>';
        
        echo '<div class="status-ok">';
        echo '<strong>Serveur:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu') . '<br>';
        echo '<strong>PHP Version:</strong> ' . PHP_VERSION . '<br>';
        echo '<strong>Document Root:</strong> ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'inconnu') . '<br>';
        
        // OPCache
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = opcache_get_status();
            $enabled = $opcacheStatus['opcache_enabled'] ? 'Activé' : 'Désactivé';
            echo "<strong>OPCache:</strong> $enabled<br>";
            if ($opcacheStatus['opcache_enabled']) {
                $scripts = $opcacheStatus['opcache_statistics']['num_cached_scripts'];
                echo "Scripts en cache: $scripts<br>";
                $diagnostics[] = "OPCache actif avec $scripts scripts";
            }
        }
        echo '</div>';
        echo '</div>';
        
        // 4. FICHIERS .HTACCESS
        echo '<div class="section">';
        echo '<h2>4. Fichiers .htaccess</h2>';
        
        $htaccessFiles = [
            $baseDir . '/.htaccess',
            $baseDir . '/public/.htaccess'
        ];
        
        foreach ($htaccessFiles as $htaccess) {
            if (file_exists($htaccess)) {
                $content = file_get_contents($htaccess);
                $path = str_replace($baseDir . '/', '', $htaccess);
                echo "<div class=\"status-ok\"><strong>✅ $path existe</strong><br>";
                echo "Taille: " . filesize($htaccess) . " bytes</div>";
                
                if (strpos($content, 'Cache-Control') !== false || 
                    strpos($content, 'mod_expires') !== false ||
                    strpos($content, 'mod_headers') !== false) {
                    echo '<div class="status-warn">⚠️ Contient des règles de cache</div>';
                    $issues[] = "Fichier .htaccess avec règles cache: $path";
                }
            } else {
                $path = str_replace($baseDir . '/', '', $htaccess);
                echo "<div class=\"status-warn\">⚠️ $path manquant</div>";
            }
        }
        echo '</div>';
        
        // 5. FICHIERS DE TEST
        echo '<div class="section">';
        echo '<h2>5. Fichiers de Test</h2>';
        
        $testFiles = [
            'public/test-carte.html' => 'Test carte HTML (référence)',
            'public/test-map-direct.php' => 'Test direct PHP',
            'fix-map-cache.php' => 'Script cache-busting',
            'force-server-refresh.php' => 'Script force refresh'
        ];
        
        foreach ($testFiles as $file => $description) {
            $fullPath = $baseDir . '/' . $file;
            if (file_exists($fullPath)) {
                $modified = date('Y-m-d H:i:s', filemtime($fullPath));
                echo "<div class=\"status-ok\">✅ <strong>$file</strong> - $description<br>";
                echo "Modifié: $modified</div>";
            } else {
                echo "<div class=\"status-warn\">⚠️ <strong>$file</strong> - $description (manquant)</div>";
            }
        }
        echo '</div>';
        
        // RÉSUMÉ
        echo '<div class="section">';
        echo '<h2>🎯 Résumé du Diagnostic</h2>';
        
        echo '<div class="status-ok">';
        echo '<strong>Éléments OK:</strong> ' . count($diagnostics) . '<br>';
        foreach ($diagnostics as $diag) {
            echo "✅ $diag<br>";
        }
        echo '</div>';
        
        if (!empty($issues)) {
            echo '<div class="status-error">';
            echo '<strong>Problèmes détectés:</strong> ' . count($issues) . '<br>';
            foreach ($issues as $issue) {
                echo "❌ $issue<br>";
            }
            echo '</div>';
        }
        
        // RECOMMANDATIONS
        echo '<div class="status-warn">';
        echo '<h3>🔧 Recommandations:</h3>';
        
        if (count($issues) == 0) {
            echo '<p><strong>Aucun problème détecté au niveau application.</strong></p>';
            echo '<p>Le problème est probablement au niveau serveur:</p>';
            echo '<ul>';
            echo '<li>Cache Apache (mod_cache)</li>';
            echo '<li>Cache Nginx/Plesk</li>';
            echo '<li>Cache reverse proxy</li>';
            echo '</ul>';
            
            echo '<h4>Tests immédiats:</h4>';
            echo '<ol>';
            echo '<li><a href="/test-carte.html" target="_blank">Tester test-carte.html</a> (doit fonctionner)</li>';
            echo '<li><a href="/test-map-direct.php" target="_blank">Tester test-map-direct.php</a> (doit fonctionner)</li>';
            echo '<li><a href="/map" target="_blank">Tester /map</a> (problématique)</li>';
            echo '</ol>';
        } else {
            echo '<ol>';
            echo '<li>Exécuter: <code>php fix-map-cache.php</code></li>';
            echo '<li>Si échec: <code>php force-server-refresh.php</code></li>';
            echo '<li>Configurer directives Plesk anti-cache</li>';
            echo '</ol>';
        }
        
        echo '</div>';
        echo '</div>';
        ?>
        
        <div class="section">
            <h3>🔗 Actions Rapides</h3>
            <p>
                <a href="/test-carte.html" target="_blank" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin-right: 10px;">Test HTML ✅</a>
                <a href="/test-map-direct.php" target="_blank" style="background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin-right: 10px;">Test PHP Direct</a>
                <a href="/map" target="_blank" style="background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin-right: 10px;">Route /map ❌</a>
            </p>
        </div>
        
        <div class="section">
            <h3>ℹ️ Info Debug</h3>
            <pre><?= "Timestamp: " . time() . "\nUser Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'inconnu') . "\nHTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'inconnu') ?></pre>
        </div>
    </div>
</body>
</html>