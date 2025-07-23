<?php
// SOLUTION ULTRA SIMPLE - Headers PHP purs
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Timestamp: 1753252905");

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
        alert("✅ TEST SIMPLE RÉUSSI !\nSi vous voyez cette alerte, la page PHP fonctionne\nTimestamp: <?= time() ?>");
    }, 2000);
    </script>
</body>
</html>