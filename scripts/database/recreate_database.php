<?php
/**
 * Script de recréation complète de la base de données TopoclimbCH
 * Résout le problème critique de tables manquantes
 */

echo "🔧 RÉCRÉATION COMPLÈTE BASE DE DONNÉES TopoclimbCH\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base de données\n\n";
    
    // 1. Créer la table users (CRITIQUE pour l'authentification)
    echo "1️⃣ Création table users...\n";
    $usersSql = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        nom VARCHAR(100),
        prenom VARCHAR(100),
        autorisation INTEGER DEFAULT 3,
        date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
        derniere_connexion DATETIME,
        actif INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($usersSql);
    echo "✅ Table users créée\n";
    
    // 2. Créer un utilisateur admin par défaut
    echo "\n2️⃣ Création utilisateur admin...\n";
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    $adminSql = "INSERT OR REPLACE INTO users (id, email, password_hash, nom, prenom, autorisation) 
                 VALUES (1, 'admin@topoclimb.ch', ?, 'Admin', 'TopoclimbCH', 0)";
    
    $stmt = $db->prepare($adminSql);
    $stmt->execute([$adminPassword]);
    echo "✅ Admin créé: admin@topoclimb.ch / admin123 (rôle 0)\n";
    
    // 3. Créer les tables principales
    echo "\n3️⃣ Création tables principales...\n";
    
    // Table regions
    $regionsSql = "
    CREATE TABLE IF NOT EXISTS climbing_regions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        coordinates_lat DECIMAL(10,8),
        coordinates_lng DECIMAL(11,8),
        altitude INTEGER,
        country_id INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($regionsSql);
    echo "✅ Table climbing_regions créée\n";
    
    // Table sites
    $sitesSql = "
    CREATE TABLE IF NOT EXISTS climbing_sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        region_id INTEGER,
        coordinates_lat DECIMAL(10,8),
        coordinates_lng DECIMAL(11,8),
        altitude INTEGER,
        access_time INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (region_id) REFERENCES climbing_regions(id)
    )";
    $db->exec($sitesSql);
    echo "✅ Table climbing_sites créée\n";
    
    // Table sectors
    $sectorsSql = "
    CREATE TABLE IF NOT EXISTS climbing_sectors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        region_id INTEGER,
        site_id INTEGER,
        coordinates_lat DECIMAL(10,8),
        coordinates_lng DECIMAL(11,8),
        altitude INTEGER,
        access_time INTEGER,
        orientation VARCHAR(10),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (region_id) REFERENCES climbing_regions(id),
        FOREIGN KEY (site_id) REFERENCES climbing_sites(id)
    )";
    $db->exec($sectorsSql);
    echo "✅ Table climbing_sectors créée\n";
    
    // Table routes
    $routesSql = "
    CREATE TABLE IF NOT EXISTS climbing_routes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        sector_id INTEGER NOT NULL,
        difficulty VARCHAR(10),
        length INTEGER,
        grade_value INTEGER,
        beauty_rating INTEGER,
        danger_rating INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sector_id) REFERENCES climbing_sectors(id)
    )";
    $db->exec($routesSql);
    echo "✅ Table climbing_routes créée\n";
    
    // Table books
    $booksSql = "
    CREATE TABLE IF NOT EXISTS climbing_books (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        author VARCHAR(255),
        publisher VARCHAR(255),
        publication_year INTEGER,
        isbn VARCHAR(20),
        price DECIMAL(10,2),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($booksSql);
    echo "✅ Table climbing_books créée\n";
    
    // 4. Insérer quelques données de test
    echo "\n4️⃣ Insertion données de test...\n";
    
    // Région de test
    $db->exec("INSERT OR IGNORE INTO climbing_regions (id, name, description) VALUES (1, 'Valais', 'Région alpine suisse')");
    
    // Site de test
    $db->exec("INSERT OR IGNORE INTO climbing_sites (id, name, description, region_id) VALUES (1, 'Saillon', 'Site escalade en Valais', 1)");
    
    // Secteur de test
    $db->exec("INSERT OR IGNORE INTO climbing_sectors (id, name, description, region_id, site_id) VALUES (1, 'Secteur Sud', 'Secteur expose sud', 1, 1)");
    
    // Routes de test
    for ($i = 1; $i <= 5; $i++) {
        $db->exec("INSERT OR IGNORE INTO climbing_routes (id, name, sector_id, difficulty) VALUES ($i, 'Voie Test $i', 1, '6a')");
    }
    
    echo "✅ Données de test insérées\n";
    
    // 5. Vérification finale
    echo "\n5️⃣ Vérification finale...\n";
    
    $tables = ['users', 'climbing_regions', 'climbing_sites', 'climbing_sectors', 'climbing_routes', 'climbing_books', 'view_analytics'];
    
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "✅ $table: $count enregistrements\n";
    }
    
    // Test de connexion admin
    echo "\n6️⃣ Test connexion admin...\n";
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@topoclimb.ch']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify('admin123', $admin['password_hash'])) {
        echo "✅ Test connexion admin: SUCCÈS\n";
        echo "   - Email: admin@topoclimb.ch\n";
        echo "   - Password: admin123\n";
        echo "   - Rôle: {$admin['autorisation']} (0 = admin)\n";
    } else {
        echo "❌ Test connexion admin: ÉCHEC\n";
    }
    
    echo "\n🎉 BASE DE DONNÉES COMPLÈTEMENT RECRÉÉE !\n";
    echo "\n🔑 IDENTIFIANTS ADMIN:\n";
    echo "   Email: admin@topoclimb.ch\n";
    echo "   Password: admin123\n";
    echo "   Rôle: 0 (administrateur)\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}