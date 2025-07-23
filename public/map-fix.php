<?php
// SOLUTION TEMPORAIRE - Carte qui fonctionne sur tous les serveurs
// Accès direct : /map-fix.php

// Headers anti-cache
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("ETag: " . uniqid());
header("X-Timestamp: " . time());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte TopoclimbCH - Fix Temporaire - <?= date('H:i:s') ?></title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
    * { margin: 0; padding: 0; }
    html, body { height: 100%; font-family: Arial, sans-serif; }
    
    .header {
        position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
        background: #007bff; color: white; padding: 10px 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    
    #map { 
        height: 100vh; width: 100%; 
        padding-top: 60px; box-sizing: border-box;
    }
    
    .status {
        position: fixed; bottom: 20px; left: 20px; z-index: 1000;
        background: rgba(0, 123, 255, 0.9); color: white;
        padding: 10px 15px; border-radius: 5px;
        backdrop-filter: blur(10px);
    }
    
    .return-btn {
        position: fixed; top: 20px; right: 20px; z-index: 1001;
        background: #28a745; color: white; padding: 8px 16px;
        text-decoration: none; border-radius: 4px;
        font-size: 14px;
    }
    </style>
</head>
<body>
    <div class="header">
        <h1>🗺️ TopoclimbCH - Solution Temporaire</h1>
        <p>Carte fonctionnelle | Chargé: <?= date('Y-m-d H:i:s') ?> | Timestamp: <?= time() ?></p>
    </div>
    
    <a href="/" class="return-btn">← Retour</a>
    
    <div id="map"></div>
    
    <div class="status">
        <div><strong>✅ FIX TEMPORAIRE FONCTIONNE</strong></div>
        <div>URL: /map-fix.php</div>
        <div>Serveur: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'inconnu' ?></div>
        <div>Chargé: <?= date('H:i:s') ?></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log("🔵 CARTE FIX - " + new Date().toISOString());
    console.log("URL: <?= $_SERVER['REQUEST_URI'] ?? 'unknown' ?>");
    console.log("Timestamp: <?= time() ?>");
    
    // Carte centrée sur Suisse
    const map = L.map("map").setView([46.8182, 8.2275], 8);
    
    // Tuiles OpenStreetMap
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors"
    }).addTo(map);
    
    // Marqueur test Saillon
    L.marker([46.1817, 7.1947])
        .addTo(map)
        .bindPopup("<b>🏔️ Saillon</b><br>Solution temporaire<br>Chargé: <?= date('H:i:s') ?>")
        .openPopup();
    
    // Autres marqueurs de test
    L.marker([46.6037, 7.2625]).addTo(map)
        .bindPopup("<b>🏔️ Kandersteg</b><br>Site d'escalade");
    
    L.marker([46.4775, 9.5726]).addTo(map)
        .bindPopup("<b>🏔️ Val Verzasca</b><br>Site d'escalade");
    
    // Message de confirmation
    setTimeout(() => {
        const popup = L.popup()
            .setLatLng([46.8182, 8.2275])
            .setContent("🎉 <strong>CARTE FONCTIONNE !</strong><br>Solution temporaire en attendant le fix du serveur<br>Timestamp: <?= time() ?>")
            .openOn(map);
    }, 2000);
    </script>
</body>
</html>