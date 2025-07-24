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
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;

/**
 * Contrôleur pour la carte interactive TopoclimbCH
 * Affiche une carte interactive avec tous les sites d'escalade suisses
 */
class MapController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        
        // Injecter la base de données dans les modèles pour éviter les problèmes de singleton
        \TopoclimbCH\Models\Region::setDatabase($this->db);
        \TopoclimbCH\Models\Site::setDatabase($this->db);
        \TopoclimbCH\Models\Sector::setDatabase($this->db);
        \TopoclimbCH\Models\Route::setDatabase($this->db);
    }

    /**
     * Affiche la carte principale avec cartes suisses officielles
     * JavaScript extrait dans /public/js/components/
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
    <title>TopoclimbCH - Carte Interactive Sites d\'Escalade</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
    
    .header {
        position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
        padding: 10px 20px; border-bottom: 1px solid rgba(0,0,0,0.1);
        display: flex; justify-content: space-between; align-items: center;
    }
    
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #333; font-weight: 600; }
    .nav-controls { display: flex; gap: 10px; }
    .nav-btn { background: none; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; 
               cursor: pointer; color: #666; text-decoration: none; transition: all 0.2s; font-size: 14px; }
    .nav-btn:hover { background: #f5f5f5; color: #333; text-decoration: none; }
    
    #map { 
        height: 100vh; width: 100%; 
        padding-top: 60px; box-sizing: border-box;
    }
    
    .controls {
        position: fixed; top: 70px; right: 20px; z-index: 1000;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
        padding: 10px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: flex; flex-direction: column; gap: 5px;
    }
    
    .control-btn {
        width: 40px; height: 40px; border: none; border-radius: 6px;
        background: transparent; cursor: pointer; display: flex;
        align-items: center; justify-content: center; color: #666;
        transition: all 0.2s; font-size: 16px;
    }
    
    .control-btn:hover { background: rgba(0, 0, 0, 0.05); color: #333; }
    
    .status {
        position: fixed; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(44, 90, 160, 0.9); color: white;
        padding: 10px 15px; border-radius: 8px; font-size: 14px;
    }
    
    .legend {
        position: fixed; bottom: 20px; right: 20px; z-index: 1000;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
        padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-size: 13px; min-width: 140px;
    }
    
    .legend-title { font-weight: bold; margin-bottom: 8px; text-align: center; }
    
    .legend-items { margin-bottom: 8px; }
    
    .legend-item {
        display: flex; align-items: center; margin: 3px 0;
        font-size: 12px;
    }
    
    .legend-color {
        width: 12px; height: 12px; border-radius: 50%;
        margin-right: 6px; border: 1px solid #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    
    .legend-note {
        font-size: 11px; color: #666; text-align: center;
        font-style: italic; border-top: 1px solid #eee; padding-top: 6px;
    }
    
    /* Styles personnalisés pour les clusters */
    .marker-cluster-small {
        background-color: rgba(181, 226, 140, 0.8);
        border: 2px solid rgba(110, 204, 57, 0.8);
    }
    .marker-cluster-small div {
        background-color: rgba(110, 204, 57, 0.8);
        color: white;
        font-weight: bold;
    }
    
    .marker-cluster-medium {
        background-color: rgba(241, 211, 87, 0.8);
        border: 2px solid rgba(240, 194, 12, 0.8);
    }
    .marker-cluster-medium div {
        background-color: rgba(240, 194, 12, 0.8);
        color: white;
        font-weight: bold;
    }
    
    .marker-cluster-large {
        background-color: rgba(253, 156, 115, 0.8);
        border: 2px solid rgba(241, 128, 23, 0.8);
    }
    .marker-cluster-large div {
        background-color: rgba(241, 128, 23, 0.8);
        color: white;
        font-weight: bold;
    }
    
    /* Styles pour les marqueurs par niveau hiérarchique */
    .region-marker {
        background: linear-gradient(45deg, #e74c3c, #c0392b);
        border: 3px solid white;
        box-shadow: 0 3px 10px rgba(0,0,0,0.3);
    }
    
    .site-marker {
        background: linear-gradient(45deg, #3498db, #2980b9);
        border: 2px solid white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .sector-marker {
        background: linear-gradient(45deg, #2ecc71, #27ae60);
        border: 2px solid white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    
    @media (max-width: 768px) {
        .header { padding: 8px 15px; }
        .nav-controls { gap: 5px; }
        .nav-btn { padding: 6px 10px; font-size: 13px; }
        .controls { top: 60px; right: 15px; }
        .control-btn { width: 36px; height: 36px; font-size: 14px; }
        .status { bottom: 15px; left: 15px; font-size: 13px; }
        .legend { 
            bottom: 100px; right: 15px; font-size: 12px; 
            min-width: 120px; padding: 8px 12px;
        }
        .legend-item { font-size: 11px; }
        .legend-note { font-size: 10px; }
    }
    </style>
</head>
<body>
    <div class="header">
        <a href="/" class="brand">
            <i class="fas fa-mountain"></i>
            TopoclimbCH
        </a>
        <div class="nav-controls">
            <a href="/regions" class="nav-btn">
                <i class="fas fa-list"></i> Liste
            </a>
            <a href="/sites" class="nav-btn">
                <i class="fas fa-mountain"></i> Sites
            </a>
        </div>
    </div>
    
    <div class="controls">
        <button id="layers-btn" class="control-btn" title="Changer fond">
            <i class="fas fa-layer-group"></i>
        </button>
        <button id="locate-btn" class="control-btn" title="Ma position">
            <i class="fas fa-crosshairs"></i>
        </button>
        <button id="sites-btn" class="control-btn" title="Sites escalade">
            <i class="fas fa-map-marker-alt"></i>
        </button>
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>🗺️ Carte Escalade CH</strong></div>
        <div>Service: <span id="layer-name">Swisstopo</span></div>
        <div>Sites: <span id="site-count">0</span></div>
        <div>Status: <span id="status">Chargement...</span></div>
    </div>
    
    <div class="legend">
        <div class="legend-title">🗺️ Hiérarchie</div>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-color" style="background: #e74c3c;"></span> 
                <span>Régions</span>
                <input type="checkbox" id="toggle-regions" checked style="margin-left: auto;">
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: #3498db;"></span> 
                <span>Sites</span>
                <input type="checkbox" id="toggle-sites" checked style="margin-left: auto;">
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: #2ecc71;"></span> 
                <span>Secteurs</span>
                <input type="checkbox" id="toggle-sectors" checked style="margin-left: auto;">
            </div>
        </div>
        <div class="legend-note">💡 Clusters automatiques</div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
    console.log("🇨🇭 TopoclimbCH - Carte suisse interactive");
    
    // Configuration pour la Suisse
    const SWISS_CENTER = [46.8182, 8.2275];
    let map, currentLayer;
    let climbingData = {
        regions: [],
        sites: [],
        sectors: []
    };
    let clusterGroups = {
        regions: null,
        sites: null,
        sectors: null
    };
    
    // Couches de cartes suisses officielles
    const swissLayers = {
        pixelkarte: {
            layer: null,
            name: "Carte couleur",
            url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg"
        },
        orthophoto: {
            layer: null,
            name: "Photos aériennes", 
            url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg"
        },
        topo: {
            layer: null,
            name: "Topographique",
            url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.landeskarte-farbe-10/default/current/3857/{z}/{x}/{y}.jpeg"
        },
        hiking: {
            layer: null,
            name: "Randonnée",
            url: "https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.wanderkarten500/default/current/3857/{z}/{x}/{y}.jpeg"
        }
    };
    
    // Version fonctionnelle basée sur map-clean
    document.addEventListener("DOMContentLoaded", function() {
        console.log("🇨🇭 CARTES SUISSES OFFICIELLES - " + new Date().toISOString());
        
        // Configuration pour la Suisse  
        const swissCenter = [46.8182, 8.2275];
        const swissZoom = 8;
        
        // Initialiser la carte avec Swisstopo
        const map = L.map("map", {
            center: swissCenter,
            zoom: swissZoom,
            maxZoom: 18,
            minZoom: 6
        });
        
        // Couche Swisstopo par défaut
        const swissLayer = L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg", {
            attribution: "© swisstopo",
            maxZoom: 18
        });
        
        swissLayer.addTo(map);
        
        // Marqueur test de Saillon
        L.marker([46.1817, 7.1947])
            .addTo(map)
            .bindPopup("<b>🏔️ Saillon</b><br>Site d escalade de test<br><small>Coordonnées: 46.1817, 7.1947</small>")
            .openPopup();
        
        // Status
        document.getElementById("status").textContent = "Carte suisse chargée";
        document.getElementById("site-count").textContent = "1";
        console.log("✅ Carte suisse initialisée avec succès");
    });
    </script>
</body>
</html>';

        return new CoreResponse($html);
    }

    /**
     * API pour récupérer les sites  
     */
    public function apiSites(?Request $request = null): Response
    {
        return $this->json([
            'success' => true,
            'data' => [],
            'message' => 'API temporairement désactivée - utiliser MapControllerFixed'
        ]);
    }
}
