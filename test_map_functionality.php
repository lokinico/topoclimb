#!/usr/bin/env php
<?php

/**
 * Script de test pour vérifier la fonctionnalité de carte
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Helpers/functions.php';

use TopoclimbCH\Controllers\MapController;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Database\Database;

echo "🗺️ Test de la fonctionnalité carte TopoclimbCH\n";
echo "================================================\n\n";

try {
    // Test 1: Vérifier que le contrôleur existe
    echo "📋 Test 1: Vérification du contrôleur MapController\n";
    
    if (class_exists('TopoclimbCH\Controllers\MapController')) {
        echo "   ✅ MapController existe\n";
        echo "   ✅ MapController peut être chargé\n";
        $controller = null; // On ne teste pas l'instanciation sans DI
    } else {
        echo "   ❌ MapController n'existe pas\n";
        exit(1);
    }

    // Test 2: Vérifier les méthodes du contrôleur
    echo "\n📋 Test 2: Vérification des méthodes du contrôleur\n";
    
    $requiredMethods = ['index', 'apiSites', 'apiSiteDetails', 'apiGeoSearch'];
    foreach ($requiredMethods as $method) {
        if (method_exists('TopoclimbCH\Controllers\MapController', $method)) {
            echo "   ✅ Méthode $method existe\n";
        } else {
            echo "   ❌ Méthode $method manquante\n";
        }
    }

    // Test 3: Vérifier les templates
    echo "\n📋 Test 3: Vérification des templates\n";
    
    $templatePath = __DIR__ . '/resources/views/map/index.twig';
    if (file_exists($templatePath)) {
        echo "   ✅ Template map/index.twig existe\n";
        
        $templateContent = file_get_contents($templatePath);
        if (strpos($templateContent, 'climbing-map') !== false) {
            echo "   ✅ Template contient l'élément carte\n";
        } else {
            echo "   ❌ Template ne contient pas l'élément carte\n";
        }
    } else {
        echo "   ❌ Template map/index.twig manquant\n";
    }

    // Test 4: Vérifier les assets CSS/JS
    echo "\n📋 Test 4: Vérification des assets\n";
    
    $cssPath = __DIR__ . '/public/css/pages/map.css';
    if (file_exists($cssPath)) {
        echo "   ✅ CSS map.css existe\n";
    } else {
        echo "   ❌ CSS map.css manquant\n";
    }
    
    $jsPath = __DIR__ . '/public/js/components/map-manager.js';
    if (file_exists($jsPath)) {
        echo "   ✅ JavaScript map-manager.js existe\n";
    } else {
        echo "   ❌ JavaScript map-manager.js manquant\n";
    }

    // Test 5: Vérifier les routes
    echo "\n📋 Test 5: Vérification des routes\n";
    
    $routesPath = __DIR__ . '/config/routes.php';
    if (file_exists($routesPath)) {
        $routes = require $routesPath;
        
        $mapRoutes = array_filter($routes, function($route) {
            return strpos($route['path'], '/map') === 0 || strpos($route['path'], '/api/map') === 0;
        });
        
        if (count($mapRoutes) >= 4) {
            echo "   ✅ Routes de carte configurées (" . count($mapRoutes) . " routes)\n";
            
            foreach ($mapRoutes as $route) {
                echo "      - {$route['method']} {$route['path']}\n";
            }
        } else {
            echo "   ❌ Routes de carte manquantes ou incomplètes\n";
        }
    } else {
        echo "   ❌ Fichier de routes manquant\n";
    }

    // Test 6: Vérifier la structure des méthodes
    echo "\n📋 Test 6: Vérification de la structure du contrôleur\n";
    
    try {
        $reflection = new ReflectionClass('TopoclimbCH\Controllers\MapController');
        
        echo "   ✅ MapController peut être analysé par réflexion\n";
        
        if ($reflection->getParentClass() && $reflection->getParentClass()->getName() === 'TopoclimbCH\Controllers\BaseController') {
            echo "   ✅ MapController étend BaseController\n";
        } else {
            echo "   ❌ MapController n'étend pas BaseController\n";
        }
        
        $constructor = $reflection->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() >= 3) {
            echo "   ✅ MapController a un constructeur avec injection de dépendances\n";
        } else {
            echo "   ❌ MapController n'a pas de constructeur approprié\n";
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Erreur lors de l'analyse de réflexion: " . $e->getMessage() . "\n";
    }

    // Test 7: Vérifier les icônes
    echo "\n📋 Test 7: Vérification des icônes\n";
    
    $iconPaths = [
        '/public/images/icons/climbing-marker.svg',
        '/public/images/icons/marker-shadow.svg'
    ];
    
    foreach ($iconPaths as $iconPath) {
        if (file_exists(__DIR__ . $iconPath)) {
            echo "   ✅ Icône $iconPath existe\n";
        } else {
            echo "   ❌ Icône $iconPath manquante\n";
        }
    }

    // Résumé final
    echo "\n🎯 RÉSUMÉ DES TESTS\n";
    echo "==================\n";
    
    $components = [
        'MapController' => class_exists('TopoclimbCH\Controllers\MapController'),
        'Template' => file_exists(__DIR__ . '/resources/views/map/index.twig'),
        'CSS' => file_exists(__DIR__ . '/public/css/pages/map.css'),
        'JavaScript' => file_exists(__DIR__ . '/public/js/components/map-manager.js'),
        'Routes' => file_exists(__DIR__ . '/config/routes.php'),
        'Icônes' => file_exists(__DIR__ . '/public/images/icons/climbing-marker.svg')
    ];
    
    $totalComponents = count($components);
    $workingComponents = array_sum($components);
    
    foreach ($components as $component => $status) {
        echo ($status ? "✅" : "❌") . " $component\n";
    }
    
    echo "\n📊 Score: $workingComponents/$totalComponents composants fonctionnels\n";
    
    if ($workingComponents === $totalComponents) {
        echo "\n🚀 SUCCÈS: Tous les composants de carte sont en place!\n";
        echo "La page https://topoclimb.ch/map devrait maintenant fonctionner.\n\n";
        
        echo "🔗 URLs disponibles:\n";
        echo "   - https://topoclimb.ch/map (page principale)\n";
        echo "   - https://topoclimb.ch/api/map/sites (API sites)\n";
        echo "   - https://topoclimb.ch/api/map/search (API recherche)\n";
        
    } else {
        echo "\n⚠️  ATTENTION: Certains composants manquent.\n";
        echo "Vérifiez les éléments marqués ❌ ci-dessus.\n";
    }

} catch (\Exception $e) {
    echo "\n💥 ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🏁 Test terminé.\n";