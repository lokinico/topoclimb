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
        <div class="legend-title">🎨 Régions</div>
        <div class="legend-items">
            <div class="legend-item"><span class="legend-color" style="background: #c0392b;"></span> Valais</div>
            <div class="legend-item"><span class="legend-color" style="background: #2980b9;"></span> Vaud</div>
            <div class="legend-item"><span class="legend-color" style="background: #e67e22;"></span> Tessin</div>
            <div class="legend-item"><span class="legend-color" style="background: #27ae60;"></span> Berne</div>
            <div class="legend-item"><span class="legend-color" style="background: #8e44ad;"></span> Jura</div>
            <div class="legend-item"><span class="legend-color" style="background: #34495e;"></span> Grisons</div>
        </div>
        <div class="legend-note">💡 Taille = nombre de voies</div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🇨🇭 TopoclimbCH - Carte suisse interactive");
    
    // Configuration pour la Suisse
    const SWISS_CENTER = [46.8182, 8.2275];
    let map, currentLayer, sitesData = [];
    
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
        loadSitesData();
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
    
    function loadSitesData() {
        // Essayer de charger les données réelles
        fetch("/api/map/sites")
            .then(response => response.json())
            .then(data => {
                if (data.success && data.sites) {
                    sitesData = data.sites;
                    addSiteMarkers();
                } else {
                    loadTestSites();
                }
                updateStatus();
            })
            .catch(error => {
                console.log("Chargement données de test (erreur API)");
                loadTestSites();
                updateStatus();
            });
    }
    
    function loadTestSites() {
        // Sites d\'escalade célèbres en Suisse - Version étendue
        sitesData = [
            // Valais - Sites majeurs
            {name: "Saillon", latitude: 46.1817, longitude: 7.1947, region_name: "Valais", description: "Site sportif réputé", route_count: 120},
            {name: "Vouvry", latitude: 46.3306, longitude: 6.8542, region_name: "Valais", description: "Escalade sur calcaire", route_count: 85},
            {name: "Branson", latitude: 46.1917, longitude: 7.1833, region_name: "Valais", description: "Escalade sur schiste", route_count: 95},
            {name: "Saint-Maurice", latitude: 46.2167, longitude: 7.0167, region_name: "Valais", description: "Falaises calcaires", route_count: 60},
            
            // Vaud 
            {name: "Freyr", latitude: 46.7089, longitude: 6.2333, region_name: "Vaud", description: "Falaise au bord du lac", route_count: 200},
            {name: "Dent de Vaulion", latitude: 46.6833, longitude: 6.3667, region_name: "Vaud", description: "Calcaire jurassien", route_count: 45},
            
            // Tessin - Granit et gneiss
            {name: "Cresciano", latitude: 46.3833, longitude: 8.8667, region_name: "Tessin", description: "Bloc mondial", route_count: 300},
            {name: "Verzasca", latitude: 46.4775, longitude: 9.5726, region_name: "Tessin", description: "Valle Verzasca", route_count: 150},
            {name: "Ponte Brolla", latitude: 46.3972, longitude: 8.8583, region_name: "Tessin", description: "Gneiss de qualité", route_count: 80},
            
            // Berne - Alpes
            {name: "Kandersteg", latitude: 46.6037, longitude: 7.2625, region_name: "Berne", description: "Oberland bernois", route_count: 90},
            {name: "Gimmelwald", latitude: 46.5506, longitude: 7.8958, region_name: "Berne", description: "Vue alpine", route_count: 25},
            {name: "Gastlosen", latitude: 46.6165, longitude: 7.2833, region_name: "Berne", description: "Calcaire alpin", route_count: 110},
            
            // Jura
            {name: "Roc de la Vache", latitude: 47.2167, longitude: 7.0833, region_name: "Jura", description: "Calcaire jurassien", route_count: 60},
            {name: "Creux du Van", latitude: 46.9333, longitude: 6.7, region_name: "Jura", description: "Cirque naturel", route_count: 40},
            
            // Grisons - Haute montagne
            {name: "Bürs", latitude: 47.1492, longitude: 9.8287, region_name: "Grisons", description: "Calcaire alpin", route_count: 70},
            {name: "Rätikon", latitude: 46.9833, longitude: 9.8333, region_name: "Grisons", description: "Massif frontalier", route_count: 55},
            
            // Sites urbains
            {name: "Fluhberg", latitude: 47.3697, longitude: 8.5492, region_name: "Zurich", description: "Proche de Zurich", route_count: 35},
            {name: "Solothurn", latitude: 47.2083, longitude: 7.5333, region_name: "Soleure", description: "Jura soleurois", route_count: 28}
        ];
        
        console.log(`✅ ${sitesData.length} sites d\'escalade suisses chargés`);
        addSiteMarkers();
    }
    
    function addSiteMarkers() {
        sitesData.forEach(site => {
            if (site.latitude && site.longitude) {
                // Couleur selon la région
                const regionColors = {
                    "Valais": "#c0392b",
                    "Vaud": "#2980b9", 
                    "Tessin": "#e67e22",
                    "Berne": "#27ae60",
                    "Jura": "#8e44ad",
                    "Grisons": "#34495e",
                    "Zurich": "#16a085",
                    "Soleure": "#f39c12"
                };
                
                const markerColor = regionColors[site.region_name] || "#e74c3c";
                
                // Taille selon le nombre de voies
                const routeCount = site.route_count || 0;
                const markerSize = Math.max(6, Math.min(12, 6 + (routeCount / 50)));
                
                L.circleMarker([site.latitude, site.longitude], {
                    radius: markerSize,
                    fillColor: markerColor,
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map).bindPopup(`
                    <div style="min-width: 200px;">
                        <h6 style="margin: 0 0 8px 0; color: ${markerColor};">
                            🏔️ ${site.name}
                        </h6>
                        <div style="margin-bottom: 8px;">
                            <strong>📍 ${site.region_name || "Suisse"}</strong>
                        </div>
                        ${site.description ? `<p style="margin: 4px 0; font-size: 13px; color: #666;">${site.description}</p>` : ""}
                        ${routeCount > 0 ? `<div style="margin: 8px 0; padding: 4px 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                            🧗 <strong>${routeCount} voies</strong>
                        </div>` : ""}
                        <div style="margin-top: 8px; font-size: 11px; color: #999;">
                            Coordonnées: ${site.latitude.toFixed(4)}, ${site.longitude.toFixed(4)}
                        </div>
                    </div>
                `);
            }
        });
    }
    
    function updateStatus() {
        document.getElementById("site-count").textContent = sitesData.length;
        document.getElementById("status").textContent = `${sitesData.length} sites chargés`;
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
        
        // Toggle sites
        let sitesVisible = true;
        document.getElementById("sites-btn").addEventListener("click", () => {
            // Cette fonctionnalité sera implémentée quand on aura plus de données
            console.log("Toggle sites - à implémenter");
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