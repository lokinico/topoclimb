<?php
/**
 * TEST CHARGEMENT DE CLASSES
 */

echo "🔍 TEST CHARGEMENT CLASSES\n";
echo "==========================\n";

// 1. Charger l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

// 2. Tester les classes critiques
$classes = [
    'TopoclimbCH\Controllers\MapController',
    'TopoclimbCH\Controllers\BaseController',
    'TopoclimbCH\Core\View',
    'TopoclimbCH\Core\Session',
    'TopoclimbCH\Core\Database',
    'TopoclimbCH\Core\Auth',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class existe\n";
        
        // Pour MapController, vérifier les méthodes
        if ($class === 'TopoclimbCH\Controllers\MapController') {
            $methods = get_class_methods($class);
            if (in_array('index', $methods)) {
                echo "   ✅ Méthode index() présente\n";
            } else {
                echo "   ❌ Méthode index() manquante\n";
            }
        }
    } else {
        echo "❌ $class MANQUANTE\n";
    }
}

// 3. Test instantiation MapController si possible
echo "\n🧪 TEST INSTANTIATION:\n";
try {
    if (class_exists('TopoclimbCH\Controllers\MapController')) {
        echo "Tentative d'instantiation de MapController...\n";
        
        // On ne peut pas l'instancier complètement sans ses dépendances
        // mais on peut au moins vérifier qu'elle se charge
        $reflection = new ReflectionClass('TopoclimbCH\Controllers\MapController');
        echo "✅ MapController peut être réfléchie\n";
        echo "   Fichier: " . $reflection->getFileName() . "\n";
        echo "   Méthodes: " . count($reflection->getMethods()) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur instantiation: " . $e->getMessage() . "\n";
}

// 4. Vérifier les fichiers sur disque
echo "\n📁 VÉRIFICATION FICHIERS:\n";
$mapControllerFile = __DIR__ . '/src/Controllers/MapController.php';
if (file_exists($mapControllerFile)) {
    echo "✅ Fichier MapController.php existe\n";
    
    // Vérifier le contenu
    $content = file_get_contents($mapControllerFile);
    if (strpos($content, 'class MapController') !== false) {
        echo "   ✅ Contient 'class MapController'\n";
    }
    if (strpos($content, 'namespace TopoclimbCH\Controllers') !== false) {
        echo "   ✅ Namespace TopoclimbCH\Controllers correct\n";
    }
    if (strpos($content, 'public function index') !== false) {
        echo "   ✅ Méthode index() présente\n";
    }
} else {
    echo "❌ Fichier MapController.php manquant\n";
}

echo "\n🎯 CONCLUSION:\n";
echo "==============\n";
if (class_exists('TopoclimbCH\Controllers\MapController')) {
    echo "✅ MapController est correctement chargé\n";
    echo "Le problème est ailleurs (probablement routing ou instantiation)\n";
} else {
    echo "❌ MapController n'est PAS chargé\n";
    echo "Problème d'autoloading ou de fichier\n";
}
?>