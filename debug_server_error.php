<?php
// Script de diagnostic pour identifier l'erreur 500 exacte
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNOSTIC ERREUR 500 MAP ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Vérifier les fichiers critiques
echo "1. VÉRIFICATION DES FICHIERS:\n";
$files = [
    'src/Controllers/MapController.php',
    'resources/views/map/index.twig',
    'resources/views/layouts/app.twig'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe (" . filesize($file) . " bytes)\n";
    } else {
        echo "❌ $file MANQUANT\n";
    }
}

// Test 2: Vérifier la syntaxe PHP
echo "\n2. VÉRIFICATION SYNTAXE PHP:\n";
$phpFiles = ['src/Controllers/MapController.php'];
foreach ($phpFiles as $file) {
    if (file_exists($file)) {
        $output = [];
        $return = 0;
        exec("php -l $file 2>&1", $output, $return);
        if ($return === 0) {
            echo "✅ $file: Syntaxe OK\n";
        } else {
            echo "❌ $file: ERREUR SYNTAXE\n";
            echo "   " . implode("\n   ", $output) . "\n";
        }
    }
}

// Test 3: Tester la classe MapController directement
echo "\n3. TEST CLASSE MAPCONTROLLER:\n";
try {
    require_once 'bootstrap.php';
    echo "✅ Bootstrap chargé\n";
    
    // Test instantiation simple
    $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
    $container = $containerBuilder->build();
    echo "✅ Container créé\n";
    
    $mapController = $container->get(\TopoclimbCH\Controllers\MapController::class);
    echo "✅ MapController instancié\n";
    
    // Test méthode index avec gestion d'erreur complète
    ob_start();
    error_reporting(E_ALL);
    
    try {
        $response = $mapController->index();
        $status = $response->getStatusCode();
        echo "✅ MapController::index() status: $status\n";
        
        if ($status === 500) {
            $content = $response->getContent();
            echo "❌ Contenu erreur 500:\n";
            echo substr($content, 0, 500) . "\n";
        }
        
    } catch (\ParseError $e) {
        echo "❌ ERREUR PARSE: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (\TypeError $e) {
        echo "❌ ERREUR TYPE: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (\Error $e) {
        echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (\Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    $output = ob_get_clean();
    if ($output) {
        echo "🔍 Output capturé:\n$output\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

// Test 4: Vérifier la signature des méthodes
echo "\n4. VÉRIFICATION SIGNATURES MÉTHODES:\n";
if (class_exists(\TopoclimbCH\Controllers\MapController::class)) {
    $reflection = new ReflectionClass(\TopoclimbCH\Controllers\MapController::class);
    
    $methods = ['index', 'apiSites', 'apiSiteDetails', 'apiGeoSearch'];
    foreach ($methods as $methodName) {
        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            $params = $method->getParameters();
            $paramInfo = [];
            foreach ($params as $param) {
                $type = $param->getType() ? $param->getType()->getName() : 'mixed';
                $nullable = $param->allowsNull() ? '?' : '';
                $default = $param->isDefaultValueAvailable() ? ' = ' . var_export($param->getDefaultValue(), true) : '';
                $paramInfo[] = $nullable . $type . ' $' . $param->getName() . $default;
            }
            echo "✅ $methodName(" . implode(', ', $paramInfo) . ")\n";
        } else {
            echo "❌ Méthode $methodName manquante\n";
        }
    }
}

echo "\n=== FIN DIAGNOSTIC ===\n";