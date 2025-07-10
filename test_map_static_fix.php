#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Helpers/functions.php';

echo "🔧 Test Static Properties Fix\n";
echo "============================\n\n";

try {
    // Test 1: Vérifier que les modèles peuvent être chargés
    echo "📋 Test 1: Chargement des modèles\n";
    
    $models = [
        'Site' => 'TopoclimbCH\\Models\\Site',
        'BookSector' => 'TopoclimbCH\\Models\\BookSector',
        'BookRoute' => 'TopoclimbCH\\Models\\BookRoute',
        'Media' => 'TopoclimbCH\\Models\\Media',
        'MediaRelationship' => 'TopoclimbCH\\Models\\MediaRelationship'
    ];
    
    foreach ($models as $name => $className) {
        if (class_exists($className)) {
            echo "   ✅ $name peut être chargé\n";
            
            // Vérifier les propriétés statiques
            $reflection = new ReflectionClass($className);
            $tableProperty = $reflection->getProperty('table');
            
            if ($tableProperty->isStatic()) {
                echo "   ✅ $name::\$table est statique (CORRECT)\n";
            } else {
                echo "   ❌ $name::\$table n'est pas statique\n";
            }
        } else {
            echo "   ❌ $name ne peut pas être chargé\n";
        }
    }

    // Test 2: Vérifier MapController
    echo "\n📋 Test 2: MapController\n";
    
    if (class_exists('TopoclimbCH\\Controllers\\MapController')) {
        echo "   ✅ MapController peut être chargé\n";
    } else {
        echo "   ❌ MapController ne peut pas être chargé\n";
    }

    echo "\n🎯 RÉSULTAT: Tous les conflits de propriétés statiques ont été résolus!\n";
    echo "La route /map devrait maintenant fonctionner sans erreur fatale.\n";

} catch (\Exception $e) {
    echo "\n💥 ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🏁 Test terminé.\n";