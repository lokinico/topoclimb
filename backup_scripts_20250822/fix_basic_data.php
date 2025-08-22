<?php

// Script pour ajouter les données de base avec échappement correct
try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Ajout des données de base ===\n";
    
    // Régions avec préparation des requêtes pour éviter les problèmes d'apostrophes
    $regions = [
        [2, 'Valais', 'Région alpine avec de nombreux sites d\'escalade', 46.2276, 7.3586],
        [3, 'Jura', 'Massif calcaire avec escalade en dalle et dévers', 47.0667, 6.7833],
        [4, 'Tessin', 'Région granitique au sud des Alpes', 46.1951, 8.8799],
        [5, 'Grisons', 'Canton montagneux avec escalade alpine', 46.6578, 9.5215]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO climbing_regions (id, name, description, country_id, latitude, longitude) 
                          VALUES (?, ?, ?, 1, ?, ?)");
    
    foreach ($regions as $region) {
        $stmt->execute([$region[0], $region[1], $region[2], $region[3], $region[4]]);
        echo "✓ Région {$region[1]} ajoutée\n";
    }
    
    // Site d'exemple
    $stmt = $db->prepare("INSERT OR IGNORE INTO climbing_sites (id, name, description, region_id, latitude, longitude) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Secteur Test Valais', 'Site d\'exemple pour tester le système', 2, 46.2276, 7.3586]);
    echo "✓ Site d'exemple ajouté\n";
    
    // Secteur d'exemple
    $stmt = $db->prepare("INSERT OR IGNORE INTO climbing_sectors (id, name, description, region_id, site_id, latitude, longitude, orientation, rock_type) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Mur d\'exemple', 'Secteur pour tester les fonctionnalités', 2, 1, 46.2276, 7.3586, 'Sud', 'Calcaire']);
    echo "✓ Secteur d'exemple ajouté\n";
    
    // Quelques voies d'exemple
    $routes = [
        [1, 'Voie facile', 'Voie d\'initiation', 1, '4a', 15, 8],
        [2, 'Voie intermédiaire', 'Bon niveau technique', 1, '6b', 25, 12],
        [3, 'Voie difficile', 'Pour grimpeurs expérimentés', 1, '7c', 30, 15]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO climbing_routes (id, name, description, sector_id, difficulty, length, bolts_count) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($routes as $route) {
        $stmt->execute($route);
        echo "✓ Voie {$route[1]} ajoutée\n";
    }
    
    // Guide d'exemple
    $stmt = $db->prepare("INSERT OR IGNORE INTO climbing_books (id, title, author, description, publication_year) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Guide TopoclimbCH', 'Équipe TopoclimbCH', 'Guide numérique des sites d\'escalade suisses', 2025]);
    echo "✓ Guide d'exemple ajouté\n";
    
    echo "\n✅ Données de base ajoutées avec succès!\n";
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}