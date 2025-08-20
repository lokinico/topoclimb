<?php
/**
 * Debug pour comprendre pourquoi les pages create redirigent
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== DEBUG REDIRECTION PAGES CREATE ===\n\n";

// Test des URLs problématiques avec curl détaillé
$urls = [
    '/routes/create',
    '/sectors/create', 
    '/sites/create',
    '/books/create'
];

foreach ($urls as $url) {
    echo "🔍 Test $url...\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:8000$url",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, // Important: ne pas suivre les redirections
        CURLOPT_VERBOSE => false,
        CURLOPT_USERAGENT => 'TopoclimbCH Debug Bot',
        CURLOPT_COOKIE => "PHPSESSID=debug_session_id",
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    
    echo "   Code HTTP: $httpCode\n";
    
    if ($httpCode === 302) {
        echo "   🔄 REDIRECTION détectée\n";
        
        // Extraire l'URL de redirection des headers
        if (preg_match('/Location:\s*([^\r\n]+)/i', $response, $matches)) {
            $location = trim($matches[1]);
            echo "   📍 Redirigé vers: $location\n";
        }
        
        // Chercher des indices dans les headers
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            if (stripos($line, 'location:') !== false) {
                echo "   Header: " . trim($line) . "\n";
            }
        }
    } elseif ($httpCode === 200) {
        echo "   ✅ Page accessible\n";
        
        // Vérifier s'il y a un formulaire
        if (strpos($response, '<form') !== false) {
            echo "   📝 Formulaire détecté\n";
        } else {
            echo "   ⚠️ Pas de formulaire trouvé\n";
        }
    } elseif ($httpCode === 403) {
        echo "   🚫 Accès interdit\n";
    } elseif ($httpCode === 404) {
        echo "   🔍 Page non trouvée\n";
    } else {
        echo "   ❓ Code inattendu\n";
    }
    
    echo "\n";
}

// Test avec session valide (simuler login admin)
echo "🔐 Test avec session d'admin...\n\n";

// Créer une session avec cookies persistants
$cookieJar = tempnam(sys_get_temp_dir(), 'curl_cookies_');

// 1. Login d'abord
echo "1. Tentative de login...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8000/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar
]);

$loginPage = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Page login: HTTP $loginCode\n";

// 2. Faire le POST de login (sans token CSRF pour le moment)
echo "2. POST login...\n";
$loginData = [
    'username' => 'superadmin', 
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8000/login',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($loginData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar
]);

$loginResponse = curl_exec($ch);
$loginPostCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   POST login: HTTP $loginPostCode\n";

// 3. Tester une page create avec la session
echo "3. Test /routes/create avec session...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8000/routes/create',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar
]);

$createResponse = curl_exec($ch);
$createCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   /routes/create avec session: HTTP $createCode\n";

if ($createCode === 302) {
    if (preg_match('/Location:\s*([^\r\n]+)/i', $createResponse, $matches)) {
        echo "   Redirigé vers: " . trim($matches[1]) . "\n";
    }
} elseif ($createCode === 200) {
    echo "   ✅ Formulaire de création accessible !\n";
    
    if (strpos($createResponse, '<form') !== false) {
        echo "   📝 Formulaire détecté dans la réponse\n";
    }
}

// Nettoyage
unlink($cookieJar);

echo "\n📋 CONCLUSION:\n";
echo "Si toutes les pages create redirigent même avec une session admin,\n";
echo "le problème vient soit:\n";
echo "1. Des middlewares qui refusent l'accès\n";
echo "2. D'une exception dans les contrôleurs\n"; 
echo "3. D'une redirection forcée dans le code\n";
?>