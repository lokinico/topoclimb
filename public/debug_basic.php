<?php
/**
 * Debug ultra basique
 */
header('Content-Type: text/plain');

echo "=== DEBUG BASIC ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Répertoire: " . __DIR__ . "\n\n";

// Vérifier les chemins d'autoloader
$autoloader_paths = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    '/tmp/vendor/autoload.php',
    '/home/httpd/vhosts/topoclimb.ch/topoclimb/vendor/autoload.php'
];

echo "=== VÉRIFICATION AUTOLOADERS ===\n";
foreach ($autoloader_paths as $path) {
    if (file_exists($path)) {
        echo "✅ Trouvé: $path\n";
        echo "   Taille: " . filesize($path) . " bytes\n";
        echo "   Lisible: " . (is_readable($path) ? 'OUI' : 'NON') . "\n";
    } else {
        echo "❌ Non trouvé: $path\n";
    }
}

// Tester le chargement de l'autoloader
echo "\n=== TEST CHARGEMENT ===\n";
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    echo "Tentative de chargement de: $autoloader\n";
    try {
        require_once $autoloader;
        echo "✅ Autoloader chargé avec succès\n";
    } catch (Error $e) {
        echo "❌ Erreur PHP: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
} else {
    echo "❌ Autoloader non trouvé\n";
}

// Vérifier les permissions du répertoire
echo "\n=== PERMISSIONS ===\n";
echo "Répertoire courant: " . getcwd() . "\n";
echo "Utilisateur PHP: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'inconnu') . "\n";
echo "Permissions vendor/: " . (is_dir(__DIR__ . '/vendor') ? decoct(fileperms(__DIR__ . '/vendor') & 0777) : 'N/A') . "\n";

echo "\n=== FIN DEBUG BASIC ===\n";
?>