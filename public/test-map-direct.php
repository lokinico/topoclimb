<?php
/**
 * TEST DIRECT CARTE - Bypass complet système
 * Compare directement avec /map pour identifier le cache
 */

// Headers anti-cache agressifs
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache"); 
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Last-Modified: " . gmdate('D, d M Y H:i:s') . " GMT");

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TEST DIRECT CARTE - <?= date('H:i:s') ?></title>
    
    <!-- TIMESTAMP UNIQUE: <?= time() ?> -->
    
    <style>
    body { margin: 0; font-family: Arial, sans-serif; }
    .test-header {
        position: fixed; top: 0; left: 0; right: 0; 
        background: #ff0000; color: white; padding: 10px;
        z-index: 10000; font-weight: bold;
    }
    #map { width: 100%; height: 100vh; margin-top: 50px; }
    .status { position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; }
    </style>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="test-header">
        🔴 TEST DIRECT <?= date('Y-m-d H:i:s') ?> - Si vous voyez cette barre rouge = test fonctionne
    </div>
    
    <div id="map"></div>
    
    <div class="status">
        <div>⏱️ Chargé: <?= date('H:i:s') ?></div>
        <div>🎯 Mode: Test direct</div>
        <div id="map-status">🟡 Initialisation...</div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    console.log('🔴 TEST DIRECT CARTE - ' + new Date().toISOString());
    
    const status = document.getElementById('map-status');
    status.textContent = '🟡 Création carte...';
    
    // Carte centrée sur Suisse
    const map = L.map('map').setView([46.8182, 8.2275], 8);
    
    status.textContent = '🟡 Ajout tuiles...';
    
    // Tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    status.textContent = '🟡 Ajout marqueur test...';
    
    // Marqueur test Saillon
    L.marker([46.1817, 7.1947])
        .addTo(map)
        .bindPopup('<b>🏔️ Saillon Test</b><br>Marqueur de test direct<br>Timestamp: <?= date("H:i:s") ?>')
        .openPopup();
    
    status.textContent = '✅ Carte chargée (test direct)';
    
    // Test automatique après 2 secondes
    setTimeout(() => {
        const popup = L.popup()
            .setLatLng([46.8182, 8.2275])
            .setContent('🎯 <b>TEST RÉUSSI</b><br>Si cette carte fonctionne et /map ne fonctionne pas<br>= Problème de cache serveur confirmé')
            .openOn(map);
    }, 2000);
    </script>
</body>
</html>