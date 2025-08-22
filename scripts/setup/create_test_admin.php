<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "👤 CRÉATION UTILISATEUR ADMIN DE TEST\n\n";

$db = new Database();

try {
    // Données utilisateur admin test (adapter à la structure existante)
    $admin_data = [
        'username' => 'admin_test',
        'mail' => 'admin@topoclimb-test.local',
        'password' => password_hash('TestAdmin2025!', PASSWORD_DEFAULT),
        'autorisation' => '0', // 0 = admin
        'nom' => 'Admin',
        'prenom' => 'Test',
        'ville' => 'Test City',
        'date_registered' => date('Y-m-d H:i:s')
    ];
    
    // Vérifier si l'utilisateur existe déjà
    $existing = $db->fetchOne(
        "SELECT id FROM users WHERE username = ? OR mail = ?",
        [$admin_data['username'], $admin_data['mail']]
    );
    
    if ($existing) {
        echo "ℹ️ Utilisateur admin déjà existant - mise à jour du mot de passe\n";
        
        // Mettre à jour le mot de passe
        $db->update(
            'users',
            [
                'password' => $admin_data['password'],
                'autorisation' => '0'
            ],
            'id = ?',
            [$existing['id']]
        );
        
        echo "✅ Mot de passe admin mis à jour\n\n";
    } else {
        // Créer le nouvel utilisateur
        $userId = $db->insert('users', $admin_data);
        
        if ($userId) {
            echo "✅ Utilisateur admin créé avec ID: $userId\n\n";
        } else {
            throw new Exception("Échec création utilisateur admin");
        }
    }
    
    echo "🔐 IDENTIFIANTS ADMIN TEST:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "URL du serveur: http://localhost:8000\n";
    echo "Page de connexion: http://localhost:8000/login\n\n";
    echo "Nom d'utilisateur: admin_test\n";
    echo "Mot de passe: TestAdmin2025!\n";
    echo "Rôle: Administrateur (accès complet)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📋 PAGES À TESTER:\n";
    echo "• http://localhost:8000/regions/create - Créer région\n";
    echo "• http://localhost:8000/sites/create - Créer site\n"; 
    echo "• http://localhost:8000/sectors/create - Créer secteur\n";
    echo "• http://localhost:8000/routes/create - Créer voie\n";
    echo "• http://localhost:8000/books/create - Créer topo\n\n";
    
    echo "✅ POINTS À VÉRIFIER:\n";
    echo "1. Connexion admin réussie\n";
    echo "2. Formulaires s'affichent sans erreur\n";
    echo "3. Dropdowns région→site→secteur dynamiques\n";
    echo "4. Systèmes de cotation disponibles\n";
    echo "5. Boutons conversion GPS↔LV95 fonctionnels\n";
    echo "6. Plus de message 'formulaire non sécurisé'\n";
    echo "7. Upload d'images fonctionne\n";
    echo "8. Soumission formulaires sans erreur 404\n\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}