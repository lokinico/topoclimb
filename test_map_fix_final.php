<?php
// Test final pour vérifier si le fix du MapController fonctionne

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST FINAL MAP CONTROLLER FIX ===\n";

// Chargement du bootstrap
try {
    require_once __DIR__ . '/bootstrap.php';
    echo "✓ Bootstrap chargé\n";
} catch (Exception $e) {
    echo "✗ Erreur bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

// Charger l'autoloader de Composer
require BASE_PATH . '/vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

echo "✓ Dépendances chargées\n";

try {
    // Test de la création des services de base
    echo "=== Test des services de base ===\n";
    
    $database = new \TopoclimbCH\Core\Database();
    echo "✓ Database créée\n";
    
    $session = new \TopoclimbCH\Core\Session();
    echo "✓ Session créée\n";
    
    $view = new \TopoclimbCH\Core\View(BASE_PATH . '/resources/views');
    echo "✓ View créée\n";
    
    $csrfManager = new \TopoclimbCH\Core\Security\CsrfManager($session);
    echo "✓ CsrfManager créé\n";
    
    $auth = new \TopoclimbCH\Core\Auth($database);
    echo "✓ Auth créé\n";
    
    // Test du contrôleur Map
    echo "=== Test MapController ===\n";
    
    $mapController = new \TopoclimbCH\Controllers\MapController(
        $view,
        $session,
        $csrfManager,
        $database,
        $auth
    );
    echo "✓ MapController créé\n";
    
    // Test de la méthode index avec une fausse requête
    $request = \Symfony\Component\HttpFoundation\Request::create('/map');
    echo "✓ Request créée\n";
    
    echo "Appel de MapController::index()...\n";
    $response = $mapController->index($request);
    echo "✓ MapController::index() réussi\n";
    
    echo "Type de réponse: " . get_class($response) . "\n";
    echo "Code de statut: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        $content = $response->getContent();
        echo "Taille du contenu: " . strlen($content) . " bytes\n";
        
        // Vérifier quelques éléments clés
        if (strpos($content, 'Carte Interactive') !== false) {
            echo "✓ Titre trouvé dans le contenu\n";
        } else {
            echo "✗ Titre non trouvé dans le contenu\n";
        }
        
        if (strpos($content, 'climbing-map') !== false) {
            echo "✓ Element carte trouvé dans le contenu\n";
        } else {
            echo "✗ Element carte non trouvé dans le contenu\n";
        }
        
        echo "=== SUCCESS: MapController fonctionne correctement! ===\n";
    } else {
        echo "✗ Code de statut incorrect: " . $response->getStatusCode() . "\n";
        echo "Contenu de la réponse:\n";
        echo substr($response->getContent(), 0, 500) . "\n";
    }
    
} catch (\Exception $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " (ligne " . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}