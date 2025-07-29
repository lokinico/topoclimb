<?php
/**
 * Script pour créer un utilisateur de test et diagnostiquer l'authentification
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\AuthService;

echo "🔧 CRÉATION UTILISATEUR DE TEST\n";
echo "===============================\n\n";

try {
    // Connexion à la base de données
    $db = new Database();
    echo "✅ Connexion base de données réussie\n";
    
    // Vérifier la table users
    $userCount = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    echo "📊 Utilisateurs existants: {$userCount}\n";
    
    // Créer un utilisateur de test si il n'existe pas
    $testEmail = 'test@topoclimb.ch';
    $existingUser = $db->query("SELECT * FROM users WHERE email = ?", [$testEmail])->fetch();
    
    if (!$existingUser) {
        echo "👤 Création utilisateur de test...\n";
        
        $testUser = [
            'username' => 'testuser',
            'email' => $testEmail,
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User', 
            'role' => 1, // Admin
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $sql = "INSERT INTO users (username, email, password, first_name, last_name, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $testUser['username'],
            $testUser['email'], 
            $testUser['password'],
            $testUser['first_name'],
            $testUser['last_name'],
            $testUser['role'],
            $testUser['status'],
            $testUser['created_at'],
            $testUser['updated_at']
        ];
        
        $result = $db->execute($sql, $params);
        
        if ($result) {
            echo "✅ Utilisateur de test créé avec succès!\n";
            echo "   📧 Email: {$testEmail}\n";
            echo "   🔑 Mot de passe: test123\n";
            echo "   👑 Rôle: Admin (1)\n";
        } else {
            echo "❌ Erreur lors de la création de l'utilisateur\n";
        }
    } else {
        echo "👤 Utilisateur de test existe déjà:\n";
        echo "   📧 Email: {$existingUser['email']}\n";
        echo "   👑 Rôle: {$existingUser['role']}\n";
        echo "   ✅ Statut: {$existingUser['status']}\n";
    }
    
    // Test du service d'authentification
    echo "\n🔐 TEST SERVICE AUTHENTIFICATION\n";
    echo "================================\n";
    
    $authService = new AuthService($db);
    
    // Test de connexion
    echo "🔍 Test de connexion avec utilisateur test...\n";
    $loginResult = $authService->login($testEmail, 'test123');
    
    if ($loginResult) {
        echo "✅ Authentification réussie!\n";
        echo "👤 Utilisateur connecté: {$loginResult['username']}\n";
        echo "👑 Rôle: {$loginResult['role']}\n";
    } else {
        echo "❌ Échec de l'authentification\n";
    }
    
    // Lister tous les utilisateurs
    echo "\n👥 LISTE DE TOUS LES UTILISATEURS\n";
    echo "=================================\n";
    
    $allUsers = $db->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY id")->fetchAll();
    
    foreach ($allUsers as $user) {
        $roleText = match($user['role']) {
            1 => 'Admin',
            2 => 'Moderator', 
            3 => 'User',
            4 => 'Editor',
            5 => 'Contributor',
            default => 'Unknown'
        };
        
        echo "ID: {$user['id']} | {$user['username']} ({$user['email']}) | {$roleText} | {$user['status']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "🔍 Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n💡 INSTRUCTIONS POUR TESTER:\n";
echo "============================\n";
echo "1. Allez sur http://localhost:8000/login\n";
echo "2. Connectez-vous avec:\n";
echo "   📧 Email: test@topoclimb.ch\n";
echo "   🔑 Mot de passe: test123\n";
echo "3. Une fois connecté, testez les pages:\n";
echo "   - http://localhost:8000/routes\n";
echo "   - http://localhost:8000/sectors\n";
echo "   - http://localhost:8000/regions\n";
echo "4. Vérifiez que les boutons de vue fonctionnent!\n";