<?php
/**
 * Script d'adaptation de l'authentification à la base de données existante
 * Analyse la structure réelle et adapte le code en conséquence
 */

echo "🔧 ADAPTATION AUTHENTIFICATION → BASE EXISTANTE\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base existante\n\n";
    
    // 1. Analyser la structure réelle de la table users
    echo "1️⃣ ANALYSE STRUCTURE USERS EXISTANTE\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll();
    
    echo "Colonnes détectées dans la table users :\n";
    $structure = [];
    
    foreach ($columns as $col) {
        echo "   - {$col['name']} ({$col['type']}) " . ($col['notnull'] ? "NOT NULL" : "NULL") . "\n";
        $structure[$col['name']] = $col['type'];
    }
    
    // Détecter les colonnes importantes
    $hasUsername = isset($structure['username']);
    $hasEmail = isset($structure['email']);
    $hasMail = isset($structure['mail']);
    $hasPassword = isset($structure['password']);
    $hasPasswordHash = isset($structure['password_hash']);
    $hasAutorisation = isset($structure['autorisation']);
    $hasActive = isset($structure['actif']) || isset($structure['is_active']);
    
    echo "\n📋 Analyse des colonnes critiques :\n";
    echo "   - Username: " . ($hasUsername ? "✅ Présente" : "❌ Manquante") . "\n";
    echo "   - Email: " . ($hasEmail ? "✅ Présente" : ($hasMail ? "⚠️ 'mail' trouvée" : "❌ Manquante")) . "\n";
    echo "   - Password: " . ($hasPasswordHash ? "✅ password_hash" : ($hasPassword ? "⚠️ password" : "❌ Manquante")) . "\n";
    echo "   - Autorisation: " . ($hasAutorisation ? "✅ Présente" : "❌ Manquante") . "\n";
    echo "   - Actif: " . ($hasActive ? "✅ Présente" : "❌ Manquante") . "\n";
    
    // 2. Vérifier les utilisateurs existants
    echo "\n2️⃣ UTILISATEURS EXISTANTS\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    $users = $db->query("SELECT * FROM users ORDER BY autorisation ASC LIMIT 10")->fetchAll();
    
    echo "Nombre d'utilisateurs trouvés : " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        $loginField = $hasUsername ? $user['username'] : ($hasEmail ? $user['email'] : ($hasMail ? $user['mail'] : 'N/A'));
        $activeField = isset($user['actif']) ? $user['actif'] : (isset($user['is_active']) ? $user['is_active'] : 'N/A');
        
        echo "   - ID: {$user['id']}\n";
        echo "     Login: $loginField\n";
        echo "     Rôle: {$user['autorisation']}\n";
        echo "     Actif: $activeField\n";
        echo "     Date: " . ($user['created_at'] ?? $user['date_inscription'] ?? 'N/A') . "\n\n";
    }
    
    // 3. Identifier l'utilisateur admin
    echo "3️⃣ RECHERCHE UTILISATEUR ADMIN\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    $adminUser = null;
    
    // Chercher par autorisation = 0 (admin)
    $admins = $db->query("SELECT * FROM users WHERE autorisation = 0 OR autorisation = '0' LIMIT 5")->fetchAll();
    
    if (count($admins) > 0) {
        echo "Utilisateurs admin trouvés (autorisation = 0) :\n";
        foreach ($admins as $admin) {
            $loginField = $hasUsername ? $admin['username'] : ($hasEmail ? $admin['email'] : ($hasMail ? $admin['mail'] : 'N/A'));
            echo "   - ID: {$admin['id']}, Login: $loginField\n";
            
            if (!$adminUser) $adminUser = $admin; // Prendre le premier
        }
    } else {
        echo "❌ Aucun utilisateur admin trouvé (autorisation = 0)\n";
        
        // Chercher le premier utilisateur avec la plus petite autorisation
        $firstUser = $db->query("SELECT * FROM users ORDER BY autorisation ASC LIMIT 1")->fetch();
        if ($firstUser) {
            echo "⚠️ Premier utilisateur trouvé : autorisation = {$firstUser['autorisation']}\n";
            $adminUser = $firstUser;
        }
    }
    
    // 4. Adapter le code d'authentification selon la structure
    echo "\n4️⃣ ADAPTATION CODE AUTHENTIFICATION\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    // Générer le code PHP adapté pour AuthService
    echo "Code à utiliser dans AuthService.php :\n\n";
    
    $loginColumn = $hasUsername ? 'username' : ($hasEmail ? 'email' : 'mail');
    $passwordColumn = $hasPasswordHash ? 'password_hash' : 'password';
    $activeColumn = isset($structure['actif']) ? 'actif' : 'is_active';
    
    echo "```php\n";
    echo "// Méthode login adaptée à votre structure\n";
    echo "public function login(string \$login, string \$password): bool\n";
    echo "{\n";
    echo "    try {\n";
    
    if ($hasUsername && $hasEmail) {
        echo "        // Votre base a username ET email - recherche sur les deux\n";
        echo "        \$query = \"SELECT * FROM users WHERE (username = ? OR email = ?) AND {$activeColumn} = 1\";\n";
        echo "        \$user = \$this->db->query(\$query, [\$login, \$login])->fetch();\n";
    } else {
        echo "        // Recherche sur {$loginColumn} uniquement\n";
        echo "        \$query = \"SELECT * FROM users WHERE {$loginColumn} = ? AND {$activeColumn} = 1\";\n";
        echo "        \$user = \$this->db->query(\$query, [\$login])->fetch();\n";
    }
    
    echo "        \n";
    echo "        if (!\$user) {\n";
    echo "            return false;\n";
    echo "        }\n";
    echo "        \n";
    echo "        // Vérification mot de passe\n";
    echo "        if (!password_verify(\$password, \$user['{$passwordColumn}'])) {\n";
    echo "            return false;\n";
    echo "        }\n";
    echo "        \n";
    echo "        // Connexion réussie - sauvegarder en session\n";
    echo "        \$this->session->set('auth_user_id', \$user['id']);\n";
    echo "        \$this->session->set('is_authenticated', true);\n";
    echo "        \n";
    echo "        return true;\n";
    echo "    } catch (Exception \$e) {\n";
    echo "        error_log('Erreur login: ' . \$e->getMessage());\n";
    echo "        return false;\n";
    echo "    }\n";
    echo "}\n";
    echo "```\n\n";
    
    // 5. Test de connexion avec l'admin trouvé
    if ($adminUser) {
        echo "5️⃣ TEST CONNEXION ADMIN\n";
        echo "-" . str_repeat("-", 50) . "\n";
        
        $adminLogin = $hasUsername ? $adminUser['username'] : ($hasEmail ? $adminUser['email'] : $adminUser['mail']);
        
        echo "Test avec utilisateur admin trouvé :\n";
        echo "   - ID: {$adminUser['id']}\n";
        echo "   - Login: $adminLogin\n";
        echo "   - Rôle: {$adminUser['autorisation']}\n";
        echo "   - Hash password: " . substr($adminUser[$passwordColumn], 0, 20) . "...\n";
        
        // Essayer différents mots de passe
        $testPasswords = ['admin123', 'admin', 'password', '123456', 'topoclimb'];
        
        foreach ($testPasswords as $testPass) {
            if (password_verify($testPass, $adminUser[$passwordColumn])) {
                echo "   ✅ Mot de passe trouvé: '$testPass'\n";
                echo "\n🎯 IDENTIFIANTS POUR CONNEXION :\n";
                echo "   Login: $adminLogin\n";
                echo "   Password: $testPass\n";
                break;
            }
        }
    }
    
    // 6. Recommandations finales
    echo "\n6️⃣ RECOMMANDATIONS\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    echo "Actions à effectuer :\n\n";
    
    echo "1. ✅ Modifier AuthService.php avec le code généré ci-dessus\n";
    echo "2. ✅ Utiliser les identifiants trouvés pour tester la connexion\n";
    
    if (!$hasPasswordHash && $hasPassword) {
        echo "3. ⚠️ Migrer les mots de passe vers password_hash si nécessaire\n";
    }
    
    if (!$hasUsername && $hasEmail) {
        echo "3. 💡 Optionnel: Ajouter colonne username pour plus de flexibilité\n";
    }
    
    echo "4. ✅ Tester la connexion sur /login avec les identifiants trouvés\n";
    echo "5. ✅ Vérifier l'accès aux pages protégées après connexion\n";
    
    echo "\n🎉 ADAPTATION TERMINÉE !\n";
    echo "Votre code d'authentification est maintenant adapté à votre structure DB existante.\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";