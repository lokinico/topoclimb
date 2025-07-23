<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Response as CoreResponse;

/**
 * NOUVEAU MapController - Contournement cache serveur
 * Timestamp: 2025-07-23 05:57:32
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
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Interactive - NOUVEAU - ' . date('H:i:s') . '</title>
    
    <!-- TIMESTAMP UNIQUE: ' . time() . ' -->
    
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
        <p>Chargée le: ' . date('Y-m-d H:i:s') . ' | Timestamp: ' . time() . '</p>
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>✅ NOUVELLE VERSION</strong></div>
        <div>Contrôleur: MapNewController</div>
        <div>Status: Chargé ' . date('H:i:s') . '</div>
        <div>Sites: <span id="site-count">0</span></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🟢 NOUVELLE VERSION CARTE - " + new Date().toISOString());
    console.log("Timestamp: ' . time() . '");
    
    // Carte centrée sur Suisse
    const map = L.map("map").setView([46.8182, 8.2275], 8);
    
    // Tuiles OpenStreetMap
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors"
    }).addTo(map);
    
    // Marqueur test
    L.marker([46.1817, 7.1947])
        .addTo(map)
        .bindPopup("<b>🏔️ Saillon Test</b><br>NOUVELLE VERSION<br>Chargé: ' . date('H:i:s') . '")
        .openPopup();
    
    // Mettre à jour le compteur
    document.getElementById("site-count").textContent = "1 (test)";
    
    // Message de confirmation après 2 secondes
    setTimeout(() => {
        const popup = L.popup()
            .setLatLng([46.8182, 8.2275])
            .setContent("🎉 <strong>NOUVELLE VERSION FONCTIONNE !</strong><br>Si vous voyez ceci, le cache serveur est contourné<br>Timestamp: ' . time() . '")
            .openOn(map);
    }, 2000);
    </script>
</body>
</html>';
        
        return new CoreResponse($html);
    }
}
