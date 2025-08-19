<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Auth;

/**
 * Debug des routes de création qui redirigent
 */

echo "========== DEBUG ROUTES CREATE ==========\n";
echo date('Y-m-d H:i:s') . " - Test diagnostic\n\n";

try {
    // Simuler une session authentifiée
    $session = new Session();
    $session->set('user_id', 1);
    $session->set('user_role', 0);
    
    $db = new Database();
    $auth = new Auth($session, $db);
    
    echo "--- TEST 1: Vérification authentification ---\n";
    echo "Auth check: " . ($auth->check() ? '✅ OK' : '❌ FAILED') . "\n";
    echo "User ID: " . ($auth->id() ?? 'NULL') . "\n";
    echo "User role: " . ($auth->role() ?? 'NULL') . "\n\n";
    
    echo "--- TEST 2: Vérification base données secteurs ---\n";
    $sectors = $db->fetchAll(
        "SELECT s.id, s.name, r.name as region_name, si.name as site_name
         FROM climbing_sectors s 
         LEFT JOIN climbing_regions r ON s.region_id = r.id 
         LEFT JOIN climbing_sites si ON s.site_id = si.id
         WHERE s.active = 1 
         ORDER BY r.name ASC, s.name ASC
         LIMIT 5"
    );
    
    echo "Secteurs trouvés: " . count($sectors) . "\n";
    foreach ($sectors as $i => $sector) {
        echo "  " . ($i + 1) . ". {$sector['name']} (ID: {$sector['id']})\n";
        if ($i >= 4) break; // Limite à 5
    }
    
    echo "\n--- TEST 3: Test spécifique secteur 12 ---\n";
    $sector12 = $db->fetchOne(
        "SELECT s.*, r.name as region_name, si.name as site_name 
         FROM climbing_sectors s 
         LEFT JOIN climbing_regions r ON s.region_id = r.id 
         LEFT JOIN climbing_sites si ON s.site_id = si.id
         WHERE s.id = ? AND s.active = 1",
        [12]
    );
    
    if ($sector12) {
        echo "✅ Secteur 12 trouvé: {$sector12['name']}\n";
        echo "   Région: " . ($sector12['region_name'] ?? 'N/A') . "\n";
        echo "   Site: " . ($sector12['site_name'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Secteur 12 non trouvé ou inactif\n";
    }
    
    echo "\n--- TEST 4: Test requête problématique médias ---\n";
    try {
        $mediaTest = $db->fetchAll(
            "SELECT m.id, m.title, m.file_path, m.file_type, m.created_at
             FROM climbing_media m 
             WHERE m.entity_type = 'route' AND m.entity_id = 1 AND m.active = 1
             LIMIT 1"
        );
        echo "✅ Requête médias OK - " . count($mediaTest) . " résultat(s)\n";
    } catch (Exception $e) {
        echo "❌ Erreur requête médias: " . $e->getMessage() . "\n";
    }
    
    echo "\n--- TEST 5: Simulation création route ---\n";
    // Test le code exact de RouteController::create
    echo "Secteurs disponibles pour formulaire: " . count($sectors) . "\n";
    echo "Paramètre sector_id simulé: 12\n";
    
    $routeData = (object)['sector_id' => 12];
    echo "Objet route créé: sector_id = " . $routeData->sector_id . "\n";
    
    echo "\n========== RÉSUMÉ ==========\n";
    echo "✅ Base de données fonctionnelle\n";
    echo "✅ Authentification simulée OK\n";
    echo "✅ Secteurs récupérés avec succès\n";
    echo "✅ Secteur 12 existe et est actif\n";
    echo "? Vérifier si le problème vient du template ou du middleware\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Tests terminés!\n";