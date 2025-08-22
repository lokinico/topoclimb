<?php

// Script pour restaurer les tables essentielles manquantes
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Restauration des tables essentielles ===\n\n";

try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Liste des tables essentielles à créer
    $essentialTables = [
        // Table des utilisateurs
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            prenom VARCHAR(100),
            nom VARCHAR(100),
            ville VARCHAR(100),
            autorisation INTEGER DEFAULT 3,
            is_active INTEGER DEFAULT 1,
            is_banned INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table des pays
        "CREATE TABLE IF NOT EXISTS climbing_countries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(2) UNIQUE NOT NULL,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table des régions
        "CREATE TABLE IF NOT EXISTS climbing_regions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            country_id INTEGER,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            elevation INTEGER,
            difficulty_min VARCHAR(10),
            difficulty_max VARCHAR(10),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (country_id) REFERENCES climbing_countries(id)
        )",
        
        // Table des sites d'escalade
        "CREATE TABLE IF NOT EXISTS climbing_sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            region_id INTEGER,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            elevation INTEGER,
            access_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (region_id) REFERENCES climbing_regions(id)
        )",
        
        // Table des secteurs
        "CREATE TABLE IF NOT EXISTS climbing_sectors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            region_id INTEGER,
            site_id INTEGER,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            elevation INTEGER,
            orientation VARCHAR(50),
            rock_type VARCHAR(100),
            difficulty_min VARCHAR(10),
            difficulty_max VARCHAR(10),
            routes_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (region_id) REFERENCES climbing_regions(id),
            FOREIGN KEY (site_id) REFERENCES climbing_sites(id)
        )",
        
        // Table des voies
        "CREATE TABLE IF NOT EXISTS climbing_routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            sector_id INTEGER NOT NULL,
            difficulty VARCHAR(20),
            length INTEGER,
            bolts_count INTEGER,
            rating DECIMAL(3,2),
            first_ascent_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id)
        )",
        
        // Table des guides/livres
        "CREATE TABLE IF NOT EXISTS climbing_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255),
            description TEXT,
            publication_year INTEGER,
            isbn VARCHAR(20),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];
    
    // Créer chaque table
    foreach ($essentialTables as $index => $sql) {
        try {
            $db->exec($sql);
            $tableName = preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches) ? $matches[1] : "Table " . ($index + 1);
            echo "✓ $tableName créée\n";
        } catch (PDOException $e) {
            echo "✗ Erreur lors de la création d'une table: " . $e->getMessage() . "\n";
        }
    }
    
    // Insérer des données de base
    echo "\n=== Insertion des données de base ===\n";
    
    // Pays de base
    $db->exec("INSERT OR IGNORE INTO climbing_countries (id, name, code) VALUES (1, 'Suisse', 'CH')");
    echo "✓ Pays Suisse ajouté\n";
    
    // Utilisateur admin par défaut
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT OR IGNORE INTO users (id, username, email, password_hash, nom, prenom, autorisation) 
               VALUES (1, 'admin', 'admin@topoclimb.ch', '$passwordHash', 'Admin', 'TopoclimbCH', 1)");
    echo "✓ Utilisateur admin créé (login: admin, mot de passe: admin123)\n";
    
    // Régions de base
    $regions = [
        [2, 'Valais', 'Région alpine avec de nombreux sites d\'escalade', 46.2276, 7.3586],
        [3, 'Jura', 'Massif calcaire avec escalade en dalle et dévers', 47.0667, 6.7833],
        [4, 'Tessin', 'Région granitique au sud des Alpes', 46.1951, 8.8799],
        [5, 'Grisons', 'Canton montagneux avec escalade alpine', 46.6578, 9.5215]
    ];
    
    foreach ($regions as $region) {
        $db->exec("INSERT OR IGNORE INTO climbing_regions (id, name, description, country_id, latitude, longitude) 
                   VALUES ({$region[0]}, '{$region[1]}', '{$region[2]}', 1, {$region[3]}, {$region[4]})");
        echo "✓ Région {$region[1]} ajoutée\n";
    }
    
    // Site d'exemple
    $db->exec("INSERT OR IGNORE INTO climbing_sites (id, name, description, region_id, latitude, longitude) 
               VALUES (1, 'Secteur Test Valais', 'Site d\'exemple pour tester le système', 2, 46.2276, 7.3586)");
    echo "✓ Site d'exemple ajouté\n";
    
    // Secteur d'exemple
    $db->exec("INSERT OR IGNORE INTO climbing_sectors (id, name, description, region_id, site_id, latitude, longitude, orientation, rock_type) 
               VALUES (1, 'Mur d\'exemple', 'Secteur pour tester les fonctionnalités', 2, 1, 46.2276, 7.3586, 'Sud', 'Calcaire')");
    echo "✓ Secteur d'exemple ajouté\n";
    
    // Quelques voies d'exemple
    $routes = [
        [1, 'Voie facile', 'Voie d\'initiation', 1, '4a', 15, 8],
        [2, 'Voie intermédiaire', 'Bon niveau technique', 1, '6b', 25, 12],
        [3, 'Voie difficile', 'Pour grimpeurs expérimentés', 1, '7c', 30, 15]
    ];
    
    foreach ($routes as $route) {
        $db->exec("INSERT OR IGNORE INTO climbing_routes (id, name, description, sector_id, difficulty, length, bolts_count) 
                   VALUES ({$route[0]}, '{$route[1]}', '{$route[2]}', {$route[3]}, '{$route[4]}', {$route[5]}, {$route[6]})");
        echo "✓ Voie {$route[1]} ajoutée\n";
    }
    
    // Guide d'exemple
    $db->exec("INSERT OR IGNORE INTO climbing_books (id, title, author, description, publication_year) 
               VALUES (1, 'Guide TopoclimbCH', 'Équipe TopoclimbCH', 'Guide numérique des sites d\'escalade suisses', 2025)");
    echo "✓ Guide d'exemple ajouté\n";
    
    echo "\n=== Restauration terminée avec succès! ===\n";
    echo "Vous pouvez maintenant vous connecter avec:\n";
    echo "Login: admin\n";
    echo "Mot de passe: admin123\n";
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}