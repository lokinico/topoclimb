<?php
// Script de test direct pour diagnostiquer l'erreur sur /map

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Charger la configuration
$config = require __DIR__ . '/config/config.php';

// Créer une instance du container
$container = new \TopoclimbCH\Core\Container();

// Configurer les services
$container->set(\TopoclimbCH\Core\Database::class, function() use ($config) {
    return new \TopoclimbCH\Core\Database($config['database']);
});

$container->set(\TopoclimbCH\Core\Session::class, function() {
    return new \TopoclimbCH\Core\Session();
});

$container->set(\TopoclimbCH\Core\View::class, function() {
    return new \TopoclimbCH\Core\View(__DIR__ . '/resources/views');
});

$container->set(\TopoclimbCH\Core\Auth::class, function() use ($container) {
    return new \TopoclimbCH\Core\Auth($container->get(\TopoclimbCH\Core\Database::class));
});

$container->set(\TopoclimbCH\Core\Security\CsrfManager::class, function() use ($container) {
    return new \TopoclimbCH\Core\Security\CsrfManager($container->get(\TopoclimbCH\Core\Session::class));
});

try {
    echo "=== Test direct MapController ===\n";
    
    // Créer une request simulée
    $request = \Symfony\Component\HttpFoundation\Request::create('/map');
    
    // Créer le contrôleur
    $controller = new \TopoclimbCH\Controllers\MapController(
        $container->get(\TopoclimbCH\Core\View::class),
        $container->get(\TopoclimbCH\Core\Session::class),
        $container->get(\TopoclimbCH\Core\Security\CsrfManager::class),
        $container->get(\TopoclimbCH\Core\Database::class),
        $container->get(\TopoclimbCH\Core\Auth::class)
    );
    
    echo "Contrôleur créé avec succès\n";
    
    // Tester la méthode index
    echo "Appel de la méthode index()...\n";
    $response = $controller->index($request);
    
    echo "Réponse obtenue: " . get_class($response) . "\n";
    echo "Status code: " . $response->getStatusCode() . "\n";
    echo "Content length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() !== 200) {
        echo "ERREUR: Status code non-200!\n";
        echo "Headers: " . print_r($response->headers->all(), true) . "\n";
        echo "Content preview: " . substr($response->getContent(), 0, 500) . "\n";
    } else {
        echo "SUCCESS: Page map fonctionne!\n";
    }
    
} catch (\Exception $e) {
    echo "ERREUR CAPTURE: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}