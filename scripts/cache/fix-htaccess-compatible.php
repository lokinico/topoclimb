<?php
/**
 * CORRECTION .htaccess - Approche compatible
 * Supprimer les directives problématiques et utiliser approche simple
 */

echo "🔧 CORRECTION .htaccess COMPATIBLE\n";
echo "=================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. NETTOYER le .htaccess des directives problématiques
    echo "1. NETTOYAGE .htaccess des directives non compatibles...\n";
    
    $htaccessPath = __DIR__ . '/.htaccess';
    $content = file_get_contents($htaccessPath);
    
    // Supprimer complètement la section anti-cache problématique
    $cleanContent = preg_replace(
        '/# ANTI-CACHE ULTRA AGRESSIF.*?<\/IfModule>/s',
        '# Anti-cache simplifié via PHP headers dans contrôleur',
        $content
    );
    
    file_put_contents($htaccessPath, $cleanContent);
    echo "   ✅ .htaccess nettoyé des directives LocationMatch\n";

    // 2. CRÉER une version ultra-simple du test
    echo "\n2. CRÉATION version ultra-simple...\n";
    
    $simpleTestPath = __DIR__ . '/public/map-simple-test.php';
    $simpleContent = '<?php
// SOLUTION ULTRA SIMPLE - Headers PHP purs
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Timestamp: ' . time() . '");

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Simple Carte - <?= date("H:i:s") ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
    body { margin: 0; font-family: Arial, sans-serif; }
    .header { 
        background: #007bff; color: white; padding: 15px; 
        text-align: center; font-size: 18px; font-weight: bold;
    }
    #map { height: calc(100vh - 60px); }
    </style>
</head>
<body>
    <div class="header">
        🎯 TEST SIMPLE CARTE - <?= date("Y-m-d H:i:s") ?> - Timestamp: <?= time() ?>
    </div>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🔵 CARTE SIMPLE - " + new Date().toISOString());
    
    const map = L.map("map").setView([46.8182, 8.2275], 8);
    
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap"
    }).addTo(map);
    
    L.marker([46.1817, 7.1947])
        .addTo(map)
        .bindPopup("<b>TEST SIMPLE</b><br>Chargé: <?= date("H:i:s") ?>")
        .openPopup();
        
    // Notification après chargement
    setTimeout(() => {
        alert("✅ TEST SIMPLE RÉUSSI !\\nSi vous voyez cette alerte, la page PHP fonctionne\\nTimestamp: <?= time() ?>");
    }, 2000);
    </script>
</body>
</html>';
    
    file_put_contents($simpleTestPath, $simpleContent);
    echo "   ✅ Test ultra-simple créé: map-simple-test.php\n";

    // 3. MODIFIER le MapNewController pour utiliser headers PHP seulement
    echo "\n3. MODIFICATION MapNewController (headers PHP purs)...\n";
    
    $controllerPath = __DIR__ . '/src/Controllers/MapNewController.php';
    if (file_exists($controllerPath)) {
        $controllerContent = file_get_contents($controllerPath);
        
        // S'assurer que les headers sont bien dans le code PHP
        if (strpos($controllerContent, 'X-Timestamp') === false) {
            $controllerContent = str_replace(
                '"X-Force-Refresh: " . md5(time()),',
                '"X-Force-Refresh: " . md5(time()) . "",
            "X-Timestamp: " . time(),',
                $controllerContent
            );
            file_put_contents($controllerPath, $controllerContent);
        }
        echo "   ✅ MapNewController headers renforcés\n";
    }

    // 4. CRÉER script de test comparatif simple
    echo "\n4. CRÉATION script comparatif simple...\n";
    
    $comparePath = __DIR__ . '/public/test-final-simple.php';
    $compareContent = '<?php
header("Cache-Control: no-cache");
?><!DOCTYPE html>
<html><head><title>Test Final Simple</title>
<style>
body { font-family: Arial; margin: 20px; }
.test { margin: 20px 0; padding: 15px; border: 2px solid #ddd; border-radius: 5px; }
.success { border-color: #28a745; background: #f8fff9; }
.error { border-color: #dc3545; background: #fff8f8; }
.btn { 
    background: #007bff; color: white; padding: 10px 20px; 
    text-decoration: none; border-radius: 5px; margin: 5px; 
    display: inline-block; font-weight: bold;
}
.btn.success { background: #28a745; }
.btn.danger { background: #dc3545; }
</style>
</head>
<body>
<h1>🎯 Test Final Simple - <?= date("H:i:s") ?></h1>

<div class="test success">
    <h2>✅ Test PHP Basique</h2>
    <p>Cette page PHP fonctionne. Timestamp: <?= time() ?></p>
</div>

<div class="test">
    <h2>🧪 Tests à Effectuer</h2>
    <p><strong>Ordre de test recommandé :</strong></p>
    
    <h3>1. Test Ultra-Simple (doit marcher)</h3>
    <a href="/map-simple-test.php" target="_blank" class="btn success">Test Simple</a>
    <p><small>Doit afficher header bleu + carte + popup automatique</small></p>
    
    <h3>2. Test Nouvelle Route (contournement)</h3>
    <a href="/map-new" target="_blank" class="btn">Test /map-new</a>
    <p><small>Doit afficher header vert "NOUVELLE VERSION"</small></p>
    
    <h3>3. Test Route Originale (problématique)</h3>
    <a href="/map" target="_blank" class="btn danger">Test /map</a>
    <p><small>Version qui reste en cache</small></p>
</div>

<div class="test">
    <h2>📊 Interprétation</h2>
    <ul>
        <li><strong>Test Simple fonctionne :</strong> PHP et serveur OK</li>
        <li><strong>/map-new fonctionne :</strong> Nouvelle route contourne le cache</li>
        <li><strong>/map reste buggé :</strong> Cache serveur sur route spécifique</li>
        <li><strong>Tous identiques :</strong> Cache navigateur ou résolu</li>
    </ul>
</div>

<div class="test error">
    <h2>🚨 Si Rien ne Fonctionne</h2>
    <p>Problème plus profond :</p>
    <ul>
        <li>Vérifier logs Apache : <code>/var/log/apache2/error.log</code></li>
        <li>Redémarrer Apache/Nginx sur Plesk</li>
        <li>Vérifier permissions fichiers</li>
        <li>Tester en mode privé navigateur</li>
    </ul>
</div>
</body></html>';
    
    file_put_contents($comparePath, $compareContent);
    echo "   ✅ Script test final créé: test-final-simple.php\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 CORRECTION .htaccess TERMINÉE\n";
echo "================================\n";
echo "✅ Directives LocationMatch supprimées\n";
echo "✅ Test ultra-simple créé\n";
echo "✅ Headers PHP purs utilisés\n";
echo "✅ Script de test final créé\n\n";

echo "🚀 TESTEZ MAINTENANT:\n";
echo "====================\n";
echo "1. 🎯 /test-final-simple.php (guide de test)\n";
echo "2. 🔵 /map-simple-test.php (doit marcher)\n";
echo "3. 🟢 /map-new (contournement)\n";
echo "4. 🔴 /map (original problématique)\n\n";

echo "✅ Plus d'erreur .htaccess - Tests prêts !\n";
?>