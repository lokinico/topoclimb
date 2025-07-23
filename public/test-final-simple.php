<?php
header("Cache-Control: no-cache");
?><!DOCTYPE html>
<html><head><title>Test Final Simple</title>
<style>
body { font-family: Arial; margin: 20px; }
.test { margin: 20px 0; padding: 15px; border: 2px solid #ddd; border-radius: 5px; }
.success { border-color: #28a745; background: #f8fff9; }
.error { border-color: #dc3545; background: #fff8f8; }
.btn { 
    background: #007bff; color: white; padding: 10px 20px; 
    text-decoration: none; border-radius: 5px; margin: 5px; 
    display: inline-block; font-weight: bold;
}
.btn.success { background: #28a745; }
.btn.danger { background: #dc3545; }
</style>
</head>
<body>
<h1>🎯 Test Final Simple - <?= date("H:i:s") ?></h1>

<div class="test success">
    <h2>✅ Test PHP Basique</h2>
    <p>Cette page PHP fonctionne. Timestamp: <?= time() ?></p>
</div>

<div class="test">
    <h2>🧪 Tests à Effectuer</h2>
    <p><strong>Ordre de test recommandé :</strong></p>
    
    <h3>1. Test Ultra-Simple (doit marcher)</h3>
    <a href="/map-simple-test.php" target="_blank" class="btn success">Test Simple</a>
    <p><small>Doit afficher header bleu + carte + popup automatique</small></p>
    
    <h3>2. Test Nouvelle Route (contournement)</h3>
    <a href="/map-new" target="_blank" class="btn">Test /map-new</a>
    <p><small>Doit afficher header vert "NOUVELLE VERSION"</small></p>
    
    <h3>3. Test Route Originale (problématique)</h3>
    <a href="/map" target="_blank" class="btn danger">Test /map</a>
    <p><small>Version qui reste en cache</small></p>
</div>

<div class="test">
    <h2>📊 Interprétation</h2>
    <ul>
        <li><strong>Test Simple fonctionne :</strong> PHP et serveur OK</li>
        <li><strong>/map-new fonctionne :</strong> Nouvelle route contourne le cache</li>
        <li><strong>/map reste buggé :</strong> Cache serveur sur route spécifique</li>
        <li><strong>Tous identiques :</strong> Cache navigateur ou résolu</li>
    </ul>
</div>

<div class="test error">
    <h2>🚨 Si Rien ne Fonctionne</h2>
    <p>Problème plus profond :</p>
    <ul>
        <li>Vérifier logs Apache : <code>/var/log/apache2/error.log</code></li>
        <li>Redémarrer Apache/Nginx sur Plesk</li>
        <li>Vérifier permissions fichiers</li>
        <li>Tester en mode privé navigateur</li>
    </ul>
</div>
</body></html>