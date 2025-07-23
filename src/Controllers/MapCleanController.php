<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Response as CoreResponse;

/**
 * MapCleanController - Version propre avec cartes suisses officielles
 * Utilise les services geo.admin.ch
 */
class MapCleanController extends BaseController
{
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
        
        // HTML avec cartes suisses officielles
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TopoclimbCH - Cartes Suisses Officielles</title>
    
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
    
    .controls {
        position: fixed; top: 70px; right: 20px; z-index: 1000;
        background: rgba(255, 255, 255, 0.95);
        padding: 10px; border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    .controls button {
        display: block; width: 100%; margin: 5px 0;
        padding: 8px 12px; border: none; border-radius: 3px;
        background: #2c5aa0; color: white; cursor: pointer;
        font-size: 12px;
    }
    
    .controls button:hover {
        background: #1e3f73;
    }
    
    .status {
        position: fixed; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(44, 90, 160, 0.9); color: white;
        padding: 10px 15px; border-radius: 5px;
        font-size: 14px;
    }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ TopoclimbCH - Cartes Suisses Officielles</h1>
        <p>Powered by geo.admin.ch | Chargé: ' . date('Y-m-d H:i:s') . '</p>
    </div>
    
    <div class="controls">
        <button onclick="switchToPixelkarte()">🗺️ Carte couleur</button>
        <button onclick="switchToOrthophoto()">📸 Photos aériennes</button>
        <button onclick="switchToTopo()">⛰️ Carte topographique</button>
        <button onclick="switchToHiking()">🥾 Cartes de randonnée</button>
        <button onclick="addTestMarkers()">📍 Sites test</button>
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>✅ CARTES SUISSES OFFICIELLES</strong></div>
        <div>Service: geo.admin.ch</div>
        <div>Timestamp: ' . time() . '</div>
        <div id="current-layer">Couche: Carte couleur</div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🇨🇭 CARTES SUISSES OFFICIELLES - " + new Date().toISOString());
    
    // Configuration pour la Suisse (centre et projection)
    const swissCenter = [46.8182, 8.2275]; // Centre de la Suisse
    const swissZoom = 8;
    
    // Initialiser la carte
    const map = L.map("map", {
        center: swissCenter,
        zoom: swissZoom,
        maxZoom: 18,
        minZoom: 6
    });
    
    // Couches de cartes suisses officielles
    const swissLayers = {
        // Carte couleur Swisstopo (par défaut)
        pixelkarte: L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg", {
            attribution: "© swisstopo",
            maxZoom: 18
        }),
        
        // Photos aériennes
        orthophoto: L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg", {
            attribution: "© swisstopo",
            maxZoom: 18
        }),
        
        // Carte topographique
        topo: L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.landeskarte-farbe-10/default/current/3857/{z}/{x}/{y}.jpeg", {
            attribution: "© swisstopo",
            maxZoom: 18
        }),
        
        // Cartes de randonnée  
        hiking: L.tileLayer("https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.wanderkarten500/default/current/3857/{z}/{x}/{y}.jpeg", {
            attribution: "© swisstopo",
            maxZoom: 18
        })
    };
    
    // Ajouter la couche par défaut
    let currentLayer = swissLayers.pixelkarte;
    currentLayer.addTo(map);
    
    // Fonctions de changement de couche
    function switchToPixelkarte() {
        map.removeLayer(currentLayer);
        currentLayer = swissLayers.pixelkarte;
        currentLayer.addTo(map);
        document.getElementById("current-layer").textContent = "Couche: Carte couleur";
    }
    
    function switchToOrthophoto() {
        map.removeLayer(currentLayer);
        currentLayer = swissLayers.orthophoto;
        currentLayer.addTo(map);
        document.getElementById("current-layer").textContent = "Couche: Photos aériennes";
    }
    
    function switchToTopo() {
        map.removeLayer(currentLayer);
        currentLayer = swissLayers.topo;
        currentLayer.addTo(map);
        document.getElementById("current-layer").textContent = "Couche: Topographique";
    }
    
    function switchToHiking() {
        map.removeLayer(currentLayer);
        currentLayer = swissLayers.hiking;
        currentLayer.addTo(map);
        document.getElementById("current-layer").textContent = "Couche: Randonnée";
    }
    
    // Fonction pour ajouter des marqueurs de test
    function addTestMarkers() {
        // Sites d\'escalade célèbres en Suisse
        const testSites = [
            {name: "Saillon", lat: 46.1817, lng: 7.1947, desc: "Site d\'escalade du Valais"},
            {name: "Kandersteg", lat: 46.6037, lng: 7.2625, desc: "Oberland bernois"},
            {name: "Verzasca", lat: 46.4775, lng: 9.5726, desc: "Valle Verzasca, Tessin"},
            {name: "Bürs", lat: 47.1492, lng: 9.8287, desc: "Grisons"},
            {name: "Gastlosen", lat: 46.6165, lng: 7.2833, desc: "Fribourg"}
        ];
        
        testSites.forEach(site => {
            L.marker([site.lat, site.lng])
                .addTo(map)
                .bindPopup(`<b>🏔️ ${site.name}</b><br>${site.desc}<br><small>Coordonnées: ${site.lat}, ${site.lng}</small>`)
                .on("click", () => {
                    console.log(`Site cliqué: ${site.name}`);
                });
        });
        
        console.log("Sites d\'escalade test ajoutés");
    }
    
    // Event listeners pour debugging
    map.on("moveend", () => {
        const center = map.getCenter();
        console.log(`Carte déplacée vers: ${center.lat.toFixed(4)}, ${center.lng.toFixed(4)} - Zoom: ${map.getZoom()}`);
    });
    
    map.on("zoomend", () => {
        console.log(`Niveau de zoom: ${map.getZoom()}`);
    });
    
    // Message de confirmation
    setTimeout(() => {
        const popup = L.popup()
            .setLatLng(swissCenter)
            .setContent("🎉 <strong>CARTES SUISSES CHARGÉES !</strong><br>Utilisez les boutons pour changer de couche<br>Timestamp: ' . time() . '")
            .openOn(map);
            
        // Fermer automatiquement après 3 secondes
        setTimeout(() => {
            map.closePopup(popup);
        }, 3000);
    }, 1000);
    </script>
</body>
</html>';
        
        return new CoreResponse($html);
    }
}