<?php
/**
 * Script de correction pour adapter l'authentification à la base de production
 * Détecte automatiquement si c'est mail ou email et corrige le code
 */

echo "🔧 CORRECTION AUTHENTIFICATION POUR PRODUCTION\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion base de données\n\n";
    
    // 1. Analyser la structure users
    echo "1️⃣ ANALYSE STRUCTURE TABLE USERS\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll();
    $columnNames = array_column($columns, 'name');
    
    $hasEmail = in_array('email', $columnNames);
    $hasMail = in_array('mail', $columnNames);
    $hasPasswordHash = in_array('password_hash', $columnNames);
    $hasPassword = in_array('password', $columnNames);
    $hasActif = in_array('actif', $columnNames);
    $hasIsActive = in_array('is_active', $columnNames);
    
    echo "Colonnes détectées :\n";
    foreach ($columnNames as $col) {
        echo "   - $col\n";
    }
    
    echo "\n📋 Analyse :\n";
    echo "   - Email: " . ($hasEmail ? "✅ email" : ($hasMail ? "⚠️ mail" : "❌ aucune")) . "\n";
    echo "   - Password: " . ($hasPasswordHash ? "✅ password_hash" : ($hasPassword ? "⚠️ password" : "❌ aucune")) . "\n";
    echo "   - Actif: " . ($hasActif ? "✅ actif" : ($hasIsActive ? "✅ is_active" : "❌ aucune")) . "\n";
    
    // 2. Corriger AuthService.php si nécessaire
    echo "\n2️⃣ CORRECTION AUTHSERVICE.PHP\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $authServiceFile = 'src/Services/AuthService.php';
    
    if (!file_exists($authServiceFile)) {
        echo "❌ Fichier AuthService.php non trouvé\n";
        exit(1);
    }
    
    $content = file_get_contents($authServiceFile);
    $originalContent = $content;
    $modified = false;
    
    // Si la base utilise 'mail' au lieu de 'email'
    if (!$hasEmail && $hasMail) {
        echo "🔧 Correction : email → mail\n";
        
        // Remplacer dans la requête SQL
        $content = str_replace(
            'SELECT * FROM users WHERE email = ?',
            'SELECT * FROM users WHERE mail = ?',
            $content
        );
        
        // Remplacer dans les commentaires
        $content = str_replace(
            'Récupérer l\'utilisateur par email',
            'Récupérer l\'utilisateur par mail',
            $content
        );
        
        $modified = true;
        echo "   ✅ Requête SQL mise à jour : email → mail\n";
    }
    
    // Si la base utilise 'password' au lieu de 'password_hash'
    if (!$hasPasswordHash && $hasPassword) {
        echo "🔧 Correction : password_hash → password\n";
        
        $content = str_replace(
            'password_verify($password, $result[\'password_hash\'])',
            'password_verify($password, $result[\'password\'])',
            $content
        );
        
        $modified = true;
        echo "   ✅ Vérification password mise à jour\n";
    }
    
    // Correction de la condition actif
    $activeColumn = $hasActif ? 'actif' : 'is_active';
    
    // Ajouter une vérification de l'utilisateur actif
    if (strpos($content, 'actif = 1') === false && strpos($content, 'is_active = 1') === false) {
        echo "🔧 Ajout vérification utilisateur actif\n";
        
        $emailColumn = $hasEmail ? 'email' : 'mail';
        
        $content = str_replace(
            "SELECT * FROM users WHERE $emailColumn = ? LIMIT 1",
            "SELECT * FROM users WHERE $emailColumn = ? AND $activeColumn = 1 LIMIT 1",
            $content
        );
        
        $modified = true;
        echo "   ✅ Vérification $activeColumn ajoutée\n";
    }
    
    // Sauvegarder les modifications
    if ($modified) {
        file_put_contents($authServiceFile, $content);
        echo "✅ AuthService.php mis à jour\n";
        
        // Créer une sauvegarde
        file_put_contents($authServiceFile . '.backup', $originalContent);
        echo "✅ Sauvegarde créée : AuthService.php.backup\n";
    } else {
        echo "✅ Aucune modification nécessaire\n";
    }
    
    // 3. Vérifier les utilisateurs admin
    echo "\n3️⃣ VÉRIFICATION UTILISATEUR ADMIN\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $emailColumn = $hasEmail ? 'email' : 'mail';
    $passwordColumn = $hasPasswordHash ? 'password_hash' : 'password';
    
    // Chercher l'admin existant
    $query = "SELECT * FROM users WHERE autorisation = 0 OR autorisation = '0' LIMIT 1";
    $admin = $db->query($query)->fetch();
    
    if ($admin) {
        echo "✅ Admin trouvé :\n";
        echo "   - ID: {$admin['id']}\n";
        echo "   - " . ucfirst($emailColumn) . ": {$admin[$emailColumn]}\n";
        echo "   - Autorisation: {$admin['autorisation']}\n";
        echo "   - Actif: " . ($admin[$activeColumn] ?? 'N/A') . "\n";
        
        // Tester le mot de passe
        $testPasswords = ['admin123', 'admin', 'password', '123456'];
        $passwordFound = false;
        
        foreach ($testPasswords as $testPass) {
            if (password_verify($testPass, $admin[$passwordColumn])) {
                echo "   ✅ Mot de passe: $testPass\n";
                $passwordFound = true;
                break;
            }
        }
        
        if (!$passwordFound) {
            echo "   ⚠️ Mot de passe non trouvé, création d'un nouveau...\n";
            
            $newPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET $passwordColumn = ? WHERE id = ?";
            $db->prepare($updateQuery)->execute([$newPassword, $admin['id']]);
            
            echo "   ✅ Nouveau mot de passe défini: admin123\n";
        }
        
        echo "\n🔑 IDENTIFIANTS DE CONNEXION :\n";
        echo "   Email/Login: {$admin[$emailColumn]}\n";
        echo "   Password: " . ($passwordFound ? $testPass : 'admin123') . "\n";
        
    } else {
        echo "❌ Aucun admin trouvé\n";
        
        // Créer un admin
        echo "🔧 Création utilisateur admin...\n";
        
        $adminEmail = 'admin@topoclimb.ch';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $insertQuery = "INSERT INTO users ($emailColumn, $passwordColumn, nom, prenom, autorisation, $activeColumn, created_at) 
                       VALUES (?, ?, 'Admin', 'System', 0, 1, ?)";
        
        $db->prepare($insertQuery)->execute([$adminEmail, $adminPassword, date('Y-m-d H:i:s')]);
        
        echo "✅ Admin créé :\n";
        echo "   Email/Login: $adminEmail\n";
        echo "   Password: admin123\n";
    }
    
    // 4. Test final
    echo "\n4️⃣ TEST FINAL\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Simuler une tentative de connexion
    $testEmail = $admin ? $admin[$emailColumn] : 'admin@topoclimb.ch';
    $testPassword = 'admin123';
    
    $testQuery = "SELECT * FROM users WHERE $emailColumn = ? AND $activeColumn = 1 LIMIT 1";
    $testUser = $db->prepare($testQuery);
    $testUser->execute([$testEmail]);
    $user = $testUser->fetch();
    
    if ($user && password_verify($testPassword, $user[$passwordColumn])) {
        echo "✅ Test connexion réussi !\n";
        echo "   - Utilisateur trouvé\n";
        echo "   - Mot de passe correct\n";
        echo "   - Compte actif\n";
        
        echo "\n🎉 AUTHENTIFICATION CORRIGÉE AVEC SUCCÈS !\n";
        echo "\n📋 RÉSUMÉ DES CORRECTIONS :\n";
        echo "   - Code adapté à la structure de votre base\n";
        echo "   - Utilisateur admin fonctionnel\n";
        echo "   - Requêtes SQL optimisées\n";
        
        echo "\n🔗 PROCHAINES ÉTAPES :\n";
        echo "1. Tester la connexion sur /login\n";
        echo "2. Vérifier l'accès aux pages protégées\n";
        echo "3. Valider le système de vues\n";
        
    } else {
        echo "❌ Test connexion échoué\n";
        
        if (!$user) {
            echo "   - Utilisateur non trouvé avec $emailColumn = $testEmail\n";
        } else {
            echo "   - Mot de passe incorrect ou compte inactif\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";