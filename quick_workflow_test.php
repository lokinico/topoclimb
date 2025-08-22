<?php
/**
 * Test Workflow Utilisateur - Vérification rapide du parcours complet
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🎯 TEST WORKFLOW UTILISATEUR COMPLET" . PHP_EOL;
echo "====================================" . PHP_EOL . PHP_EOL;

$db = new Database();
$baseUrl = 'http://localhost:8000';

function testUrl($url, $description) {
    global $baseUrl;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false
    ]);
    
    $content = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ $description: Erreur cURL - $error" . PHP_EOL;
        return false;
    }
    
    if ($code === 200) {
        echo "  ✅ $description: OK ($code)" . PHP_EOL;
        return true;
    } else {
        echo "  ⚠️ $description: Code $code" . PHP_EOL;
        return false;
    }
}

// 1. Test workflow navigation principal
echo "🗺️ WORKFLOW 1: NAVIGATION PRINCIPALE" . PHP_EOL;
echo "------------------------------------" . PHP_EOL;

$nav_workflow = [
    '/' => 'Page d\'accueil',
    '/regions' => 'Liste des régions',
    '/sites' => 'Liste des sites',
    '/sectors' => 'Liste des secteurs', 
    '/routes' => 'Liste des voies'
];

$nav_success = 0;
foreach ($nav_workflow as $url => $desc) {
    if (testUrl($url, $desc)) {
        $nav_success++;
    }
}

echo "  📊 Navigation: $nav_success/" . count($nav_workflow) . " pages accessibles" . PHP_EOL . PHP_EOL;

// 2. Test workflow découverte géographique
echo "🌍 WORKFLOW 2: DÉCOUVERTE GÉOGRAPHIQUE" . PHP_EOL;
echo "--------------------------------------" . PHP_EOL;

try {
    // Récupérer une région avec sites
    $region = $db->fetchOne(
        "SELECT r.*, COUNT(s.id) as site_count 
         FROM climbing_regions r 
         LEFT JOIN climbing_sites s ON r.id = s.region_id 
         WHERE r.active = 1 
         GROUP BY r.id 
         HAVING site_count > 0 
         LIMIT 1"
    );
    
    if ($region) {
        echo "  🎯 Test avec région: {$region['name']}" . PHP_EOL;
        
        $geo_workflow = [
            "/regions/{$region['id']}" => "Page région {$region['name']}"
        ];
        
        // Récupérer sites de cette région
        $sites = $db->fetchAll(
            "SELECT * FROM climbing_sites WHERE region_id = ? AND active = 1 LIMIT 2",
            [$region['id']]
        );
        
        foreach ($sites as $site) {
            $geo_workflow["/sites/{$site['id']}"] = "Site {$site['name']}";
            
            // Récupérer secteurs de ce site
            $sectors = $db->fetchAll(
                "SELECT * FROM climbing_sectors WHERE site_id = ? AND active = 1 LIMIT 1",
                [$site['id']]
            );
            
            foreach ($sectors as $sector) {
                $geo_workflow["/sectors/{$sector['id']}"] = "Secteur {$sector['name']} (avec widget météo)";
                
                // Récupérer voies de ce secteur
                $routes = $db->fetchAll(
                    "SELECT * FROM climbing_routes WHERE sector_id = ? LIMIT 1",
                    [$sector['id']]
                );
                
                foreach ($routes as $route) {
                    $geo_workflow["/routes/{$route['id']}"] = "Voie {$route['name']}";
                }
            }
        }
        
        $geo_success = 0;
        foreach ($geo_workflow as $url => $desc) {
            if (testUrl($url, $desc)) {
                $geo_success++;
            }
        }
        
        echo "  📊 Géographie: $geo_success/" . count($geo_workflow) . " pages accessibles" . PHP_EOL;
        
    } else {
        echo "  ❌ Aucune région avec sites trouvée pour le test" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "  ❌ Erreur test géographique: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 3. Test APIs critiques
echo "🔌 WORKFLOW 3: APIS CRITIQUES" . PHP_EOL;
echo "-----------------------------" . PHP_EOL;

$apis = [
    '/api/weather/current?lat=46.2044&lng=7.15' => 'API Météo (widget secteurs)',
    '/api/sectors' => 'API Secteurs (cartes)',
    '/api/sites' => 'API Sites',
    '/api/routes' => 'API Voies'
];

$api_success = 0;
foreach ($apis as $url => $desc) {
    if (testUrl($url, $desc)) {
        $api_success++;
    }
}

echo "  📊 APIs: $api_success/" . count($apis) . " endpoints fonctionnels" . PHP_EOL . PHP_EOL;

// 4. Test fonctionnalités critiques
echo "⚡ WORKFLOW 4: FONCTIONNALITÉS CRITIQUES" . PHP_EOL;
echo "----------------------------------------" . PHP_EOL;

// Test widget météo
$sectors_with_gps = $db->fetchAll(
    "SELECT id, name, coordinates_lat, coordinates_lng 
     FROM climbing_sectors 
     WHERE coordinates_lat IS NOT NULL AND coordinates_lng IS NOT NULL 
     LIMIT 2"
);

if (count($sectors_with_gps) > 0) {
    echo "  ✅ Widget météo: " . count($sectors_with_gps) . " secteurs avec GPS disponibles" . PHP_EOL;
    
    foreach ($sectors_with_gps as $sector) {
        echo "    🌤️ Secteur '{$sector['name']}': {$sector['coordinates_lat']}, {$sector['coordinates_lng']}" . PHP_EOL;
    }
} else {
    echo "  ❌ Widget météo: Aucun secteur avec coordonnées GPS" . PHP_EOL;
}

// Test médias
$media_count = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_media")['count'];
echo "  📸 Système médias: $media_count fichiers dans la base" . PHP_EOL;

// Test utilisateurs
$user_count = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
echo "  👥 Système utilisateurs: $user_count comptes" . PHP_EOL;

echo PHP_EOL;

// 5. Résumé et recommandations
echo "📋 RÉSUMÉ & RECOMMANDATIONS IMMÉDIATES" . PHP_EOL;
echo "======================================" . PHP_EOL;

$total_tests = $nav_success + $geo_success + $api_success;
$total_possible = count($nav_workflow) + count($geo_workflow) + count($apis);

echo "🎯 Score global: $total_tests/$total_possible tests réussis (" . round(($total_tests/$total_possible)*100) . "%)" . PHP_EOL . PHP_EOL;

echo "✅ ACTIONS IMMÉDIATES RECOMMANDÉES:" . PHP_EOL;
echo "1. 🧪 Tester manuellement dans navigateur:" . PHP_EOL;
echo "   → Ouvrir http://localhost:8000" . PHP_EOL;
echo "   → Naviguer: Accueil → Régions → Sites → Secteurs → Voies" . PHP_EOL;
echo "   → Vérifier widget météo sur page secteur" . PHP_EOL . PHP_EOL;

echo "2. ⚡ Tester JavaScript:" . PHP_EOL;
echo "   → Ouvrir console développeur (F12)" . PHP_EOL;
echo "   → Vérifier aucune erreur JavaScript" . PHP_EOL;
echo "   → Tester interactions (filtres, pagination)" . PHP_EOL . PHP_EOL;

echo "3. 📱 Tester responsive:" . PHP_EOL;
echo "   → Mode mobile dans navigateur" . PHP_EOL;
echo "   → Vérifier navigation tactile" . PHP_EOL . PHP_EOL;

if ($total_tests >= $total_possible * 0.9) {
    echo "🎉 EXCELLENT! Le site semble prêt pour utilisation." . PHP_EOL;
} elseif ($total_tests >= $total_possible * 0.7) {
    echo "👍 BON! Quelques ajustements mineurs recommandés." . PHP_EOL;
} else {
    echo "⚠️ ATTENTION! Corrections nécessaires avant utilisation." . PHP_EOL;
}

echo PHP_EOL;
echo "💡 Prochaine étape: Test manuel complet dans le navigateur" . PHP_EOL;
echo "🌐 URL: http://localhost:8000" . PHP_EOL;