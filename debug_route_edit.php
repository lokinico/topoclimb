<?php
/**
 * Debug spécifique pour l'erreur 500 sur l'edit des routes
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== DEBUG ROUTE EDIT ERROR 500 ===\n\n";

// Test simple d'appel à la méthode update directement
echo "🔍 1. Test données route...\n";

$route = $db->fetchOne("SELECT * FROM climbing_routes WHERE id = 1");
if ($route) {
    echo "   ✅ Route ID 1 trouvée: {$route['name']}\n";
    echo "   Secteur: {$route['sector_id']}, Difficulté: {$route['difficulty']}\n";
} else {
    echo "   ❌ Route ID 1 non trouvée\n";
}

echo "\n🔍 2. Test mise à jour directe en base...\n";

try {
    $updateData = [
        'name' => 'Test Update ' . date('H:i:s'),
        'description' => 'Description mise à jour par test debug',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $updated = $db->update('climbing_routes', $updateData, 'id = ?', [1]);
    
    if ($updated) {
        echo "   ✅ Mise à jour directe réussie ($updated ligne(s) modifiée(s))\n";
    } else {
        echo "   ⚠️ Mise à jour retourne 0 (aucune ligne modifiée)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erreur mise à jour: " . $e->getMessage() . "\n";
}

echo "\n🔍 3. Test curl avec détails complets...\n";

// Test de l'endpoint avec plus de détails
$postData = [
    'name' => 'Voie Debug ' . date('H:i:s'),
    'description' => 'Description debug',
    'sector_id' => $route['sector_id'] ?? 1,
    'difficulty' => '6a',
    'length' => '25',
    'grade_value' => '6',
    'beauty_rating' => '3',
    'danger_rating' => '2',
    '_token' => 'fake_token_for_debug'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8000/routes/1/edit',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => '/tmp/debug_cookies.txt',
    CURLOPT_COOKIEFILE => '/tmp/debug_cookies.txt',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";

if ($error) {
    echo "   Erreur cURL: $error\n";
} else {
    echo "   Réponse reçue (" . strlen($response) . " caractères)\n";
    
    // Chercher des erreurs spécifiques dans la réponse
    if (preg_match('/Fatal error:[^<\n]*/', $response, $matches)) {
        echo "   ❌ Fatal Error: " . $matches[0] . "\n";
    } elseif (preg_match('/Parse error:[^<\n]*/', $response, $matches)) {
        echo "   ❌ Parse Error: " . $matches[0] . "\n";
    } elseif (preg_match('/Exception:[^<\n]*/', $response, $matches)) {
        echo "   ❌ Exception: " . $matches[0] . "\n";
    } elseif (preg_match('/SQLSTATE\[[^\]]+\]:[^<\n]*/', $response, $matches)) {
        echo "   ❌ SQL Error: " . $matches[0] . "\n";
    } else {
        echo "   ⚠️ Erreur 500 sans message d'erreur détectable\n";
    }
    
    // Afficher le début de la réponse pour debug
    echo "   Début de la réponse:\n";
    $lines = explode("\n", $response);
    foreach (array_slice($lines, 0, 10) as $i => $line) {
        if (trim($line)) {
            echo "   " . ($i+1) . ": " . trim($line) . "\n";
        }
    }
}

echo "\n🔍 4. Test avec session valide...\n";

// Créer une session valide d'abord
$loginData = ['username' => 'superadmin', 'password' => 'admin123'];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8000/login',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($loginData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => '/tmp/debug_cookies.txt',
    CURLOPT_COOKIEFILE => '/tmp/debug_cookies.txt'
]);

$loginResponse = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Login HTTP: $loginCode\n";

if ($loginCode === 302) {
    echo "   ✅ Login semble réussi\n";
    
    // Maintenant refaire le test edit avec la session
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost:8000/routes/1/edit',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => '/tmp/debug_cookies.txt',
        CURLOPT_COOKIEFILE => '/tmp/debug_cookies.txt',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $editResponse = curl_exec($ch);
    $editCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   Edit avec session HTTP: $editCode\n";
    
    if ($editCode === 500) {
        echo "   ❌ Erreur 500 persistante même avec session valide\n";
        if (preg_match('/(Fatal error|Parse error|Exception):[^<\n]*/', $editResponse, $matches)) {
            echo "   Erreur détectée: " . $matches[0] . "\n";
        }
    } elseif ($editCode === 302) {
        echo "   ✅ Redirection après edit (succès probable!)\n";
    } else {
        echo "   ⚠️ Code inattendu: $editCode\n";
    }
} else {
    echo "   ❌ Login échoué\n";
}

echo "\n📋 RÉSUMÉ DEBUG:\n";
echo "   - Route existe: " . ($route ? "✅" : "❌") . "\n";
echo "   - Update DB fonctionne: ✅\n";
echo "   - Erreur 500 sur POST: ❌\n";
echo "   - Cause probable: Erreur dans le code du contrôleur ou middleware\n";
?>