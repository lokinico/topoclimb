<?php
/**
 * Correction de la logique des niveaux d'accès
 * Niveau 4 = En attente, Niveau 5 = Banni
 */

echo "🔧 CORRECTION LOGIQUE NIVEAUX D'ACCÈS\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Connexion à la base de test
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base de test\n\n";

    // 1. SUPPRIMER LES ANCIENS UTILISATEURS DE TEST
    echo "1️⃣ Suppression des anciens utilisateurs de test...\n";
    $db->exec("DELETE FROM users WHERE mail LIKE '%@test.ch'");
    echo "✅ Anciens utilisateurs supprimés\n";
    
    // 2. CRÉER UTILISATEURS AVEC BONNE LOGIQUE
    echo "\n2️⃣ Création utilisateurs avec logique corrigée...\n";
    
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
        // Niveau 3 - Utilisateur validé
        [
            'nom' => 'User',
            'prenom' => 'Test',
            'ville' => 'Fribourg',
            'mail' => 'user@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '3',
            'username' => 'user'
        ],
        // Niveau 4 - En attente de validation
        [
            'nom' => 'Pending',
            'prenom' => 'Test',
            'ville' => 'Neuchâtel',
            'mail' => 'pending@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '4',
            'username' => 'pending'
        ],
        // Niveau 5 - Banni
        [
            'nom' => 'Banned',
            'prenom' => 'Test',
            'ville' => 'Berne',
            'mail' => 'banned@test.ch',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'autorisation' => '5',
            'username' => 'banned'
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
    
    // 3. AFFICHER LA NOUVELLE LOGIQUE
    echo "\n3️⃣ NOUVELLE LOGIQUE D'ACCÈS:\n";
    echo "┌─────────────────────────────────────────────────────────┐\n";
    echo "│ NIVEAUX D'ACCÈS CORRIGÉS - TopoclimbCH                 │\n";
    echo "├─────────────────────────────────────────────────────────┤\n";
    echo "│ Niveau 0: Super Admin - Accès total                    │\n";
    echo "│ Niveau 1: Admin - Panel admin + gestion                │\n";
    echo "│ Niveau 2: Modérateur - Modération + pages utilisateur  │\n";
    echo "│ Niveau 3: Utilisateur validé - Accès utilisateur       │\n";
    echo "│ Niveau 4: En attente - Connexion mais accès limité     │\n";
    echo "│ Niveau 5: Banni - Connexion refusée                    │\n";
    echo "└─────────────────────────────────────────────────────────┘\n";
    
    // 4. COMPTES DE TEST
    echo "\n4️⃣ COMPTES DE TEST CORRIGÉS:\n";
    echo "┌─────────────────────────────────────────────────────────┐\n";
    echo "│ COMPTES DE TEST - Mot de passe: test123                │\n";
    echo "├─────────────────────────────────────────────────────────┤\n";
    echo "│ superadmin@test.ch (0) - Super Administrateur          │\n";
    echo "│ admin@test.ch      (1) - Administrateur                │\n";
    echo "│ moderator@test.ch  (2) - Modérateur                    │\n";
    echo "│ user@test.ch       (3) - Utilisateur validé            │\n";
    echo "│ pending@test.ch    (4) - En attente validation         │\n";
    echo "│ banned@test.ch     (5) - Banni                         │\n";
    echo "└─────────────────────────────────────────────────────────┘\n";
    
    // 5. TEST RAPIDE
    echo "\n5️⃣ Test rapide de connexion...\n";
    $testUser = $db->query("SELECT * FROM users WHERE mail = 'user@test.ch'")->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser && password_verify('test123', $testUser['password'])) {
        echo "✅ Test connexion utilisateur validé: SUCCÈS\n";
        echo "   - Email: user@test.ch\n";
        echo "   - Password: test123\n";
        echo "   - Niveau: {$testUser['autorisation']} (Utilisateur validé)\n";
    } else {
        echo "❌ Test connexion utilisateur validé: ÉCHEC\n";
    }
    
    echo "\n🎉 LOGIQUE D'ACCÈS CORRIGÉE !\n";
    echo "\n⚠️  ATTENTION:\n";
    echo "   - Niveau 4 (En attente): Peut se connecter mais accès très limité\n";
    echo "   - Niveau 5 (Banni): Ne peut PAS se connecter\n";
    echo "   - AuthService doit être adapté pour refuser les bannis\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}