<?php
/**
 * Test d'accès avec utilisateur admin après corrections
 */

echo "🔐 TEST ACCÈS ADMIN APRÈS CORRECTIONS\n";
echo "====================================\n\n";

define('SERVER_URL', 'http://localhost:8000');
define('COOKIE_FILE', '/tmp/topoclimb_admin_cookies.txt');

// Nettoyer les cookies précédents
if (file_exists(COOKIE_FILE)) {
    unlink(COOKIE_FILE);
}

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
    
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
}

// 1. Connexion avec admin
echo "🔑 CONNEXION ADMIN\n";
echo "==================\n";

$loginPageResponse = httpRequest(SERVER_URL . '/login');
echo "Page login: Code {$loginPageResponse['code']}\n";

// Extraire token CSRF
preg_match('/name="_token".*?value="([^"]+)"/', $loginPageResponse['body'], $matches);
$csrfToken = $matches[1] ?? '';

if ($csrfToken) {
    echo "✅ Token CSRF trouvé: " . substr($csrfToken, 0, 10) . "...\n";
} else {
    echo "⚠️  Token CSRF non trouvé\n";
}

// Connexion avec admin
$loginData = [
    'email' => 'admin@topoclimb.ch',
    'password' => 'admin123'
];

if ($csrfToken) {
    $loginData['_token'] = $csrfToken;
}

$loginResponse = httpRequest(SERVER_URL . '/login', http_build_query($loginData));
echo "Connexion admin: Code {$loginResponse['code']}\n";

if (strpos($loginResponse['headers'], 'Location:') !== false) {
    echo "✅ Redirection détectée - connexion réussie\n";
} else {
    echo "❌ Pas de redirection - connexion échouée\n";
    echo "Réponse: " . substr($loginResponse['body'], 0, 200) . "...\n";
}

// 2. Test accès pages protégées
echo "\n📋 TEST PAGES AVEC ADMIN\n";
echo "========================\n";

$pages = [
    '/routes' => 'Routes',
    '/sectors' => 'Secteurs', 
    '/regions' => 'Régions',
    '/sites' => 'Sites',
    '/books' => 'Guides'
];

$viewSystemResults = [];

foreach ($pages as $path => $name) {
    echo "\n🔍 Test: {$name} ({$path})\n";
    
    $response = httpRequest(SERVER_URL . $path);
    $statusIcon = ($response['code'] === 200) ? '✅' : '❌';
    
    echo "   {$statusIcon} Code HTTP: {$response['code']}\n";
    
    // Vérifier si redirigé vers login
    if (strpos($response['body'], '<title>Connexion - TopoclimbCH</title>') !== false) {
        echo "   🚫 ENCORE REDIRIGÉ VERS LOGIN\n";
        $viewSystemResults[$path] = 'AUTH_FAILED';
        continue;
    }
    
    // Vérifier contenu de la page
    if (strpos($response['body'], '<title>') !== false) {
        preg_match('/<title>([^<]+)<\/title>/', $response['body'], $titleMatches);
        $title = $titleMatches[1] ?? 'Titre non trouvé';
        echo "   📄 Titre: {$title}\n";
    }
    
    // Vérifier éléments système de vues
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
    
    foreach ($viewElements as $element => $desc) {
        $found = strpos($response['body'], $element) !== false;
        $icon = $found ? '✅' : '❌';
        echo "   {$icon} {$desc}\n";
        if ($found) $foundElements++;
    }
    
    $percentage = round(($foundElements / $totalElements) * 100);
    echo "   📊 Système de vues: {$foundElements}/{$totalElements} ({$percentage}%)\n";
    
    $viewSystemResults[$path] = $percentage;
}

// 3. Résumé
echo "\n📊 RÉSUMÉ FINAL\n";
echo "===============\n";

$totalSuccess = 0;
$totalPages = count($viewSystemResults);

foreach ($viewSystemResults as $path => $result) {
    if ($result === 'AUTH_FAILED') {
        echo "❌ {$path}: ÉCHEC AUTHENTIFICATION\n";
    } else {
        $icon = ($result >= 80) ? '✅' : (($result >= 50) ? '⚠️' : '❌');
        echo "{$icon} {$path}: {$result}% système de vues\n";
        if ($result >= 50) $totalSuccess++;
    }
}

$globalScore = round(($totalSuccess / $totalPages) * 100);
echo "\n🎯 SCORE GLOBAL: {$globalScore}%\n";

if ($globalScore >= 80) {
    echo "🎉 EXCELLENT - Système fonctionnel!\n";
} elseif ($globalScore >= 50) {
    echo "⚠️  BON - Quelques améliorations nécessaires\n";
} else {
    echo "❌ PROBLÉMATIQUE - Corrections majeures requises\n";
}

// Nettoyer
if (file_exists(COOKIE_FILE)) {
    unlink(COOKIE_FILE);
}

echo "\n💡 INSTRUCTIONS MANUELLES:\n";
echo "==========================\n";
echo "1. Allez sur: http://localhost:8000/login\n";
echo "2. Connectez-vous avec:\n";
echo "   📧 Email: admin@topoclimb.ch\n";
echo "   🔑 Mot de passe: admin123\n";
echo "3. Testez les boutons de vue sur: /routes, /sectors, /regions\n";