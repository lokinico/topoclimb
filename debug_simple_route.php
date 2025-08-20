<?php

/**
 * Script simple pour vérifier la configuration des routes
 */

echo "🔍 VÉRIFICATION SIMPLE CONFIGURATION ROUTES\n";
echo "=" . str_repeat("=", 50) . "\n";

// Charger les routes
$routes = require __DIR__ . '/config/routes.php';

echo "📋 Recherche des routes create:\n\n";

$createRoutes = [];

foreach ($routes as $index => $route) {
    if (strpos($route['path'], '/create') !== false) {
        $createRoutes[] = [
            'method' => $route['method'],
            'path' => $route['path'],
            'controller' => $route['controller'],
            'action' => $route['action'],
            'middlewares' => $route['middlewares'] ?? []
        ];
    }
}

echo "🎯 ROUTES CREATE TROUVÉES (" . count($createRoutes) . "):\n\n";

foreach ($createRoutes as $route) {
    echo "📍 {$route['method']} {$route['path']}\n";
    echo "   Controller: " . basename(str_replace('\\', '/', $route['controller'])) . "\n";
    echo "   Action: {$route['action']}()\n";
    echo "   Middlewares: " . (empty($route['middlewares']) ? 'Aucun' : implode(', ', array_map(function($m) { return basename(str_replace('\\', '/', $m)); }, $route['middlewares']))) . "\n";
    echo "\n";
}

echo "🔍 Vérification existence des méthodes contrôleurs:\n\n";

$controllersToCheck = [
    'RouteController' => '/routes/create',
    'SectorController' => '/sectors/create',
    'SiteController' => '/sites/create',
    'BookController' => '/books/create'
];

foreach ($controllersToCheck as $controller => $path) {
    echo "🧪 Vérification $controller:\n";
    
    $controllerFile = __DIR__ . "/src/Controllers/$controller.php";
    
    if (file_exists($controllerFile)) {
        echo "   ✅ Fichier existe: $controllerFile\n";
        
        $content = file_get_contents($controllerFile);
        
        // Chercher la méthode create
        if (strpos($content, 'public function create(') !== false) {
            echo "   ✅ Méthode create() trouvée\n";
            
            // Chercher si elle fait des redirections immédiates
            preg_match('/public function create\(.*?\{(.*?)\}/s', $content, $matches);
            if (isset($matches[1])) {
                $methodContent = $matches[1];
                
                if (strpos($methodContent, 'redirect(') !== false || strpos($methodContent, 'Response::redirect(') !== false) {
                    echo "   ⚠️  ATTENTION: La méthode create() contient des redirections\n";
                    
                    // Extraire les lignes de redirection
                    $lines = explode("\n", $methodContent);
                    foreach ($lines as $line) {
                        if (strpos($line, 'redirect(') !== false || strpos($line, 'Response::redirect(') !== false) {
                            echo "      📍 Redirection: " . trim($line) . "\n";
                        }
                    }
                } else {
                    echo "   ✅ Aucune redirection directe détectée\n";
                }
            }
            
        } else {
            echo "   ❌ Méthode create() MANQUANTE\n";
            
            // Lister les méthodes disponibles
            preg_match_all('/public function (\w+)\(/', $content, $matches);
            if (isset($matches[1])) {
                echo "      Méthodes disponibles: " . implode(', ', array_unique($matches[1])) . "\n";
            }
        }
        
    } else {
        echo "   ❌ Fichier contrôleur manquant: $controllerFile\n";
    }
    echo "\n";
}

echo "💡 HYPOTHÈSE PRINCIPALE:\n";
echo "Si les routes sont bien configurées et les méthodes existent,\n";
echo "alors la redirection vient probablement:\n";
echo "1. 🔧 D'un middleware qui redirige AVANT le contrôleur\n";
echo "2. 🔧 D'une exception dans le contrôleur qui déclenche un catch/redirect\n";
echo "3. 🔧 D'un système externe (serveur web) qui redirige\n\n";

echo "🎯 PROCHAINES ÉTAPES:\n";
echo "1. Vérifier les middlewares pour des redirections automatiques\n";
echo "2. Ajouter des logs détaillés dans les contrôleurs create()\n";
echo "3. Tester sans middleware pour isoler le problème\n";