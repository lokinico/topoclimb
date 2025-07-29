<?php
/**
 * Test des pages avec authentification automatique
 */

echo "🔐 TEST PAGES AVEC AUTHENTIFICATION\n";
echo "===================================\n\n";

define('SERVER_URL', 'http://localhost:8000');
define('COOKIE_FILE', '/tmp/topoclimb_cookies.txt');

/**
 * Faire une requête HTTP avec cookies
 */
function httpRequest($url, $postData = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
    curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body
    ];
}

// 1. Se connecter d'abord
echo "🔑 CONNEXION AUTOMATIQUE\n";
echo "========================\n";

// Récupérer la page de login pour obtenir le token CSRF
$loginPageResponse = httpRequest(SERVER_URL . '/login');

if ($loginPageResponse['code'] !== 200) {
    echo "❌ Impossible d'accéder à la page de login\n";
    exit(1);
}

// Extraire le token CSRF depuis le HTML
preg_match('/name="_token".*?value="([^"]+)"/', $loginPageResponse['body'], $matches);
$csrfToken = $matches[1] ?? '';

if (!$csrfToken) {
    echo "⚠️  Token CSRF non trouvé, tentative sans token\n";
}

// Données de connexion
$loginData = [
    'email' => 'test@topoclimb.ch',
    'password' => 'test123'
];

if ($csrfToken) {
    $loginData['_token'] = $csrfToken;
}

// Tentative de connexion
$loginResponse = httpRequest(SERVER_URL . '/login', http_build_query($loginData));

echo "Tentative de connexion: Code {$loginResponse['code']}\n";

if (strpos($loginResponse['headers'], 'Location:') !== false) {
    echo "✅ Redirection détectée - probablement connecté\n";
} else {
    echo "⚠️  Pas de redirection détectée\n";
}

// 2. Tester l'accès aux pages protégées
echo "\n📋 TEST PAGES PROTÉGÉES\n";
echo "=======================\n";

$pages = [
    '/routes' => 'Page des routes',
    '/sectors' => 'Page des secteurs', 
    '/regions' => 'Page des régions',
    '/sites' => 'Page des sites',
    '/books' => 'Page des guides'
];

$viewSystemResults = [];

foreach ($pages as $path => $description) {
    echo "\n🔍 Test: {$description} ({$path})\n";
    
    $response = httpRequest(SERVER_URL . $path);
    $statusIcon = ($response['code'] === 200) ? '✅' : '❌';
    
    echo "   {$statusIcon} Code HTTP: {$response['code']}\n";
    
    // Vérifier si on est redirigé vers login
    if (strpos($response['body'], '<title>Connexion - TopoclimbCH</title>') !== false) {
        echo "   🚫 REDIRIGÉ VERS LOGIN - Non authentifié\n";
        $viewSystemResults[$path] = 'AUTH_FAILED';
        continue;
    }
    
    // Vérifier les éléments du système de vues
    $viewElements = [
        'entities-container' => 'Conteneur principal',
        'view-grid' => 'Vue grille',
        'view-list' => 'Vue liste',
        'view-compact' => 'Vue compacte',
        'data-view="grid"' => 'Bouton grille',
        'data-view="list"' => 'Bouton liste',
        'data-view="compact"' => 'Bouton compact',
        'view-modes.css' => 'CSS vues',
        'view-manager.js' => 'JS ViewManager'
    ];
    
    $foundElements = 0;
    $totalElements = count($viewElements);
    
    foreach ($viewElements as $element => $name) {
        $found = strpos($response['body'], $element) !== false;
        $icon = $found ? '✅' : '❌';
        echo "   {$icon} {$name}\n";
        if ($found) $foundElements++;
    }
    
    $percentage = round(($foundElements / $totalElements) * 100);
    echo "   📊 Système de vues: {$foundElements}/{$totalElements} ({$percentage}%)\n";
    
    $viewSystemResults[$path] = $percentage;
}

// 3. Résumé des résultats
echo "\n📊 RÉSUMÉ DES RÉSULTATS\n";
echo "=======================\n";

foreach ($viewSystemResults as $path => $result) {
    if ($result === 'AUTH_FAILED') {
        echo "❌ {$path}: ÉCHEC AUTHENTIFICATION\n";
    } else {
        $icon = ($result >= 80) ? '✅' : (($result >= 50) ? '⚠️' : '❌');
        echo "{$icon} {$path}: {$result}% du système de vues présent\n";
    }
}

// Nettoyer le fichier de cookies
if (file_exists(COOKIE_FILE)) {
    unlink(COOKIE_FILE);
}

echo "\n💡 PROCHAINES ÉTAPES:\n";
echo "====================\n";
echo "1. Si AUTH_FAILED: Vérifiez la configuration d'authentification\n";
echo "2. Si système de vues < 100%: Examinez les templates manquants\n";
echo "3. Testez manuellement avec: http://localhost:8000/login\n";