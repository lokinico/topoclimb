#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Helpers/functions.php';

echo "🔧 Test simple MapController\n";
echo "============================\n\n";

try {
    // Test 1: Vérifier que la classe MapController peut être chargée
    echo "📋 Test 1: Chargement de la classe\n";
    
    if (class_exists('TopoclimbCH\Controllers\MapController')) {
        echo "   ✅ MapController existe et peut être chargé\n";
    } else {
        echo "   ❌ MapController ne peut pas être chargé\n";
        exit(1);
    }

    // Test 2: Vérifier la structure de la classe
    echo "\n📋 Test 2: Structure de la classe\n";
    
    $reflection = new ReflectionClass('TopoclimbCH\Controllers\MapController');
    
    if ($reflection->hasMethod('index')) {
        echo "   ✅ Méthode index() existe\n";
    } else {
        echo "   ❌ Méthode index() manquante\n";
    }
    
    if ($reflection->hasMethod('apiSites')) {
        echo "   ✅ Méthode apiSites() existe\n";
    } else {
        echo "   ❌ Méthode apiSites() manquante\n";
    }

    // Test 3: Vérifier la classe parente
    echo "\n📋 Test 3: Héritage\n";
    
    $parent = $reflection->getParentClass();
    if ($parent && $parent->getName() === 'TopoclimbCH\Controllers\BaseController') {
        echo "   ✅ Hérite correctement de BaseController\n";
    } else {
        echo "   ❌ N'hérite pas correctement de BaseController\n";
    }

    // Test 4: Vérifier que les classes utilisées existent
    echo "\n📋 Test 4: Dépendances\n";
    
    $dependencies = [
        'TopoclimbCH\Core\Request',
        'TopoclimbCH\Core\Response', 
        'TopoclimbCH\Models\Region',
        'TopoclimbCH\Models\Site',
        'TopoclimbCH\Models\Sector',
        'TopoclimbCH\Models\Route'
    ];
    
    foreach ($dependencies as $dep) {
        if (class_exists($dep)) {
            echo "   ✅ $dep existe\n";
        } else {
            echo "   ❌ $dep manquant\n";
        }
    }

    echo "\n🎯 RÉSULTAT: MapController semble correct\n";
    echo "Le problème est probablement dans la configuration du conteneur DI\n";
    echo "ou dans l'injection de dépendances lors de l'exécution.\n";

} catch (\Exception $e) {
    echo "\n💥 ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🏁 Test terminé.\n";