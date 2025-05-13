<?php
// Fichier de débogage pour le conteneur

define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Créer le conteneur
$containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
$container = $containerBuilder->build();

// Afficher les services enregistrés
echo "<h1>Services enregistrés dans le conteneur:</h1>";
echo "<pre>";
var_dump($container->getServiceIds());
echo "</pre>";

// Vérifier spécifiquement le HomeController
echo "<h2>Vérification spécifique:</h2>";
try {
    echo "Le conteneur a-t-il HomeController? " . 
        ($container->has('TopoclimbCH\\Controllers\\HomeController') ? "OUI" : "NON") . "<br>";
    
    if ($container->has('TopoclimbCH\\Controllers\\HomeController')) {
        $controller = $container->get('TopoclimbCH\\Controllers\\HomeController');
        echo "Type de HomeController: " . get_class($controller) . "<br>";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}