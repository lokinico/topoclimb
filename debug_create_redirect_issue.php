<?php

// Script de diagnostic pour comprendre pourquoi les pages create redirigent
require_once __DIR__ . '/bootstrap.php';

echo "🔍 DIAGNOSTIC PROBLÈME PAGES CREATE\n";
echo "=" . str_repeat("=", 50) . "\n";

echo "📋 Test 1: Vérification des méthodes create dans les contrôleurs\n";

$controllers = [
    'RouteController' => '/home/nibaechl/topoclimb/src/Controllers/RouteController.php',
    'SectorController' => '/home/nibaechl/topoclimb/src/Controllers/SectorController.php', 
    'SiteController' => '/home/nibaechl/topoclimb/src/Controllers/SiteController.php',
    'BookController' => '/home/nibaechl/topoclimb/src/Controllers/BookController.php'
];

foreach ($controllers as $name => $path) {
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $hasCreate = strpos($content, 'public function create(') !== false;
        echo ($hasCreate ? "✅" : "❌") . " $name: " . ($hasCreate ? "méthode create() présente" : "méthode create() MANQUANTE") . "\n";
        
        if ($hasCreate) {
            // Chercher si la méthode redirige immédiatement
            $createStart = strpos($content, 'public function create(');
            $nextMethod = strpos($content, 'public function', $createStart + 1);
            $createMethod = substr($content, $createStart, $nextMethod - $createStart);
            
            $hasRedirectInCreate = strpos($createMethod, 'redirect(') !== false || strpos($createMethod, 'Response::redirect(') !== false;
            if ($hasRedirectInCreate) {
                echo "   ⚠️  ATTENTION: La méthode create() contient une redirection\n";
            }
            
            $hasRender = strpos($createMethod, 'render(') !== false;
            if ($hasRender) {
                echo "   ✅ La méthode create() contient un render()\n";
            }
        }
    } else {
        echo "❌ $name: Fichier non trouvé\n";
    }
}

echo "\n📋 Test 2: Vérification des routes create dans config/routes.php\n";

$routesFile = '/home/nibaechl/topoclimb/config/routes.php';
if (file_exists($routesFile)) {
    $routesContent = file_get_contents($routesFile);
    
    $createRoutes = [
        '/routes/create',
        '/sectors/create', 
        '/sites/create',
        '/books/create'
    ];
    
    foreach ($createRoutes as $route) {
        $hasRoute = strpos($routesContent, "'" . $route . "'") !== false;
        echo ($hasRoute ? "✅" : "❌") . " Route $route: " . ($hasRoute ? "présente" : "MANQUANTE") . "\n";
        
        if ($hasRoute) {
            // Vérifier l'action associée
            $routePattern = "/'path' => '" . preg_quote($route, '/') . "'/";
            if (preg_match($routePattern, $routesContent)) {
                echo "   ✅ Configuration trouvée\n";
            }
        }
    }
} else {
    echo "❌ Fichier config/routes.php non trouvé\n";
}

echo "\n📋 Test 3: Test HTTP direct des pages create\n";

function testHttpPage($path, $description) {
    $url = 'http://localhost:8000' . $path;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DebugScript 1.0');
    
    // Simuler une session admin
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: PHPSESSID=debug_session_' . uniqid() . '; auth_user_id=1; is_authenticated=1'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    $headers = substr($response, 0, $headerSize);
    $content = substr($response, $headerSize);
    
    echo "🔗 $description ($path)\n";
    echo "   📊 Status: $httpCode\n";
    
    if ($httpCode == 302) {
        preg_match('/Location: ([^\r\n]+)/', $headers, $matches);
        $location = isset($matches[1]) ? trim($matches[1]) : 'inconnue';
        echo "   ↗️  Redirection vers: $location\n";
        
        if (strpos($location, $path) !== false) {
            echo "   🔄 PROBLÈME: Redirection circulaire détectée!\n";
        } elseif (strpos($location, '/login') !== false) {
            echo "   🔒 Redirection vers login (problème auth)\n";
        } else {
            echo "   ❓ Redirection inattendue\n";
        }
    } elseif ($httpCode == 200) {
        $hasForm = strpos($content, '<form') !== false;
        echo "   📝 Formulaire présent: " . ($hasForm ? "✅" : "❌") . "\n";
    } else {
        echo "   ❌ Code d'erreur inattendu\n";
    }
    echo "\n";
}

// Tester les pages create principales
testHttpPage('/routes/create', 'Route Create');
testHttpPage('/sectors/create', 'Sector Create');
testHttpPage('/sites/create', 'Site Create'); 
testHttpPage('/books/create', 'Book Create');

echo "📋 Test 4: Test avec paramètre sector_id\n";
testHttpPage('/routes/create?sector_id=12', 'Route Create avec sector_id');

echo "🎯 DIAGNOSTIC TERMINÉ\n";
echo "Vérifiez les résultats ci-dessus pour identifier le problème.\n";