<?php
/**
 * Met à jour la base de données de test avec la structure exacte de production
 * Basé sur votre export réel de base de données
 */

echo "🔧 MISE À JOUR DB TEST AVEC STRUCTURE PRODUCTION\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Connexion à la base de test
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base de test\n\n";

    // 1. RECRÉER TABLE USERS AVEC STRUCTURE EXACTE DE PRODUCTION
    echo "1️⃣ Recréation table users (structure production exacte)...\n";
    
    // Supprimer l'ancienne table
    $db->exec("DROP TABLE IF EXISTS users");
    
    // Créer table avec structure EXACTE de production
    $usersSql = "
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom VARCHAR(255) NOT NULL,
        prenom VARCHAR(255) NOT NULL,
        ville VARCHAR(255) NOT NULL,
        mail VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        autorisation VARCHAR(255) NOT NULL DEFAULT '3',
        username VARCHAR(100) NOT NULL,
        reset_token VARCHAR(20) DEFAULT NULL,
        reset_token_expires_at DATETIME DEFAULT NULL,
        date_registered DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($usersSql);
    echo "✅ Table users recréée avec structure production\n";
    
    // 2. CRÉER UTILISATEURS DE TEST AVEC TOUS LES NIVEAUX D'ACCÈS
    echo "\n2️⃣ Création utilisateurs de test (tous niveaux)...\n";
    
    $testUsers = [
        // Niveau 0 - Super Admin
        [
            'nom' => 'SuperAdmin',
            'prenom' => 'Test',
            'ville' => 'Lausanne',
            'mail' => 'superadmin@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '0',
            'username' => 'superadmin'
        ],
        // Niveau 1 - Admin  
        [
            'nom' => 'Admin',
            'prenom' => 'Test',
            'ville' => 'Genève',
            'mail' => 'admin@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '1',
            'username' => 'admin'
        ],
        // Niveau 2 - Modérateur
        [
            'nom' => 'Moderator',
            'prenom' => 'Test',
            'ville' => 'Sion',
            'mail' => 'moderator@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '2',
            'username' => 'moderator'
        ],
        // Niveau 3 - Utilisateur standard
        [
            'nom' => 'User',
            'prenom' => 'Test',
            'ville' => 'Fribourg',
            'mail' => 'user@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '3',
            'username' => 'user'
        ],
        // Niveau 4 - Éditeur
        [
            'nom' => 'Editor',
            'prenom' => 'Test',
            'ville' => 'Neuchâtel',
            'mail' => 'editor@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '4',
            'username' => 'editor'
        ],
        // Niveau 5 - Contributeur
        [
            'nom' => 'Contributor',
            'prenom' => 'Test',
            'ville' => 'Berne',
            'mail' => 'contributor@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '5',
            'username' => 'contributor'
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO users (nom, prenom, ville, mail, password, autorisation, username) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($testUsers as $user) {
        $stmt->execute([
            $user['nom'],
            $user['prenom'], 
            $user['ville'],
            $user['mail'],
            $user['password'],
            $user['autorisation'],
            $user['username']
        ]);
        echo "✅ Utilisateur créé: {$user['mail']} (niveau {$user['autorisation']})\n";
    }
    
    // 3. VÉRIFIER LES AUTRES TABLES PRINCIPALES
    echo "\n3️⃣ Vérification tables principales...\n";
    
    $mainTables = [
        'climbing_regions',
        'climbing_sites', 
        'climbing_sectors',
        'climbing_routes',
        'climbing_books'
    ];
    
    foreach ($mainTables as $table) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "✅ $table: $count enregistrements\n";
        } catch (Exception $e) {
            echo "⚠️  $table: Table n'existe pas encore\n";
        }
    }
    
    // 4. RÉSUMÉ FINAL
    echo "\n4️⃣ Résumé des comptes de test créés...\n";
    echo "┌─────────────────────────────────────────────────────────┐\n";
    echo "│ COMPTES DE TEST - Tous avec mot de passe: test123      │\n";
    echo "├─────────────────────────────────────────────────────────┤\n";
    echo "│ superadmin@test.ch (niveau 0) - Super Administrateur   │\n";
    echo "│ admin@test.ch      (niveau 1) - Administrateur         │\n";
    echo "│ moderator@test.ch  (niveau 2) - Modérateur            │\n";
    echo "│ user@test.ch       (niveau 3) - Utilisateur standard   │\n";
    echo "│ editor@test.ch     (niveau 4) - Éditeur               │\n";
    echo "│ contributor@test.ch(niveau 5) - Contributeur          │\n";
    echo "└─────────────────────────────────────────────────────────┘\n";
    
    // 5. TEST RAPIDE DE CONNEXION
    echo "\n5️⃣ Test rapide de connexion...\n";
    $testUser = $db->query("SELECT * FROM users WHERE mail = 'admin@test.ch'")->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser && password_verify('test123', $testUser['password'])) {
        echo "✅ Test connexion admin: SUCCÈS\n";
        echo "   - Email: admin@test.ch\n";
        echo "   - Password: test123\n";
        echo "   - Niveau: {$testUser['autorisation']}\n";
    } else {
        echo "❌ Test connexion admin: ÉCHEC\n";
    }
    
    echo "\n🎉 BASE DE DONNÉES TEST MISE À JOUR AVEC STRUCTURE PRODUCTION !\n";
    echo "\n🔑 TOUS LES COMPTES DE TEST:\n";
    echo "   Password universel: test123\n";
    echo "   Utilisez ces comptes pour tester tous les niveaux d'accès\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}