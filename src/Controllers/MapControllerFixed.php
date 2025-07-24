<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Response as CoreResponse;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

/**
 * Contrôleur pour la carte interactive TopoclimbCH - Version corrigée
 */
class MapControllerFixed extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * Affiche la carte principale - Version fonctionnelle simplifiée
     */
    public function index(?Request $request = null): Response
    {
        // Headers anti-cache
        $headers = [
            "Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private",
            "Pragma: no-cache",
            "Expires: Thu, 01 Jan 1970 00:00:00 GMT",
            "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT",
            "ETag: " . uniqid(),
            "X-Timestamp: " . time(),
        ];
        
        foreach ($headers as $header) {
            header($header);
        }
        
        // HTML basé sur la version fonctionnelle de map-clean
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TopoclimbCH - Carte Interactive</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; font-family: Arial, sans-serif; }
    
    .header {
        position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
        background: #2c5aa0; color: white; padding: 10px 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    
    #map { 
        height: 100vh; width: 100%; 
        padding-top: 60px; box-sizing: border-box;
    }
    
    .status {
        position: fixed; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(44, 90, 160, 0.9); color: white;
        padding: 10px 15px; border-radius: 8px; font-size: 14px;
    }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ TopoclimbCH - Carte Interactive</h1>
        <span>Sites d\'escalade de Suisse - Version corrigée</span>
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>🗺️ Carte Escalade CH</strong></div>
        <div>Status: <span id="status">Chargement...</span></div>
        <div>Sites: <span id="site-count">0</span></div>
        <div>Version: Corrigée ' . date('H:i:s') . '</div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🇨🇭 TopoclimbCH - Carte corrigée - " + new Date().toISOString());
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("✅ DOM chargé");
        
        try {
            // Configuration Suisse
            const swissCenter = [46.8182, 8.2275];
            const swissZoom = 8;
            
            // Initialiser la carte
            const map = L.map("map", {
                center: swissCenter,
                zoom: swissZoom,
                maxZoom: 18,
                minZoom: 6
            });
            
            // Couche Swisstopo
            const swissLayer = L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg", {
                attribution: "© swisstopo",
                maxZoom: 18
            });
            
            swissLayer.addTo(map);
            
            // Marqueur test Saillon
            L.marker([46.1817, 7.1947])
                .addTo(map)
                .bindPopup("<b>🏔️ Saillon</b><br>Site d\'escalade test<br><small>46.1817, 7.1947</small>")
                .openPopup();
            
            // Status
            document.getElementById("status").textContent = "Carte chargée avec succès";
            document.getElementById("site-count").textContent = "1";
            
            console.log("✅ Carte suisse initialisée avec succès");
            
        } catch(error) {
            console.error("❌ Erreur initialisation carte:", error);
            document.getElementById("status").textContent = "Erreur: " + error.message;
        }
    });
    </script>
</body>
</html>';

        return new CoreResponse($html);
    }
}