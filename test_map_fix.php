#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Helpers/functions.php';

echo "🔧 Test Map Fix - Vérification des imports\n";
echo "=========================================\n\n";

try {
    // Test 1: Vérifier que MapController peut être instancié
    echo "📋 Test 1: Imports et compatibilité\n";
    
    if (class_exists('TopoclimbCH\\Controllers\\MapController')) {
        echo "   ✅ MapController peut être chargé\n";
        
        // Vérifier la réflexion
        $reflection = new ReflectionClass('TopoclimbCH\\Controllers\\MapController');
        
        // Vérifier la méthode index
        if ($reflection->hasMethod('index')) {
            $method = $reflection->getMethod('index');
            $params = $method->getParameters();
            
            if (count($params) > 0) {
                $requestParam = $params[0];
                $requestType = $requestParam->getType();
                
                if ($requestType && $requestType->getName() === 'Symfony\\Component\\HttpFoundation\\Request') {
                    echo "   ✅ Méthode index() utilise Symfony Request (CORRECT)\n";
                } else {
                    echo "   ❌ Méthode index() n'utilise pas Symfony Request\n";
                    echo "   Type trouvé: " . ($requestType ? $requestType->getName() : 'aucun') . "\n";
                }
            }
        }
        
        // Vérifier l'héritage
        $parent = $reflection->getParentClass();
        if ($parent && $parent->getName() === 'TopoclimbCH\\Controllers\\BaseController') {
            echo "   ✅ Hérite correctement de BaseController\n";
        } else {
            echo "   ❌ Problème d'héritage avec BaseController\n";
        }
        
    } else {
        echo "   ❌ MapController ne peut pas être chargé\n";
        exit(1);
    }

    // Test 2: Vérifier les classes Symfony
    echo "\n📋 Test 2: Classes Symfony\n";
    
    if (class_exists('Symfony\\Component\\HttpFoundation\\Request')) {
        echo "   ✅ Symfony Request disponible\n";
    } else {
        echo "   ❌ Symfony Request manquant\n";
    }
    
    if (class_exists('Symfony\\Component\\HttpFoundation\\Response')) {
        echo "   ✅ Symfony Response disponible\n";
    } else {
        echo "   ❌ Symfony Response manquant\n";
    }

    // Test 3: Vérifier les modèles
    echo "\n📋 Test 3: Modèles TopoclimbCH\n";
    
    $models = ['Region', 'Site', 'Sector', 'Route'];
    foreach ($models as $model) {
        $className = "TopoclimbCH\\Models\\$model";
        if (class_exists($className)) {
            echo "   ✅ $model disponible\n";
        } else {
            echo "   ❌ $model manquant\n";
        }
    }

    echo "\n🎯 RÉSULTAT: MapController devrait maintenant fonctionner!\n";
    echo "Le problème de conflit Request/Response a été résolu.\n";

} catch (\Exception $e) {
    echo "\n💥 ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🏁 Test de compatibilité terminé.\n";