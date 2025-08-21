<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "🔍 DEBUG CONNEXION ADMIN\n\n";

$db = new Database();

try {
    // Vérifier l'utilisateur admin dans la base
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE username = ?",
        ['admin_test']
    );
    
    if ($user) {
        echo "✅ Utilisateur admin trouvé:\n";
        echo "   ID: {$user['id']}\n";
        echo "   Username: {$user['username']}\n";
        echo "   Mail: {$user['mail']}\n";
        echo "   Autorisation: {$user['autorisation']}\n";
        echo "   Password hash: " . substr($user['password'], 0, 20) . "...\n\n";
        
        // Tester le mot de passe
        $test_password = 'TestAdmin2025!';
        if (password_verify($test_password, $user['password'])) {
            echo "✅ Mot de passe correct\n";
        } else {
            echo "❌ Mot de passe incorrect\n";
            echo "   Test avec hash: " . password_hash($test_password, PASSWORD_DEFAULT) . "\n";
        }
        
    } else {
        echo "❌ Utilisateur admin_test non trouvé\n";
        
        // Lister tous les utilisateurs
        $all_users = $db->fetchAll("SELECT id, username, mail FROM users");
        echo "Utilisateurs existants:\n";
        foreach ($all_users as $u) {
            echo "   - {$u['username']} ({$u['mail']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}