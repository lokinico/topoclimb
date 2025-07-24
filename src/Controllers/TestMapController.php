<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Response as CoreResponse;

class TestMapController extends BaseController
{
    public function index(?Request $request = null): Response
    {
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Carte - TopoclimbCH</title>
    <style>
    body { margin: 0; font-family: Arial, sans-serif; }
    #map { 
        height: 100vh; 
        width: 100%; 
        background: linear-gradient(45deg, #4CAF50, #2196F3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        text-align: center;
    }
    .status {
        position: fixed; 
        bottom: 20px; 
        left: 20px; 
        background: rgba(0,0,0,0.8); 
        color: white;
        padding: 15px; 
        border-radius: 8px;
    }
    </style>
</head>
<body>
    <div id="map">
        <div>
            <h1>🗺️ Test Carte TopoclimbCH</h1>
            <p id="test-result">Initialisation...</p>
        </div>
    </div>
    
    <div class="status">
        <div><strong>Status:</strong> <span id="status">Test JavaScript...</span></div>
        <div>Timestamp: ' . date('Y-m-d H:i:s') . '</div>
    </div>

    <script>
    console.log("🔥 Test carte ultra-simple");
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("✅ DOM prêt");
        
        try {
            document.getElementById("test-result").textContent = "✅ JavaScript fonctionne";
            document.getElementById("status").textContent = "JavaScript OK";
            
            // Test API
            fetch("/api/regions")
                .then(response => {
                    console.log("📡 API test:", response.ok);
                    if (response.ok) {
                        document.getElementById("test-result").innerHTML = 
                            "✅ JavaScript OK<br>✅ API accessible<br>Problème identifié !";
                        document.getElementById("status").textContent = "Tout fonctionne - Problème dans Leaflet";
                    } else {
                        document.getElementById("status").textContent = "API erreur: " + response.status;
                    }
                })
                .catch(error => {
                    console.error("❌ API erreur:", error);
                    document.getElementById("status").textContent = "Fetch erreur: " + error.message;
                });
                
        } catch(e) {
            console.error("❌ Erreur JS:", e);
            document.getElementById("test-result").textContent = "❌ Erreur: " + e.message;
            document.getElementById("status").textContent = "JavaScript cassé: " + e.message;
        }
    });
    </script>
</body>
</html>';

        return new CoreResponse($html);
    }
}