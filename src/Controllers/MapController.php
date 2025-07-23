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
     */
    public function index(?Request $request = null): Response
    {
        // Headers anti-cache robustes
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
        
        // HTML avec cartes suisses officielles intégrées
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
    
    // Initialisation
    document.addEventListener("DOMContentLoaded", function() {
        initializeMap();
        loadClimbingData();
        setupControls();
    });
    
    function initializeMap() {
        document.getElementById("status").textContent = "Initialisation...";
        
        map = L.map("map", {
            center: SWISS_CENTER,
            zoom: 8,
            zoomControl: false,
            attributionControl: false
        });
        
        // Créer les couches
        Object.keys(swissLayers).forEach(key => {
            swissLayers[key].layer = L.tileLayer(swissLayers[key].url, {
                attribution: "© swisstopo",
                maxZoom: 18
            });
        });
        
        // Couche par défaut
        currentLayer = "pixelkarte";
        swissLayers[currentLayer].layer.addTo(map);
        document.getElementById("layer-name").textContent = swissLayers[currentLayer].name;
        
        document.getElementById("status").textContent = "Carte chargée";
        console.log("✅ Carte suisse initialisée");
    }
    
    function loadClimbingData() {
        document.getElementById("status").textContent = "Chargement données...";
        
        // Essayer de charger les données réelles depuis les APIs
        Promise.all([
            fetch("/api/regions").catch(() => ({ success: false })),
            fetch("/api/sites").catch(() => ({ success: false })),
            fetch("/api/sectors").catch(() => ({ success: false }))
        ]).then(async ([regionsRes, sitesRes, sectorsRes]) => {
            
            // Charger les régions
            if (regionsRes.ok) {
                const regionsData = await regionsRes.json();
                climbingData.regions = regionsData.data || [];
            }
            
            // Charger les sites  
            if (sitesRes.ok) {
                const sitesData = await sitesRes.json();
                climbingData.sites = sitesData.data || [];
            }
            
            // Charger les secteurs
            if (sectorsRes.ok) {
                const sectorsData = await sectorsRes.json();
                climbingData.sectors = sectorsData.data || [];
            }
            
            // Vérifier si les données ont des coordonnées valides
            let hasValidCoordinates = false;
            
            // Vérifier les régions
            if (climbingData.regions.length > 0) {
                hasValidCoordinates = climbingData.regions.some(r => r.coordinates_lat && r.coordinates_lng && r.coordinates_lat !== 0 && r.coordinates_lng !== 0);
            }
            
            // Vérifier les sites si pas de régions valides
            if (!hasValidCoordinates && climbingData.sites.length > 0) {
                hasValidCoordinates = climbingData.sites.some(s => s.coordinates_lat && s.coordinates_lng && s.coordinates_lat !== 0 && s.coordinates_lng !== 0);
            }
            
            // Si pas de coordonnées valides, utiliser les données de test
            if (!hasValidCoordinates) {
                console.log("APIs ne retournent pas de coordonnées valides, utilisation des données de test");
                loadTestHierarchicalData();
            }
            
            initializeClusterGroups();
            addHierarchicalMarkers();
            updateStatus();
            
        }).catch(error => {
            console.log("Erreur chargement APIs:", error);
            console.log("Utilisation des données de test");
            loadTestHierarchicalData();
            initializeClusterGroups();
            addHierarchicalMarkers();
            updateStatus();
        });
    }
    
    function loadTestHierarchicalData() {
        // Structure hiérarchique : Régions → Sites → Secteurs
        
        // RÉGIONS principales de Suisse
        climbingData.regions = [
            {id: 1, name: "Valais", latitude: 46.1947, longitude: 7.144, description: "Région alpine majeure", site_count: 12, total_routes: 850},
            {id: 2, name: "Vaud", latitude: 46.5197, longitude: 6.6323, description: "Région lémanique", site_count: 8, total_routes: 420},
            {id: 3, name: "Tessin", latitude: 46.3353, longitude: 8.8019, description: "Granit et gneiss", site_count: 10, total_routes: 680},
            {id: 4, name: "Berne", latitude: 46.6037, longitude: 7.7461, description: "Oberland bernois", site_count: 6, total_routes: 380},
            {id: 5, name: "Jura", latitude: 47.0502, longitude: 6.9288, description: "Calcaire jurassien", site_count: 5, total_routes: 240},
            {id: 6, name: "Grisons", latitude: 46.8182, longitude: 9.8356, description: "Haute montagne", site_count: 4, total_routes: 190}
        ];
        
        // SITES d\'escalade par région
        climbingData.sites = [
            // Valais
            {id: 1, name: "Saillon", latitude: 46.1817, longitude: 7.1947, region_id: 1, description: "Site sportif réputé", sector_count: 8, route_count: 120},
            {id: 2, name: "Vouvry", latitude: 46.3306, longitude: 6.8542, region_id: 1, description: "Calcaire", sector_count: 6, route_count: 85},
            {id: 3, name: "Branson", latitude: 46.1917, longitude: 7.1833, region_id: 1, description: "Schiste", sector_count: 5, route_count: 95},
            {id: 4, name: "Saint-Maurice", latitude: 46.2167, longitude: 7.0167, region_id: 1, description: "Falaises", sector_count: 4, route_count: 60},
            
            // Vaud
            {id: 5, name: "Freyr", latitude: 46.7089, longitude: 6.2333, region_id: 2, description: "Bord du lac", sector_count: 12, route_count: 200},
            {id: 6, name: "Dent de Vaulion", latitude: 46.6833, longitude: 6.3667, region_id: 2, description: "Jurassien", sector_count: 3, route_count: 45},
            
            // Tessin
            {id: 7, name: "Cresciano", latitude: 46.3833, longitude: 8.8667, region_id: 3, description: "Bloc mondial", sector_count: 15, route_count: 300},
            {id: 8, name: "Verzasca", latitude: 46.4775, longitude: 9.5726, region_id: 3, description: "Valle Verzasca", sector_count: 10, route_count: 150},
            {id: 9, name: "Ponte Brolla", latitude: 46.3972, longitude: 8.8583, region_id: 3, description: "Gneiss", sector_count: 6, route_count: 80},
            
            // Berne
            {id: 10, name: "Kandersteg", latitude: 46.6037, longitude: 7.2625, region_id: 4, description: "Oberland", sector_count: 7, route_count: 90},
            {id: 11, name: "Gimmelwald", latitude: 46.5506, longitude: 7.8958, region_id: 4, description: "Vue alpine", sector_count: 2, route_count: 25},
            {id: 12, name: "Gastlosen", latitude: 46.6165, longitude: 7.2833, region_id: 4, description: "Calcaire alpin", sector_count: 8, route_count: 110}
        ];
        
        // SECTEURS - Certains directement en région, d\'autres dans des sites
        climbingData.sectors = [
            // Secteurs directs en région (Valais)
            {id: 1, name: "Secteur Sion Sud", latitude: 46.2319, longitude: 7.3575, region_id: 1, site_id: null, description: "Directement en région", route_count: 45},
            {id: 2, name: "Secteur Martigny Est", latitude: 46.1024, longitude: 7.0737, region_id: 1, site_id: null, description: "Accès direct", route_count: 38},
            
            // Secteurs dans sites (Saillon)
            {id: 3, name: "Secteur Principal", latitude: 46.1827, longitude: 7.1957, region_id: 1, site_id: 1, description: "Secteur principal de Saillon", route_count: 65},
            {id: 4, name: "Secteur Débutants", latitude: 46.1807, longitude: 7.1937, region_id: 1, site_id: 1, description: "Voies faciles", route_count: 35},
            {id: 5, name: "Secteur Expert", latitude: 46.1837, longitude: 7.1967, region_id: 1, site_id: 1, description: "Hautes difficultés", route_count: 20},
            
            // Secteurs Freyr (Vaud)
            {id: 6, name: "Freyr Rive Droite", latitude: 46.7099, longitude: 6.2343, region_id: 2, site_id: 5, description: "Rive droite", route_count: 120},
            {id: 7, name: "Freyr Rive Gauche", latitude: 46.7079, longitude: 6.2323, region_id: 2, site_id: 5, description: "Rive gauche", route_count: 80},
            
            // Secteurs Cresciano (Tessin)
            {id: 8, name: "Cresciano Central", latitude: 46.3843, longitude: 8.8677, region_id: 3, site_id: 7, description: "Zone centrale", route_count: 180},
            {id: 9, name: "Cresciano Nord", latitude: 46.3853, longitude: 8.8687, region_id: 3, site_id: 7, description: "Zone nord", route_count: 120},
            
            // Secteurs directs Jura
            {id: 10, name: "Creux du Van", latitude: 46.9333, longitude: 6.7, region_id: 5, site_id: null, description: "Cirque naturel", route_count: 40},
            {id: 11, name: "Chasseral", latitude: 47.1319, longitude: 7.0581, region_id: 5, site_id: null, description: "Sommet jurassien", route_count: 25}
        ];
        
        console.log(`✅ Données hiérarchiques chargées: ${climbingData.regions.length} régions, ${climbingData.sites.length} sites, ${climbingData.sectors.length} secteurs`);
    }
    
    function initializeClusterGroups() {
        // Créer les groupes de clusters pour chaque niveau hiérarchique
        clusterGroups.regions = L.markerClusterGroup({
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                let c = " marker-cluster-";
                if (count < 3) {
                    c += "small";
                } else if (count < 6) {
                    c += "medium";
                } else {
                    c += "large";
                }
                return new L.DivIcon({ 
                    html: "<div><span>" + count + "</span></div>", 
                    className: "marker-cluster" + c, 
                    iconSize: new L.Point(40, 40) 
                });
            },
            maxClusterRadius: 80,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        
        clusterGroups.sites = L.markerClusterGroup({
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                return new L.DivIcon({ 
                    html: "<div><span>" + count + "</span></div>", 
                    className: "marker-cluster marker-cluster-medium", 
                    iconSize: new L.Point(35, 35) 
                });
            },
            maxClusterRadius: 60,
            spiderfyOnMaxZoom: true
        });
        
        clusterGroups.sectors = L.markerClusterGroup({
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                return new L.DivIcon({ 
                    html: "<div><span>" + count + "</span></div>", 
                    className: "marker-cluster marker-cluster-small", 
                    iconSize: new L.Point(30, 30) 
                });
            },
            maxClusterRadius: 40,
            spiderfyOnMaxZoom: true
        });
    }
    
    function addHierarchicalMarkers() {
        // Ajouter les marqueurs des RÉGIONS
        climbingData.regions.forEach(region => {
            if (region.coordinates_lat && region.coordinates_lng) {
                const marker = L.circleMarker([region.coordinates_lat, region.coordinates_lng], {
                    radius: 12,
                    fillColor: "#e74c3c",
                    color: "#ffffff",
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.9,
                    className: \"region-marker\"
                });
                
                marker.bindPopup(createRegionPopup(region));
                clusterGroups.regions.addLayer(marker);
            }
        });
        
        // Ajouter les marqueurs des SITES
        climbingData.sites.forEach(site => {
            if (site.coordinates_lat && site.coordinates_lng) {
                const marker = L.circleMarker([site.coordinates_lat, site.coordinates_lng], {
                    radius: 8,
                    fillColor: "#3498db",
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8,
                    className: \"site-marker\"
                });
                
                marker.bindPopup(createSitePopup(site));
                clusterGroups.sites.addLayer(marker);
            }
        });
        
        // Ajouter les marqueurs des SECTEURS
        climbingData.sectors.forEach(sector => {
            if (sector.coordinates_lat && sector.coordinates_lng) {
                const marker = L.circleMarker([sector.coordinates_lat, sector.coordinates_lng], {
                    radius: 6,
                    fillColor: "#2ecc71",
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8,
                    className: \"sector-marker\"
                });
                
                marker.bindPopup(createSectorPopup(sector));
                clusterGroups.sectors.addLayer(marker);
            }
        });
        
        // Ajouter tous les clusters à la carte
        map.addLayer(clusterGroups.regions);
        map.addLayer(clusterGroups.sites);
        map.addLayer(clusterGroups.sectors);
        
        console.log("✅ Marqueurs hiérarchiques ajoutés avec clustering");
    }
    
    function createRegionPopup(region) {
        return "<div style=\"min-width: 220px;\">" +
            "<h5 style=\"margin: 0 0 10px 0; color: #e74c3c; display: flex; align-items: center;\">" +
                "🏔️ <span style=\"margin-left: 8px;\">" + region.name + "</span>" +
            "</h5>" +
            "<div style=\"background: #f8f9fa; padding: 8px; border-radius: 6px; margin-bottom: 10px;\">" +
                "<div style=\"font-size: 13px; color: #666; margin-bottom: 4px;\"><strong>RÉGION</strong></div>" +
                "<div style=\"font-size: 14px; margin-bottom: 6px;\">" + region.description + "</div>" +
            "</div>" +
            "<div style=\"display: flex; gap: 10px; margin-bottom: 8px;\">" +
                "<div style=\"flex: 1; text-align: center; background: #e3f2fd; padding: 6px; border-radius: 4px;\">" +
                    "<div style=\"font-size: 16px; font-weight: bold; color: #1976d2;\">" + (region.site_count || 0) + "</div>" +
                    "<div style=\"font-size: 11px; color: #666;\">Sites</div>" +
                "</div>" +
                "<div style=\"flex: 1; text-align: center; background: #e8f5e8; padding: 6px; border-radius: 4px;\">" +
                    "<div style=\"font-size: 16px; font-weight: bold; color: #388e3c;\">" + (region.total_routes || 0) + "</div>" +
                    "<div style=\"font-size: 11px; color: #666;\">Voies</div>" +
                "</div>" +
            "</div>" +
            "<div style=\"font-size: 11px; color: #999; text-align: center;\">" +
                (region.coordinates_lat || region.latitude || 0).toFixed(4) + ", " + (region.coordinates_lng || region.longitude || 0).toFixed(4) +
            "</div>" +
        "</div>";
    }
    
    function createSitePopup(site) {
        const region = climbingData.regions.find(r => r.id === site.region_id);
        return "<div style=\"min-width: 200px;\">" +
            "<h6 style=\"margin: 0 0 8px 0; color: #3498db; display: flex; align-items: center;\">" +
                "🧗 <span style=\"margin-left: 6px;\">" + site.name + "</span>" +
            "</h6>" +
            "<div style=\"background: #f0f8ff; padding: 6px; border-radius: 4px; margin-bottom: 8px;\">" +
                "<div style=\"font-size: 12px; color: #666; margin-bottom: 2px;\"><strong>SITE</strong> " + (region ? "en " + region.name : "") + "</div>" +
                "<div style=\"font-size: 13px;\">" + site.description + "</div>" +
            "</div>" +
            "<div style=\"display: flex; gap: 8px; margin-bottom: 6px;\">" +
                "<div style=\"flex: 1; text-align: center; background: #e8f5e8; padding: 4px; border-radius: 3px;\">" +
                    "<div style=\"font-size: 14px; font-weight: bold; color: #388e3c;\">" + (site.sector_count || 0) + "</div>" +
                    "<div style=\"font-size: 10px; color: #666;\">Secteurs</div>" +
                "</div>" +
                "<div style=\"flex: 1; text-align: center; background: #fff3e0; padding: 4px; border-radius: 3px;\">" +
                    "<div style=\"font-size: 14px; font-weight: bold; color: #f57c00;\">" + (site.route_count || 0) + "</div>" +
                    "<div style=\"font-size: 10px; color: #666;\">Voies</div>" +
                "</div>" +
            "</div>" +
            "<div style=\"font-size: 10px; color: #999; text-align: center;\">" +
                (site.coordinates_lat || site.latitude || 0).toFixed(4) + ", " + (site.coordinates_lng || site.longitude || 0).toFixed(4) +
            "</div>" +
        "</div>";
    }
    
    function createSectorPopup(sector) {
        const region = climbingData.regions.find(r => r.id === sector.region_id);
        const site = climbingData.sites.find(s => s.id === sector.site_id);
        
        let locationText = "";
        if (site) {
            locationText = "dans " + site.name;
        } else if (region) {
            locationText = "directement en " + region.name;
        }
        
        return "<div style=\"min-width: 180px;\">" +
            "<h6 style=\"margin: 0 0 6px 0; color: #2ecc71; display: flex; align-items: center;\">" +
                "🎯 <span style=\"margin-left: 6px;\">" + sector.name + "</span>" +
            "</h6>" +
            "<div style=\"background: #f0fff0; padding: 6px; border-radius: 4px; margin-bottom: 6px;\">" +
                "<div style=\"font-size: 11px; color: #666; margin-bottom: 2px;\"><strong>SECTEUR</strong> " + locationText + "</div>" +
                "<div style=\"font-size: 12px;\">" + sector.description + "</div>" +
            "</div>" +
            "<div style=\"text-align: center; background: #fff3e0; padding: 6px; border-radius: 4px; margin-bottom: 6px;\">" +
                "<div style=\"font-size: 16px; font-weight: bold; color: #f57c00;\">" + (sector.route_count || 0) + "</div>" +
                "<div style=\"font-size: 11px; color: #666;\">Voies d&#39;escalade</div>" +
            "</div>" +
            "<div style=\"font-size: 10px; color: #999; text-align: center;\">" +
                (sector.coordinates_lat || sector.latitude || 0).toFixed(4) + ", " + (sector.coordinates_lng || sector.longitude || 0).toFixed(4) +
            "</div>" +
        "</div>";
    }
    
    function updateStatus() {
        const totalItems = climbingData.regions.length + climbingData.sites.length + climbingData.sectors.length;
        document.getElementById("site-count").textContent = totalItems;
        document.getElementById("status").textContent = climbingData.regions.length + "R + " + climbingData.sites.length + "S + " + climbingData.sectors.length + "C";
    }
    
    function setupControls() {
        // Changement de couches
        document.getElementById("layers-btn").addEventListener("click", () => {
            const layerKeys = Object.keys(swissLayers);
            const currentIndex = layerKeys.indexOf(currentLayer);
            const nextIndex = (currentIndex + 1) % layerKeys.length;
            const nextLayer = layerKeys[nextIndex];
            
            map.removeLayer(swissLayers[currentLayer].layer);
            swissLayers[nextLayer].layer.addTo(map);
            
            currentLayer = nextLayer;
            document.getElementById("layer-name").textContent = swissLayers[currentLayer].name;
            
            console.log(`Couche changée: ${swissLayers[currentLayer].name}`);
        });
        
        // Géolocalisation
        document.getElementById("locate-btn").addEventListener("click", () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        map.setView([lat, lng], 12);
                        
                        L.circleMarker([lat, lng], {
                            radius: 10,
                            fillColor: "#3b82f6",
                            color: "#ffffff",
                            weight: 3,
                            fillOpacity: 1
                        }).addTo(map).bindPopup("📍 Votre position");
                    },
                    () => alert("Géolocalisation non disponible")
                );
            }
        });
        
        // Toggle hierarchical layers
        document.getElementById("toggle-regions").addEventListener("change", (e) => {
            if (e.target.checked) {
                map.addLayer(clusterGroups.regions);
            } else {
                map.removeLayer(clusterGroups.regions);
            }
        });
        
        document.getElementById("toggle-sites").addEventListener("change", (e) => {
            if (e.target.checked) {
                map.addLayer(clusterGroups.sites);
            } else {
                map.removeLayer(clusterGroups.sites);
            }
        });
        
        document.getElementById("toggle-sectors").addEventListener("change", (e) => {
            if (e.target.checked) {
                map.addLayer(clusterGroups.sectors);
            } else {
                map.removeLayer(clusterGroups.sectors);
            }
        });
        
        // Toggle all sites (bouton principal)
        document.getElementById("sites-btn").addEventListener("click", () => {
            const regionsVisible = map.hasLayer(clusterGroups.regions);
            const sitesVisible = map.hasLayer(clusterGroups.sites);
            const sectorsVisible = map.hasLayer(clusterGroups.sectors);
            
            // Si tous sont visibles, tout masquer, sinon tout afficher
            if (regionsVisible && sitesVisible && sectorsVisible) {
                map.removeLayer(clusterGroups.regions);
                map.removeLayer(clusterGroups.sites);
                map.removeLayer(clusterGroups.sectors);
                document.getElementById("toggle-regions").checked = false;
                document.getElementById("toggle-sites").checked = false;
                document.getElementById("toggle-sectors").checked = false;
            } else {
                map.addLayer(clusterGroups.regions);
                map.addLayer(clusterGroups.sites);
                map.addLayer(clusterGroups.sectors);
                document.getElementById("toggle-regions").checked = true;
                document.getElementById("toggle-sites").checked = true;
                document.getElementById("toggle-sectors").checked = true;
            }
        });
    }
    </script>
</body>
</html>';
        
        return new CoreResponse($html);
    }

    /**
     * API pour récupérer les données des sites en format JSON
     */
    public function apiSites(?Request $request = null): Response
    {
        try {
            $filters = [
                'region_id' => $_GET['region'] ?? null,
                'difficulty_min' => $_GET['difficulty_min'] ?? null,
                'difficulty_max' => $_GET['difficulty_max'] ?? null,
                'type' => $_GET['type'] ?? null,
                'season' => $_GET['season'] ?? null
            ];

            $sites = [];
            
            try {
                $sites = $this->getSitesForMap($filters);
            } catch (\Exception $dbException) {
                error_log("MapController::apiSites - Erreur DB, utilisation des données de test");
                
                // En cas d'erreur DB, utiliser les données de test
                $sites = $this->getTestSites();
                
                // Appliquer les filtres aux données de test
                $sites = $this->filterTestSites($sites, $filters);
            }

            return $this->json([
                'success' => true,
                'sites' => $sites,
                'count' => count($sites)
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiSites: " . $e->getMessage());
            
            // En dernier recours, retourner les données de test sans filtre
            $fallbackSites = $this->getTestSites();
            
            return $this->json([
                'success' => true,
                'sites' => $fallbackSites,
                'count' => count($fallbackSites),
                'warning' => 'Données de test utilisées'
            ]);
        }
    }

    /**
     * API pour récupérer les détails d'un site spécifique
     */
    public function apiSiteDetails(?Request $request = null): Response
    {
        try {
            // Récupérer l'ID depuis l'URL (assumé être passé en paramètre)
            $pathInfo = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/api\/map\/sites\/(\d+)/', $pathInfo, $matches);
            $siteId = $matches[1] ?? null;
            
            $site = Site::find($siteId);
            if (!$site) {
                return $this->json([
                    'success' => false,
                    'error' => 'Site non trouvé'
                ], 404);
            }

            // Récupérer les secteurs et voies du site
            $sectors = Sector::where('site_id', $siteId);
            $routes = [];
            
            foreach ($sectors as $sector) {
                $sectorRoutes = Route::where('sector_id', $sector->id);
                $routes = array_merge($routes, $sectorRoutes);
            }

            // Calculer les statistiques du site
            $stats = [
                'total_sectors' => count($sectors),
                'total_routes' => count($routes),
                'difficulty_range' => $this->calculateDifficultyRange($routes),
                'route_types' => $this->calculateRouteTypes($routes)
            ];

            return $this->json([
                'success' => true,
                'site' => $site,
                'sectors' => $sectors,
                'routes' => $routes,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiSiteDetails: " . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des détails'
            ], 500);
        }
    }

    /**
     * API pour la recherche géographique
     */
    public function apiGeoSearch(?Request $request = null): Response
    {
        try {
            $query = $_GET['q'] ?? null;
            $lat = $_GET['lat'] ?? null;
            $lng = $_GET['lng'] ?? null;
            $radius = $_GET['radius'] ?? 50; // 50km par défaut

            $results = [];

            if ($query) {
                // Recherche par nom
                $results = $this->searchByName($query);
            } elseif ($lat && $lng) {
                // Recherche par proximité
                $results = $this->searchByProximity($lat, $lng, $radius);
            }

            return $this->json([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiGeoSearch: " . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * Récupère les sites pour l'affichage sur la carte
     */
    private function getSitesForMap(array $filters): array
    {
        try {
            $sites = Site::all();
            $sitesForMap = [];

            foreach ($sites as $site) {
                // Accéder aux propriétés directement - CORRIGER LES NOMS DE COLONNES
                $siteData = [
                    'id' => $site->id,
                    'name' => $site->name,
                    'latitude' => $site->coordinates_lat, // CORRIGÉ
                    'longitude' => $site->coordinates_lng, // CORRIGÉ
                    'region_id' => $site->region_id,
                    'description' => $site->description,
                    'approach_time' => $site->approach_time
                ];
                
                // Vérifier que le site a des coordonnées valides (pas null, pas 0)
                if (is_null($siteData['latitude']) || is_null($siteData['longitude']) || 
                    $siteData['latitude'] == 0 || $siteData['longitude'] == 0) {
                    // Si pas de coordonnées, utiliser des coordonnées par défaut pour la région
                    $regionId = $site->region_id ? (int)$site->region_id : 1; // Défaut région 1 si null
                    $defaultCoords = $this->getDefaultRegionCoordinates($regionId);
                    $siteData['latitude'] = $defaultCoords['lat'];
                    $siteData['longitude'] = $defaultCoords['lng'];
                    $siteData['coordinates_estimated'] = true; // Marquer comme estimé
                }

                // Appliquer les filtres
                if (!$this->passeFilters($siteData, $filters)) {
                    continue;
                }

                try {
                    // Récupérer les informations supplémentaires
                    $region = Region::find($siteData['region_id']);
                    $sectors = Sector::where('site_id', $siteData['id']);
                    $routeCount = 0;
                    
                    foreach ($sectors as $sector) {
                        $routes = Route::where('sector_id', $sector->id);
                        $routeCount += count($routes);
                    }

                    $sitesForMap[] = [
                        'id' => $siteData['id'],
                        'name' => $siteData['name'],
                        'latitude' => (float) $siteData['latitude'],
                        'longitude' => (float) $siteData['longitude'],
                        'region_name' => $region ? $region->name : 'Région inconnue',
                        'region_id' => $siteData['region_id'],
                        'description' => $siteData['description'] ?? '',
                        'approach_time' => $siteData['approach_time'] ?? null,
                        'sector_count' => count($sectors),
                        'route_count' => $routeCount,
                        'url' => '/sites/' . $siteData['id']
                    ];
                    
                } catch (\Exception $siteException) {
                    error_log("MapController::getSitesForMap - Erreur lors du traitement du site " . $siteData['name'] . ": " . $siteException->getMessage());
                    // Continuer avec le site suivant
                    continue;
                }
            }

            return $sitesForMap;

        } catch (\Exception $e) {
            error_log("Erreur getSitesForMap: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-lancer l'exception pour que apiSites puisse utiliser les données de test
        }
    }

    /**
     * Vérifie si un site passe les filtres
     */
    private function passeFilters($site, array $filters): bool
    {
        // Filtre par région
        if (!empty($filters['region_id']) && $site['region_id'] != $filters['region_id']) {
            return false;
        }

        // TODO: Implémenter d'autres filtres (difficulté, type, saison)
        // Ces filtres nécessiteraient d'analyser les voies du site

        return true;
    }

    /**
     * Calcule les statistiques pour la carte
     */
    private function getMapStatistics(): array
    {
        try {
            $totalSites = count(Site::all());
            $totalRegions = count(Region::all());
            $totalSectors = count(Sector::all());
            $totalRoutes = count(Route::all());

            return [
                'total_sites' => $totalSites,
                'total_regions' => $totalRegions,
                'total_sectors' => $totalSectors,
                'total_routes' => $totalRoutes
            ];

        } catch (\Exception $e) {
            error_log("Erreur getMapStatistics: " . $e->getMessage());
            return [
                'total_sites' => 0,
                'total_regions' => 0,
                'total_sectors' => 0,
                'total_routes' => 0
            ];
        }
    }

    /**
     * Recherche par nom de site
     */
    private function searchByName(string $query): array
    {
        $results = [];
        $sites = Site::all();

        foreach ($sites as $site) {
            if (stripos($site['name'], $query) !== false || 
                stripos($site['description'] ?? '', $query) !== false) {
                
                if (!empty($site['latitude']) && !empty($site['longitude'])) {
                    $results[] = [
                        'id' => $site['id'],
                        'name' => $site['name'],
                        'type' => 'site',
                        'latitude' => (float) $site['latitude'],
                        'longitude' => (float) $site['longitude'],
                        'url' => '/sites/' . $site['id']
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Recherche par proximité géographique
     */
    private function searchByProximity(float $lat, float $lng, float $radius): array
    {
        $results = [];
        $sites = Site::all();

        foreach ($sites as $site) {
            if (empty($site['latitude']) || empty($site['longitude'])) {
                continue;
            }

            $distance = $this->calculateDistance(
                $lat, $lng, 
                (float) $site['latitude'], 
                (float) $site['longitude']
            );

            if ($distance <= $radius) {
                $results[] = [
                    'id' => $site['id'],
                    'name' => $site['name'],
                    'type' => 'site',
                    'latitude' => (float) $site['latitude'],
                    'longitude' => (float) $site['longitude'],
                    'distance' => round($distance, 1),
                    'url' => '/sites/' . $site['id']
                ];
            }
        }

        // Trier par distance
        usort($results, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $results;
    }

    /**
     * Calcule la distance entre deux points en kilomètres
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Rayon de la terre en kilomètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Calcule la gamme de difficulté des voies
     */
    private function calculateDifficultyRange(array $routes): array
    {
        if (empty($routes)) {
            return ['min' => null, 'max' => null];
        }

        $grades = [];
        foreach ($routes as $route) {
            if (!empty($route['difficulty_grade'])) {
                $grades[] = $route['difficulty_grade'];
            }
        }

        if (empty($grades)) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => min($grades),
            'max' => max($grades)
        ];
    }

    /**
     * Calcule la répartition des types de voies
     */
    private function calculateRouteTypes(array $routes): array
    {
        $types = [];
        
        foreach ($routes as $route) {
            $type = $route['route_type'] ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return $types;
    }

    /**
     * Données de test pour les régions suisses
     */
    private function getTestRegions(): array
    {
        return [
            ['id' => 1, 'name' => 'Valais', 'active' => 1],
            ['id' => 2, 'name' => 'Jura', 'active' => 1],
            ['id' => 3, 'name' => 'Grisons', 'active' => 1],
            ['id' => 4, 'name' => 'Tessin', 'active' => 1],
            ['id' => 5, 'name' => 'Vaud', 'active' => 1],
            ['id' => 6, 'name' => 'Berne', 'active' => 1]
        ];
    }

    /**
     * Données de test pour les sites d'escalade suisses populaires
     */
    private function getTestSites(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Saillon',
                'latitude' => 46.1847,
                'longitude' => 7.1883,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Site d\'escalade sportive réputé en Valais',
                'approach_time' => 5,
                'sector_count' => 8,
                'route_count' => 120,
                'url' => '/sites/1'
            ],
            [
                'id' => 2,
                'name' => 'Vouvry',
                'latitude' => 46.3306,
                'longitude' => 6.8542,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Escalade sportive sur calcaire',
                'approach_time' => 10,
                'sector_count' => 6,
                'route_count' => 85,
                'url' => '/sites/2'
            ],
            [
                'id' => 3,
                'name' => 'Freyr',
                'latitude' => 46.7089,
                'longitude' => 6.2333,
                'region_name' => 'Vaud',
                'region_id' => 5,
                'description' => 'Falaise calcaire au bord du lac',
                'approach_time' => 3,
                'sector_count' => 12,
                'route_count' => 200,
                'url' => '/sites/3'
            ],
            [
                'id' => 4,
                'name' => 'Pont du Diable',
                'latitude' => 46.6547,
                'longitude' => 8.5883,
                'region_name' => 'Tessin',
                'region_id' => 4,
                'description' => 'Escalade sur granit en montagne',
                'approach_time' => 20,
                'sector_count' => 4,
                'route_count' => 45,
                'url' => '/sites/4'
            ],
            [
                'id' => 5,
                'name' => 'Roc de la Vache',
                'latitude' => 47.2167,
                'longitude' => 7.0833,
                'region_name' => 'Jura',
                'region_id' => 2,
                'description' => 'Escalade traditionnelle sur calcaire jurassien',
                'approach_time' => 15,
                'sector_count' => 5,
                'route_count' => 60,
                'url' => '/sites/5'
            ],
            [
                'id' => 6,
                'name' => 'Gimmelwald',
                'latitude' => 46.5506,
                'longitude' => 7.8958,
                'region_name' => 'Berne',
                'region_id' => 6,
                'description' => 'Escalade alpine avec vue sur les Alpes',
                'approach_time' => 30,
                'sector_count' => 3,
                'route_count' => 25,
                'url' => '/sites/6'
            ],
            [
                'id' => 7,
                'name' => 'Cresciano',
                'latitude' => 46.3833,
                'longitude' => 8.8667,
                'region_name' => 'Tessin',
                'region_id' => 4,
                'description' => 'Bloc de renommée mondiale',
                'approach_time' => 5,
                'sector_count' => 10,
                'route_count' => 300,
                'url' => '/sites/7'
            ],
            [
                'id' => 8,
                'name' => 'Branson',
                'latitude' => 46.1917,
                'longitude' => 7.1833,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Escalade sportive sur schiste',
                'approach_time' => 8,
                'sector_count' => 7,
                'route_count' => 95,
                'url' => '/sites/8'
            ]
        ];
    }

    /**
     * Applique les filtres aux données de test
     */
    private function filterTestSites(array $sites, array $filters): array
    {
        $filteredSites = [];

        foreach ($sites as $site) {
            // Filtre par région
            if (!empty($filters['region_id']) && $site['region_id'] != $filters['region_id']) {
                continue;
            }

            // Pour les autres filtres (difficulté, type, saison), on accepte tous les sites
            // car les données de test ne contiennent pas ces informations détaillées
            
            $filteredSites[] = $site;
        }

        return $filteredSites;
    }

    /**
     * Retourne des coordonnées par défaut pour une région donnée
     */
    private function getDefaultRegionCoordinates(?int $regionId): array
    {
        // Coordonnées par défaut pour les principales régions suisses
        $defaultCoordinates = [
            1 => ['lat' => 46.8182, 'lng' => 8.2275], // Centre Suisse (défaut)
            2 => ['lat' => 46.1947, 'lng' => 7.1440], // Valais (Sion)
            3 => ['lat' => 46.6037, 'lng' => 7.2625], // Oberland bernois (Kandersteg)
            4 => ['lat' => 46.5197, 'lng' => 9.7970], // Grisons (Davos)
            5 => ['lat' => 46.0037, 'lng' => 8.9511], // Tessin (Bellinzona)
            6 => ['lat' => 46.7985, 'lng' => 6.6327], // Vaud (Lausanne)
            7 => ['lat' => 46.2044, 'lng' => 6.1432], // Genève
            8 => ['lat' => 47.0502, 'lng' => 6.9288], // Fribourg
            9 => ['lat' => 47.3769, 'lng' => 8.5417], // Zurich
            10 => ['lat' => 47.2692, 'lng' => 7.3398], // Jura (Soleure)
        ];

        // Retourner les coordonnées pour la région ou le centre de la Suisse par défaut
        return $defaultCoordinates[$regionId ?? 1] ?? $defaultCoordinates[1];
    }
}