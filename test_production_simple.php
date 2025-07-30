<?php
/**
 * Test simple pour le serveur de production
 * Diagnostic rapide du problème de connexion
 */

echo "🔍 TEST PRODUCTION SIMPLE\n";
echo "=" . str_repeat("=", 30) . "\n\n";

try {
    // Test 1: Base de données
    echo "1️⃣ Test base de données...\n";
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion DB: OK\n";
    
    // Test 2: Structure table users
    echo "\n2️⃣ Structure table users...\n";
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll();
    
    $hasEmail = false;
    $hasMail = false;
    $hasPasswordHash = false;
    
    echo "Colonnes trouvées:\n";
    foreach ($columns as $col) {
        echo "   - {$col['name']}\n";
        if ($col['name'] === 'email') $hasEmail = true;
        if ($col['name'] === 'mail') $hasMail = true;
        if ($col['name'] === 'password_hash') $hasPasswordHash = true;
    }
    
    // Test 3: Utilisateurs admin
    echo "\n3️⃣ Recherche utilisateur admin...\n";
    
    // Essayer avec email d'abord
    if ($hasEmail) {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND autorisation = 0");
        $stmt->execute(['admin@topoclimb.ch']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "✅ Admin trouvé avec colonne 'email'\n";
            echo "   - ID: {$admin['id']}\n"; 
            echo "   - Email: {$admin['email']}\n";
            echo "   - Rôle: {$admin['autorisation']}\n";
            
            // Test mot de passe
            $passwordField = $hasPasswordHash ? 'password_hash' : 'password';
            if (isset($admin[$passwordField])) {
                if (password_verify('admin123', $admin[$passwordField])) {
                    echo "✅ Mot de passe 'admin123': Correct\n";
                } else {
                    echo "❌ Mot de passe 'admin123': Incorrect\n";
                    
                    // Essayer d'autres mots de passe courants
                    $testPasswords = ['admin', 'password', '123456', 'topoclimb'];
                    foreach ($testPasswords as $testPass) {
                        if (password_verify($testPass, $admin[$passwordField])) {
                            echo "✅ Mot de passe trouvé: '$testPass'\n";
                            break;
                        }
                    }
                }
            } else {
                echo "❌ Pas de champ mot de passe trouvé\n";
            }
        }
    }
    
    // Essayer avec mail si pas trouvé avec email
    if (!isset($admin) && $hasMail) {
        $stmt = $db->prepare("SELECT * FROM users WHERE mail = ? AND autorisation = 0");
        $stmt->execute(['admin@topoclimb.ch']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "✅ Admin trouvé avec colonne 'mail'\n";
            echo "   - ID: {$admin['id']}\n";
            echo "   - Mail: {$admin['mail']}\n";
            echo "   - Rôle: {$admin['autorisation']}\n";
        }
    }
    
    if (!isset($admin)) {
        echo "❌ Aucun utilisateur admin trouvé\n";
        
        // Lister tous les utilisateurs
        echo "\nTous les utilisateurs:\n";
        $allUsers = $db->query("SELECT * FROM users LIMIT 5")->fetchAll();
        foreach ($allUsers as $user) {
            $emailField = $hasEmail ? ($user['email'] ?? 'N/A') : ($hasMail ? ($user['mail'] ?? 'N/A') : 'N/A');
            echo "   - ID: {$user['id']}, Email/Mail: $emailField, Rôle: {$user['autorisation']}\n";
        }
    }
    
    // Test 4: Recommandation
    echo "\n4️⃣ Recommandation...\n";
    
    if (!$hasEmail && $hasMail) {
        echo "🔧 SOLUTION: La base utilise 'mail' au lieu de 'email'\n";
        echo "   Exécuter: php fix_production_database.php\n";
        echo "   Ce script ajoutera la colonne 'email' automatiquement\n";
    } elseif (!isset($admin)) {
        echo "🔧 SOLUTION: Créer l'utilisateur admin\n";
        echo "   Exécuter: php fix_production_database.php\n";
        echo "   Ce script créera l'utilisateur admin automatiquement\n";
    } else {
        echo "✅ Structure correcte, admin existe\n";
        echo "   Tester la connexion web: admin@topoclimb.ch / admin123\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'no such table: users') !== false) {
        echo "\n🔧 SOLUTION: Table users manquante\n";
        echo "   1. Copier la nouvelle climbing_sqlite.db depuis votre local\n";
        echo "   2. OU exécuter: php recreate_database.php\n";
    }
}

echo "\n📋 RÉSUMÉ:\n";
echo "   Structure DB: " . ($hasEmail ? "✅ email" : "❌ pas email") . "\n";
echo "   Admin existant: " . (isset($admin) ? "✅ trouvé" : "❌ manquant") . "\n";
echo "   Prochaine étape: Exécuter fix_production_database.php\n";

echo "\nTest terminé à " . date('Y-m-d H:i:s') . "\n";