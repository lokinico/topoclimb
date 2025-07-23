<?php
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
<p><strong>Test:</strong> 2025-07-23 05:57:35</p>

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
</body></html>