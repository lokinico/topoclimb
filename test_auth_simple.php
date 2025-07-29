<?php
/**
 * Test simple d'authentification avec les vraies colonnes
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    echo "🔐 TEST AUTHENTIFICATION SIMPLE\n";
    echo "===============================\n";
    
    // Vérifier/créer utilisateur de test
    $testEmail = 'test@topoclimb.ch';
    $testPassword = 'test123';
    
    $user = $db->query("SELECT * FROM users WHERE email = ?", [$testEmail])->fetch();
    
    if (!$user) {
        echo "👤 Création utilisateur de test...\n";
        
        $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, prenom, nom, role_id, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            'testuser',
            $testEmail,
            $hashedPassword,
            'Test',
            'User',
            1, // Admin
            1, // Active
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ];
        
        $result = $db->query($sql, $params);
        
        if ($result) {
            echo "✅ Utilisateur créé!\n";
            $user = $db->query("SELECT * FROM users WHERE email = ?", [$testEmail])->fetch();
        } else {
            echo "❌ Erreur création\n";
            exit(1);
        }
    }
    
    echo "👤 Utilisateur trouvé:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Username: {$user['username']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Role ID: {$user['role_id']}\n";
    echo "   Active: {$user['is_active']}\n";
    
    // Test du mot de passe
    echo "\n🔑 Test du mot de passe...\n";
    $passwordCheck = password_verify($testPassword, $user['password_hash']);
    
    if ($passwordCheck) {
        echo "✅ Mot de passe correct!\n";
    } else {
        echo "❌ Mot de passe incorrect\n";
        
        // Réinitialiser le mot de passe
        echo "🔧 Réinitialisation du mot de passe...\n";
        $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
        $updateResult = $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $user['id']]);
        
        if ($updateResult) {
            echo "✅ Mot de passe réinitialisé!\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n🎯 MAINTENANT TESTEZ:\n";
echo "====================\n";
echo "1. Allez sur: http://localhost:8000/login\n";
echo "2. Connectez-vous avec:\n";
echo "   📧 Email: test@topoclimb.ch\n";
echo "   🔑 Mot de passe: test123\n";
echo "3. Ensuite testez: http://localhost:8000/routes\n";