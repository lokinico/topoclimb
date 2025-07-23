<?php
/**
 * TEST FINAL /map - Vérifier si le problème est résolu
 */

// Headers anti-cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");  
header("Expires: 0");

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Final Map - <?= date('H:i:s') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        .warning { border-left-color: #ffc107; }
        .timestamp { background: #007bff; color: white; padding: 5px 10px; border-radius: 3px; }
        iframe { width: 100%; height: 400px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block; }
        .btn.success { background: #28a745; }
        .btn.warning { background: #ffc107; }
    </style>
</head>
<body>
    <h1>🎯 Test Final Route /map</h1>
    <p class="timestamp">Exécuté le: <?= date('Y-m-d H:i:s') ?></p>
    
    <div class="test-box success">
        <h2>✅ État Actuel</h2>
        <ul>
            <li><strong>Autoloader:</strong> regeneré avec <code>composer dump-autoload</code></li>
            <li><strong>Classes:</strong> MapController chargé et fonctionnel</li>
            <li><strong>Cache .htaccess:</strong> règles problématiques supprimées</li>
            <li><strong>Cache Twig:</strong> vidé proprement</li>
        </ul>
    </div>
    
    <div class="test-box">
        <h2>🧪 Tests de Vérification</h2>
        
        <h3>1. Test Route Directe</h3>
        <p>Cliquez pour tester directement la route /map :</p>
        <a href="/map" target="_blank" class="btn">🗺️ Tester /map</a>
        <a href="/map" target="map-frame" class="btn success">📱 Tester dans iframe</a>
        
        <h3>2. Comparaison Références</h3>
        <p>Comparer avec les versions qui fonctionnent :</p>
        <a href="/test-carte.html" target="_blank" class="btn warning">📋 test-carte.html</a>
        <a href="/test-simple.php" target="_blank" class="btn warning">🔧 test-simple.php</a>
        
        <h3>3. Diagnostic Continue</h3>
        <a href="/diagnose-web.php" target="_blank" class="btn">🔍 Diagnostic Complet</a>
    </div>
    
    <div class="test-box">
        <h2>📱 Aperçu Route /map</h2>
        <p>Si la route fonctionne, vous devriez voir la carte interactive ci-dessous :</p>
        <iframe name="map-frame" src="/map"></iframe>
    </div>
    
    <div class="test-box">
        <h2>🎯 Interprétation des Résultats</h2>
        
        <h3>Si /map fonctionne maintenant :</h3>
        <ul>
            <li>✅ <strong>Problème résolu :</strong> c'était l'autoloader non régénéré</li>
            <li>✅ <strong>Solution :</strong> <code>composer dump-autoload</code> a corrigé le chargement des classes</li>
            <li>✅ <strong>Route normale :</strong> /map utilise maintenant MapController + Twig</li>
        </ul>
        
        <h3>Si /map ne fonctionne toujours pas :</h3>
        <ul>
            <li>🔍 <strong>Vérifier :</strong> erreurs PHP dans les logs</li>
            <li>🔍 <strong>Tester :</strong> avec mode développeur/debug activé</li>
            <li>🔍 <strong>Analyser :</strong> différence entre iframe et onglet direct</li>
        </ul>
    </div>
    
    <div class="test-box warning">
        <h2>⚠️ Actions si Problème Persiste</h2>
        <ol>
            <li><strong>Redémarrer Apache/Nginx</strong> sur Plesk</li>
            <li><strong>Vider cache navigateur</strong> complètement (Ctrl+Shift+Del)</li>
            <li><strong>Tester en mode privé</strong> pour éviter cache local</li>
            <li><strong>Vérifier logs serveur</strong> pour erreurs PHP</li>
        </ol>
    </div>
    
    <div class="test-box">
        <h2>📊 Status Debug</h2>
        <pre><?php
echo "Timestamp: " . time() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Autoloader régénéré: " . date('Y-m-d H:i:s', filemtime('../vendor/autoload.php')) . "\n";
echo "MapController.php: " . date('Y-m-d H:i:s', filemtime('../src/Controllers/MapController.php')) . "\n";
echo "Template Twig: " . date('Y-m-d H:i:s', filemtime('../resources/views/map/index.twig')) . "\n";
echo "Cache Twig files: " . count(glob('../storage/cache/*')) . "\n";
        ?></pre>
    </div>
</body>
</html>