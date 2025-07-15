<?php
/**
 * Script d'initialisation de la base de données de test SQLite
 */

require_once __DIR__ . '/bootstrap.php';

echo "🗄️ Initialisation de la base de données de test SQLite...\n";

try {
    // Créer le fichier SQLite
    $dbPath = __DIR__ . '/storage/database/topoclimb_test.sqlite';
    
    // Supprimer l'ancien fichier s'il existe
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "   Ancien fichier supprimé\n";
    }
    
    // Créer la base de données SQLite
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "   Base de données créée: $dbPath\n";
    
    // Créer les tables essentielles
    $tables = [
        // Table des pays
        "CREATE TABLE climbing_countries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(2) UNIQUE NOT NULL,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table des utilisateurs
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            prenom VARCHAR(100),
            nom VARCHAR(100),
            ville VARCHAR(100),
            role_id INTEGER DEFAULT 3,
            is_active INTEGER DEFAULT 1,
            is_banned INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table des régions
        "CREATE TABLE climbing_regions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            canton VARCHAR(2),
            country_id INTEGER DEFAULT 1,
            coordinates_lat DECIMAL(10,8),
            coordinates_lng DECIMAL(11,8),
            altitude INTEGER,
            active INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (country_id) REFERENCES climbing_countries(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Table des sites
        "CREATE TABLE climbing_sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            region_id INTEGER,
            coordinates_lat DECIMAL(10,8),
            coordinates_lng DECIMAL(11,8),
            altitude INTEGER,
            active INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (region_id) REFERENCES climbing_regions(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Table des secteurs
        "CREATE TABLE climbing_sectors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            site_id INTEGER,
            region_id INTEGER,
            coordinates_lat DECIMAL(10,8),
            coordinates_lng DECIMAL(11,8),
            altitude INTEGER,
            access_time INTEGER,
            difficulty_min VARCHAR(10),
            difficulty_max VARCHAR(10),
            active INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES climbing_sites(id),
            FOREIGN KEY (region_id) REFERENCES climbing_regions(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Table des voies
        "CREATE TABLE climbing_routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            sector_id INTEGER,
            difficulty VARCHAR(10),
            length DECIMAL(5,1),
            beauty INTEGER,
            active INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Table des guides/books
        "CREATE TABLE climbing_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            author VARCHAR(255),
            year INTEGER,
            region_id INTEGER,
            active INTEGER DEFAULT 1,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (region_id) REFERENCES climbing_regions(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Table des ascensions
        "CREATE TABLE user_ascents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            route_id INTEGER NOT NULL,
            ascent_date DATE,
            ascent_type VARCHAR(20),
            quality_rating INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (route_id) REFERENCES climbing_routes(id)
        )",
        
        // Table des médias
        "CREATE TABLE climbing_media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255),
            media_type VARCHAR(20),
            is_public INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table cache météo
        "CREATE TABLE weather_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cache_key VARCHAR(255) UNIQUE NOT NULL,
            data TEXT,
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Table des secteurs de guides
        "CREATE TABLE climbing_book_sectors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            book_id INTEGER,
            sector_id INTEGER,
            FOREIGN KEY (book_id) REFERENCES climbing_books(id),
            FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id)
        )"
    ];
    
    foreach ($tables as $i => $sql) {
        $pdo->exec($sql);
        echo "   Table " . ($i + 1) . "/" . count($tables) . " créée ✅\n";
    }
    
    // Insérer des données de test
    echo "\n🌱 Insertion des données de test...\n";
    
    // Pays de test
    $countries = [
        ['Suisse', 'CH'],
        ['France', 'FR'],
        ['Italie', 'IT']
    ];
    
    $countryStmt = $pdo->prepare("INSERT INTO climbing_countries (name, code) VALUES (?, ?)");
    foreach ($countries as $country) {
        $countryStmt->execute($country);
    }
    echo "   Pays ajoutés ✅\n";
    
    // Régions de test
    $regions = [
        ['Valais', 'Région d\'escalade du Valais', 'VS', 1, 46.2, 7.3, 1500],
        ['Grisons', 'Région d\'escalade des Grisons', 'GR', 1, 46.6, 9.6, 1200],
        ['Jura', 'Région d\'escalade du Jura', 'JU', 1, 47.3, 7.0, 800]
    ];
    
    $regionStmt = $pdo->prepare("INSERT INTO climbing_regions (name, description, canton, country_id, coordinates_lat, coordinates_lng, altitude, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($regions as $region) {
        $regionStmt->execute($region);
    }
    echo "   Régions ajoutées ✅\n";
    
    // Sites de test
    $sites = [
        ['Saillon', 'Site d\'escalade de Saillon', 1, 46.1817, 7.1947, 1400],
        ['Sierre', 'Site d\'escalade de Sierre', 1, 46.2919, 7.5351, 1300],
        ['Chur', 'Site d\'escalade de Chur', 2, 46.8499, 9.5331, 1100]
    ];
    
    $siteStmt = $pdo->prepare("INSERT INTO climbing_sites (name, description, region_id, coordinates_lat, coordinates_lng, altitude, created_by) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($sites as $site) {
        $siteStmt->execute($site);
    }
    echo "   Sites ajoutés ✅\n";
    
    // Secteurs de test
    $sectors = [
        ['Secteur A', 'Premier secteur de test', 1, 1, 46.1817, 7.1947, 1400, 15, '4a', '7c'],
        ['Secteur B', 'Deuxième secteur de test', 2, 1, 46.2919, 7.5351, 1300, 20, '5a', '8a'],
        ['Secteur C', 'Troisième secteur de test', 3, 2, 46.8499, 9.5331, 1100, 10, '4b', '6c']
    ];
    
    $sectorStmt = $pdo->prepare("INSERT INTO climbing_sectors (name, description, site_id, region_id, coordinates_lat, coordinates_lng, altitude, access_time, difficulty_min, difficulty_max, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($sectors as $sector) {
        $sectorStmt->execute($sector);
    }
    echo "   Secteurs ajoutés ✅\n";
    
    // Voies de test
    $routes = [
        ['Voie du Débutant', 'Voie facile pour débuter', 1, '4a', 12.5, 3],
        ['Challenge', 'Voie technique et difficile', 1, '7a', 25.0, 4],
        ['La Belle', 'Voie esthétique', 2, '5c', 18.0, 5],
        ['L\'Imposante', 'Grande voie impressionnante', 3, '6b', 45.0, 4]
    ];
    
    $routeStmt = $pdo->prepare("INSERT INTO climbing_routes (name, description, sector_id, difficulty, length, beauty, created_by) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($routes as $route) {
        $routeStmt->execute($route);
    }
    echo "   Voies ajoutées ✅\n";
    
    // Utilisateur de test
    $testUser = [
        'testuser',
        'test@topoclimb.ch',
        password_hash('password123', PASSWORD_DEFAULT),
        'Test',
        'User',
        'Bern',
        3,  // role user normal
        1,  // active
        0   // not banned
    ];
    
    $userStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, prenom, nom, ville, role_id, is_active, is_banned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $userStmt->execute($testUser);
    echo "   Utilisateur de test ajouté (testuser / password123) ✅\n";
    
    // Vérifier que tout fonctionne
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $regionCount = $pdo->query("SELECT COUNT(*) FROM climbing_regions")->fetchColumn();
    $siteCount = $pdo->query("SELECT COUNT(*) FROM climbing_sites")->fetchColumn();
    $sectorCount = $pdo->query("SELECT COUNT(*) FROM climbing_sectors")->fetchColumn();
    $routeCount = $pdo->query("SELECT COUNT(*) FROM climbing_routes")->fetchColumn();
    
    echo "\n📊 Statistiques de la base de données:\n";
    echo "   Utilisateurs: $userCount\n";
    echo "   Régions: $regionCount\n";
    echo "   Sites: $siteCount\n";
    echo "   Secteurs: $sectorCount\n";
    echo "   Voies: $routeCount\n";
    
    echo "\n✅ Base de données de test initialisée avec succès!\n";
    echo "   Fichier: $dbPath\n";
    echo "   Utilisateur test: testuser / password123\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Erreur lors de l'initialisation: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}