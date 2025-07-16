<?php
// Script de diagnostic accessible via le web
// Accessible via https://topoclimb.ch/debug_map.php

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNOSTIC WEB SERVEUR MAP ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n\n";

// Test 1: Vérifications environnement
echo "1. ENVIRONNEMENT SERVEUR:\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script: " . __FILE__ . "\n";
echo "Working Dir: " . getcwd() . "\n\n";

// Test 2: Cache opcode
echo "2. CACHE OPCODE:\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "OPcache actif: " . ($status ? 'OUI' : 'NON') . "\n";
    if ($status) {
        echo "Scripts en cache: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "Hits: " . $status['opcache_statistics']['hits'] . "\n";
        echo "Misses: " . $status['opcache_statistics']['misses'] . "\n";
    }
} else {
    echo "OPcache non disponible\n";
}
echo "\n";

// Test 3: Fichiers critiques
echo "3. FICHIERS CRITIQUES:\n";
$basePath = dirname(__DIR__);
$files = [
    'src/Controllers/MapController.php',
    'resources/views/map/index.twig'
];

foreach ($files as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
        echo "✅ $file ($size bytes, modifié: $modified)\n";
        
        // Vérifier les permissions
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        echo "   Permissions: $perms\n";
        
        // Vérifier signature MapController
        if ($file === 'src/Controllers/MapController.php') {
            $content = file_get_contents($fullPath);
            if (strpos($content, 'public function index(?Request $request = null)') !== false) {
                echo "   ✅ Signature index() correcte\n";
            } else {
                echo "   ❌ Signature index() incorrecte\n";
                // Chercher l'ancienne signature
                if (strpos($content, 'public function index(): Response') !== false) {
                    echo "   ⚠️  Ancienne signature détectée!\n";
                }
            }
        }
    } else {
        echo "❌ $file MANQUANT\n";
    }
}
echo "\n";

// Test 4: Test MapController simple
echo "4. TEST MAPCONTROLLER:\n";
try {
    chdir($basePath);
    require_once $basePath . '/bootstrap.php';
    echo "✅ Bootstrap chargé\n";
    
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container créé\n";
    
    $mapController = $container->get(\TopoclimbCH\Controllers\MapController::class);
    echo "✅ MapController instancié\n";
    
    // Test avec gestion d'erreur
    ob_start();
    try {
        $response = $mapController->index();
        echo "✅ index() appelé - Status: " . $response->getStatusCode() . "\n";
    } catch (\ArgumentCountError $e) {
        echo "❌ ERREUR ARGUMENTS: " . $e->getMessage() . "\n";
        echo "   La méthode n'accepte pas le bon nombre de paramètres!\n";
    } catch (\TypeError $e) {
        echo "❌ ERREUR TYPE: " . $e->getMessage() . "\n";
    } catch (\Throwable $e) {
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    if ($output) echo "Output: $output\n";
    
} catch (\Throwable $e) {
    echo "❌ ERREUR BOOTSTRAP: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Clear opcode cache si possible
echo "5. NETTOYAGE CACHE:\n";
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ Cache opcode vidé\n";
    } else {
        echo "❌ Échec vidage cache opcode\n";
    }
} else {
    echo "❌ opcache_reset non disponible\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";