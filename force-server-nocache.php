<?php
/**
 * FORCE SERVEUR - Créer une route /map complètement nouvelle
 * Contournement radical du cache serveur Apache/Nginx
 */

echo "🚨 FORCE SERVEUR - CONTOURNEMENT RADICAL\n";
echo "=======================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. CRÉER un nouveau contrôleur avec nom différent
    echo "1. CRÉATION nouveau contrôleur MapNew...\n";
    
    $newControllerContent = '<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Response as CoreResponse;

/**
 * NOUVEAU MapController - Contournement cache serveur
 * Timestamp: ' . date('Y-m-d H:i:s') . '
 */
class MapNewController extends BaseController
{
    public function index(?Request $request = null): Response
    {
        // HEADERS ANTI-CACHE ULTRA AGRESSIFS
        $headers = [
            "Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private",
            "Pragma: no-cache",
            "Expires: Thu, 01 Jan 1970 00:00:00 GMT",
            "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT",
            "ETag: " . uniqid(),
            "X-Timestamp: " . time(),
            "X-Force-Refresh: " . md5(time()),
        ];
        
        foreach ($headers as $header) {
            header($header);
        }
        
        // CONTENU HTML DIRECT - BYPASS TWIG COMPLÈTEMENT
        $html = \'<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Interactive - NOUVEAU - \' . date(\'H:i:s\') . \'</title>
    
    <!-- TIMESTAMP UNIQUE: \' . time() . \' -->
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
    * { margin: 0; padding: 0; }
    html, body { height: 100%; font-family: Arial, sans-serif; }
    
    .header {
        position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
        background: #28a745; color: white; padding: 10px 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    
    #map { 
        height: 100vh; width: 100%; 
        padding-top: 60px; box-sizing: border-box;
    }
    
    .status {
        position: fixed; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(40, 167, 69, 0.9); color: white;
        padding: 10px 15px; border-radius: 5px;
        backdrop-filter: blur(10px);
    }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ TopoclimbCH - Carte NOUVELLE VERSION</h1>
        <p>Chargée le: \' . date(\'Y-m-d H:i:s\') . \' | Timestamp: \' . time() . \'</p>
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>✅ NOUVELLE VERSION</strong></div>
        <div>Contrôleur: MapNewController</div>
        <div>Status: Chargé \' . date(\'H:i:s\') . \'</div>
        <div>Sites: <span id="site-count">0</span></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🟢 NOUVELLE VERSION CARTE - " + new Date().toISOString());
    console.log("Timestamp: \' . time() . \'");
    
    // Carte centrée sur Suisse
    const map = L.map("map").setView([46.8182, 8.2275], 8);
    
    // Tuiles OpenStreetMap
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors"
    }).addTo(map);
    
    // Marqueur test
    L.marker([46.1817, 7.1947])
        .addTo(map)
        .bindPopup("<b>🏔️ Saillon Test</b><br>NOUVELLE VERSION<br>Chargé: \' . date(\'H:i:s\') . \'")
        .openPopup();
    
    // Mettre à jour le compteur
    document.getElementById("site-count").textContent = "1 (test)";
    
    // Message de confirmation après 2 secondes
    setTimeout(() => {
        const popup = L.popup()
            .setLatLng([46.8182, 8.2275])
            .setContent("🎉 <strong>NOUVELLE VERSION FONCTIONNE !</strong><br>Si vous voyez ceci, le cache serveur est contourné<br>Timestamp: \' . time() . \'")
            .openOn(map);
    }, 2000);
    </script>
</body>
</html>\';
        
        return new CoreResponse($html);
    }
}
';
    
    $newControllerPath = __DIR__ . '/src/Controllers/MapNewController.php';
    file_put_contents($newControllerPath, $newControllerContent);
    echo "   ✅ MapNewController créé\n";

    // 2. AJOUTER nouvelle route dans config
    echo "\n2. AJOUT nouvelle route /map-new...\n";
    
    $routesPath = __DIR__ . '/config/routes.php';
    $routes = include $routesPath;
    
    // Ajouter la nouvelle route
    $routes[] = [
        'name' => 'map_new',
        'path' => '/map-new',
        'controller' => 'TopoclimbCH\Controllers\MapNewController',
        'action' => 'index',
        'methods' => ['GET']
    ];
    
    // Sauvegarder
    $routesContent = "<?php\n\nreturn " . var_export($routes, true) . ";\n";
    file_put_contents($routesPath, $routesContent);
    echo "   ✅ Route /map-new ajoutée\n";

    // 3. CRÉER .htaccess spécifique pour cette route
    echo "\n3. CRÉATION .htaccess anti-cache pour /map-new...\n";
    
    $htaccessContent = file_get_contents(__DIR__ . '/.htaccess');
    $antiCacheRule = "\n# ANTI-CACHE ULTRA AGRESSIF POUR MAP-NEW\n";
    $antiCacheRule .= "<LocationMatch \"/map-new\">\n";
    $antiCacheRule .= "    Header always set Cache-Control \"no-cache, no-store, must-revalidate, max-age=0\"\n";
    $antiCacheRule .= "    Header always set Pragma \"no-cache\"\n";
    $antiCacheRule .= "    Header always set Expires \"Thu, 01 Jan 1970 00:00:00 GMT\"\n";
    $antiCacheRule .= "    Header always set X-No-Cache \"" . time() . "\"\n";
    $antiCacheRule .= "    FileETag None\n";
    $antiCacheRule .= "</LocationMatch>\n\n";
    
    file_put_contents(__DIR__ . '/.htaccess', $antiCacheRule . $htaccessContent);
    echo "   ✅ .htaccess modifié avec anti-cache spécifique\n";

    // 4. RÉGÉNÉRER autoloader
    echo "\n4. RÉGÉNÉRATION autoloader...\n";
    exec('composer dump-autoload 2>&1', $output, $return);
    if ($return === 0) {
        echo "   ✅ Autoloader régénéré\n";
    } else {
        echo "   ⚠️ Erreur autoloader: " . implode('\n', $output) . "\n";
    }

    // 5. CRÉER page de test comparative
    echo "\n5. CRÉATION page test comparative...\n";
    
    $testPage = __DIR__ . '/public/compare-map-versions.php';
    $testContent = '<?php
header("Cache-Control: no-cache");
?><!DOCTYPE html>
<html><head><title>Comparaison Versions Carte</title>
<style>
body { font-family: Arial; margin: 20px; }
.comparison { display: flex; gap: 20px; }
.version { flex: 1; border: 2px solid #ddd; padding: 10px; }
.version.old { border-color: #dc3545; }
.version.new { border-color: #28a745; }
iframe { width: 100%; height: 400px; border: none; }
h2 { margin-top: 0; }
.old h2 { color: #dc3545; }
.new h2 { color: #28a745; }
</style>
</head>
<body>
<h1>🔍 Comparaison Versions Carte</h1>
<p><strong>Test:</strong> ' . date('Y-m-d H:i:s') . '</p>

<div class="comparison">
    <div class="version old">
        <h2>❌ Version Actuelle /map</h2>
        <p>Version qui reste en cache</p>
        <iframe src="/map"></iframe>
        <p><a href="/map" target="_blank">Ouvrir dans nouvel onglet</a></p>
    </div>
    
    <div class="version new">
        <h2>✅ Version Nouvelle /map-new</h2>
        <p>Contournement cache serveur</p>
        <iframe src="/map-new"></iframe>
        <p><a href="/map-new" target="_blank">Ouvrir dans nouvel onglet</a></p>
    </div>
</div>

<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <h3>📋 Interprétation:</h3>
    <ul>
        <li><strong>Si les deux sont identiques:</strong> Cache navigateur local</li>
        <li><strong>Si /map-new fonctionne mais pas /map:</strong> Cache serveur confirmé</li>
        <li><strong>Si aucun ne fonctionne:</strong> Problème plus profond</li>
        <li><strong>Si les deux fonctionnent:</strong> Cache application était le problème</li>
    </ul>
</div>
</body></html>';
    
    file_put_contents($testPage, $testContent);
    echo "   ✅ Page comparative créée: compare-map-versions.php\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n🎯 CONTOURNEMENT RADICAL CRÉÉ\n";
echo "============================\n";
echo "✅ Nouveau contrôleur: MapNewController\n";
echo "✅ Nouvelle route: /map-new\n";
echo "✅ Headers anti-cache ultra agressifs\n";
echo "✅ Bypass complet de Twig (HTML direct)\n";
echo "✅ Page comparative créée\n\n";

echo "🚀 TESTS IMMÉDIATS:\n";
echo "===================\n";
echo "1. 🔍 /compare-map-versions.php (comparaison côte à côte)\n";
echo "2. 🆕 /map-new (nouvelle version)\n";
echo "3. 🔄 /map (ancienne version pour comparaison)\n\n";

echo "💡 SI /map-new FONCTIONNE:\n";
echo "- Le problème est bien le cache serveur\n";
echo "- On peut alors remplacer la route /map par ce contenu\n";
echo "- Ou configurer Apache/Nginx pour éviter le cache\n\n";

echo "🎉 CONTOURNEMENT PRÊT - TESTEZ MAINTENANT !\n";
?>