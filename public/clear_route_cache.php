<?php
// Outil pour nettoyer le cache des routes en production
header('Content-Type: text/plain');

echo "🧹 NETTOYAGE CACHE DES ROUTES - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

try {
    require_once dirname(__DIR__) . '/bootstrap.php';
    
    $cacheDir = BASE_PATH . '/cache/routes';
    $cacheFile = $cacheDir . '/routes.php';
    
    echo "📍 Environment: " . ($_ENV['APP_ENV'] ?? 'undefined') . "\n";
    echo "📍 Cache Directory: $cacheDir\n";
    echo "📍 Cache File: $cacheFile\n\n";
    
    // Vérifier l'état avant nettoyage
    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        $cacheSize = filesize($cacheFile);
        echo "🗂️ AVANT nettoyage:\n";
        echo "   Cache créé: " . date('Y-m-d H:i:s', $cacheTime) . "\n";
        echo "   Taille: " . number_format($cacheSize) . " bytes\n\n";
        
        // Sauvegarder le cache pour debug
        $backupFile = $cacheDir . '/routes_backup_' . date('Y-m-d_H-i-s') . '.php';
        if (copy($cacheFile, $backupFile)) {
            echo "💾 Sauvegarde créée: $backupFile\n";
        }
    } else {
        echo "ℹ️ Aucun cache existant\n\n";
    }
    
    // Nettoyer le cache
    $cleaned = 0;
    
    if (file_exists($cacheFile)) {
        if (unlink($cacheFile)) {
            echo "✅ Cache routes.php supprimé\n";
            $cleaned++;
        } else {
            echo "❌ Échec suppression routes.php\n";
        }
    }
    
    // Nettoyer tout le dossier cache/routes
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== basename($backupFile ?? '')) {
                if (unlink($file)) {
                    echo "✅ Supprimé: " . basename($file) . "\n";
                    $cleaned++;
                }
            }
        }
    }
    
    echo "\n🧹 NETTOYAGE TERMINÉ:\n";
    echo "   Fichiers supprimés: $cleaned\n";
    echo "   Status: " . ($cleaned > 0 ? "Cache nettoyé" : "Rien à nettoyer") . "\n";
    
    // Test de régénération
    echo "\n🔄 TEST DE RÉGÉNÉRATION:\n";
    
    // Forcer une requête pour régénérer le cache
    if (class_exists('TopoclimbCH\\Core\\ContainerBuilder')) {
        $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
        $container = $containerBuilder->build();
        $router = $container->get(\TopoclimbCH\Core\Router::class);
        
        // Ceci va régénérer le cache
        $router->loadRoutes(BASE_PATH . '/config/routes.php');
        
        if (file_exists($cacheFile)) {
            $newCacheTime = filemtime($cacheFile);
            echo "✅ Nouveau cache généré: " . date('Y-m-d H:i:s', $newCacheTime) . "\n";
            
            // Vérifier que /map-new est maintenant dans le cache
            $cachedRoutes = require $cacheFile;
            $mapNewFound = false;
            
            foreach ($cachedRoutes as $method => $routes) {
                foreach ($routes as $pattern => $route) {
                    if (isset($route['path']) && $route['path'] === '/map-new') {
                        echo "✅ /map-new maintenant présent dans le nouveau cache !\n";
                        $mapNewFound = true;
                        break 2;
                    }
                }
            }
            
            if (!$mapNewFound) {
                echo "⚠️ /map-new toujours absent du nouveau cache\n";
            }
            
            echo "📊 Total routes en cache: " . array_sum(array_map('count', $cachedRoutes)) . "\n";
        } else {
            echo "⚠️ Cache non régénéré automatiquement\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 TERMINÉ ! Testez maintenant https://topoclimb.ch/map-new\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}