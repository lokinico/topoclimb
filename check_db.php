<?php
// Vérification rapide de la base de données
header('Content-Type: text/plain');

echo "=== VÉRIFICATION BASE DE DONNÉES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Chemins possibles pour la base de données
    $db_paths = [
        __DIR__ . '/topoclimb.db',
        dirname(__DIR__) . '/topoclimb.db',
        dirname(__DIR__) . '/database/topoclimb.db',
        dirname(__DIR__) . '/storage/topoclimb.db'
    ];
    
    $db_found = false;
    $db_path = null;
    
    foreach ($db_paths as $path) {
        if (file_exists($path)) {
            echo "✅ Base de données trouvée: $path\n";
            $db_found = true;
            $db_path = $path;
            break;
        }
    }
    
    if (!$db_found) {
        echo "❌ Base de données non trouvée. Chemins vérifiés:\n";
        foreach ($db_paths as $path) {
            echo "  - $path\n";
        }
        exit;
    }
    
    // Connexion à la base
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base réussie\n\n";
    
    // Vérifier les tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tables disponibles:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
    
    // Compter les données
    if (in_array('regions', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM regions");
        $count = $stmt->fetchColumn();
        echo "🗺️ Nombre de régions: $count\n";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT id, name, slug FROM regions LIMIT 5");
            $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "📝 Régions (5 premières):\n";
            foreach ($regions as $region) {
                echo "  - {$region['name']} (ID: {$region['id']}, slug: {$region['slug']})\n";
            }
        }
    }
    
    if (in_array('sectors', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sectors");
        $count = $stmt->fetchColumn();
        echo "🏔️ Nombre de secteurs: $count\n";
    }
    
    if (in_array('routes', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM routes");
        $count = $stmt->fetchColumn();
        echo "🧗 Nombre de voies: $count\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur PDO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA VÉRIFICATION ===\n";
?>