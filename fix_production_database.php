<?php
/**
 * Script de réparation URGENTE pour base de données de production
 * Résout le problème "Unknown column 'email'" 
 */

echo "🔥 RÉPARATION URGENTE BASE DE DONNÉES PRODUCTION\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion base de données\n\n";
    
    // 1. Vérifier la structure actuelle de la table users
    echo "1️⃣ DIAGNOSTIC STRUCTURE USERS\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll();
    
    echo "Colonnes actuelles de la table users:\n";
    $hasEmail = false;
    $hasMail = false;
    $hasPassword = false;
    $hasPasswordHash = false;
    
    foreach ($columns as $col) {
        echo "   - {$col['name']} ({$col['type']})\n";
        if ($col['name'] === 'email') $hasEmail = true;
        if ($col['name'] === 'mail') $hasMail = true;
        if ($col['name'] === 'password') $hasPassword = true;
        if ($col['name'] === 'password_hash') $hasPasswordHash = true;
    }
    
    echo "\n📋 Analyse des colonnes:\n";
    echo "   - email: " . ($hasEmail ? "✅ Présente" : "❌ Manquante") . "\n";
    echo "   - mail: " . ($hasMail ? "✅ Présente" : "❌ Manquante") . "\n";
    echo "   - password_hash: " . ($hasPasswordHash ? "✅ Présente" : "❌ Manquante") . "\n";
    echo "   - password: " . ($hasPassword ? "✅ Présente" : "❌ Manquante") . "\n";
    
    // 2. Récupérer les utilisateurs existants
    echo "\n2️⃣ UTILISATEURS EXISTANTS\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $users = $db->query("SELECT * FROM users LIMIT 5")->fetchAll();
    echo "Nombre d'utilisateurs: " . count($users) . "\n";
    
    foreach ($users as $user) {
        $emailField = $hasEmail ? $user['email'] : ($hasMail ? $user['mail'] : 'N/A');
        echo "   - ID: {$user['id']}, Email/Mail: $emailField, Rôle: {$user['autorisation']}\n";
    }
    
    // 3. RÉPARATION AUTOMATIQUE
    echo "\n3️⃣ RÉPARATION AUTOMATIQUE\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $needsRepair = false;
    
    // Si pas de colonne email mais colonne mail existe
    if (!$hasEmail && $hasMail) {
        echo "🔧 Ajout colonne 'email' basée sur 'mail'...\n";
        
        // Ajouter colonne email
        $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255)");
        
        // Copier les données de mail vers email
        $db->exec("UPDATE users SET email = mail WHERE mail IS NOT NULL");
        
        // Créer un index unique sur email
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        
        echo "✅ Colonne 'email' ajoutée et données copiées\n";
        $needsRepair = true;
    }
    
    // Si pas de colonne password_hash mais password existe
    if (!$hasPasswordHash && $hasPassword) {
        echo "🔧 Ajout colonne 'password_hash'...\n";
        
        $db->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255)");
        
        // Hasher les mots de passe existants (si ils ne sont pas déjà hashés)
        $users = $db->query("SELECT id, password FROM users WHERE password IS NOT NULL")->fetchAll();
        
        foreach ($users as $user) {
            // Vérifier si le mot de passe est déjà hashé
            if (strlen($user['password']) < 60 || !str_starts_with($user['password'], '$')) {
                // Mot de passe en clair, le hasher
                $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                echo "   - Mot de passe hashé pour utilisateur ID {$user['id']}\n";
            } else {
                // Mot de passe déjà hashé, le copier
                $stmt = $db->prepare("UPDATE users SET password_hash = password WHERE id = ?");
                $stmt->execute([$user['id']]);
                echo "   - Mot de passe copié pour utilisateur ID {$user['id']}\n";
            }
        }
        
        echo "✅ Colonne 'password_hash' ajoutée et mots de passe traités\n";
        $needsRepair = true;
    }
    
    // 4. Créer l'utilisateur admin si nécessaire
    echo "\n4️⃣ VÉRIFICATION UTILISATEUR ADMIN\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $adminEmail = 'admin@topoclimb.ch';
    
    // Vérifier si l'admin existe (avec email ou mail)
    $emailColumn = $hasEmail ? 'email' : 'mail';
    $stmt = $db->prepare("SELECT * FROM users WHERE $emailColumn = ? AND autorisation = 0");
    $stmt->execute([$adminEmail]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "🔧 Création utilisateur admin...\n";
        
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $insertSql = "INSERT INTO users (";
        $insertSql .= $hasEmail ? "email" : "mail";
        $insertSql .= ", password_hash, nom, prenom, autorisation, actif, created_at) ";
        $insertSql .= "VALUES (?, ?, 'Admin', 'TopoclimbCH', 0, 1, ?)";
        
        $stmt = $db->prepare($insertSql);
        $stmt->execute([$adminEmail, $adminPassword, date('Y-m-d H:i:s')]);
        
        echo "✅ Utilisateur admin créé: $adminEmail / admin123\n";
        $needsRepair = true;
    } else {
        echo "✅ Utilisateur admin existe déjà\n";
        
        // Vérifier le mot de passe
        if (!password_verify('admin123', $admin['password_hash'] ?? $admin['password'] ?? '')) {
            echo "🔧 Mise à jour mot de passe admin...\n";
            
            $newPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newPassword, $admin['id']]);
            
            echo "✅ Mot de passe admin mis à jour\n";
            $needsRepair = true;
        }
    }
    
    // 5. Test final
    echo "\n5️⃣ TEST FINAL\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $emailColumn = $hasEmail ? 'email' : 'mail';
    $stmt = $db->prepare("SELECT * FROM users WHERE $emailColumn = ?");
    $stmt->execute([$adminEmail]);
    $finalAdmin = $stmt->fetch();
    
    if ($finalAdmin && password_verify('admin123', $finalAdmin['password_hash'])) {
        echo "✅ Test connexion admin: SUCCÈS\n";
        echo "   - Email: $adminEmail\n";
        echo "   - Password: admin123\n";
        echo "   - Rôle: {$finalAdmin['autorisation']}\n";
        echo "   - ID: {$finalAdmin['id']}\n";
    } else {
        echo "❌ Test connexion admin: ÉCHEC\n";
    }
    
    if ($needsRepair) {
        echo "\n🎉 RÉPARATION TERMINÉE AVEC SUCCÈS !\n";
    } else {
        echo "\n✅ AUCUNE RÉPARATION NÉCESSAIRE\n";
    }
    
    echo "\n📋 STRUCTURE FINALE:\n";
    $finalColumns = $db->query("PRAGMA table_info(users)")->fetchAll();
    foreach ($finalColumns as $col) {
        echo "   - {$col['name']} ({$col['type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n⚠️ INSTRUCTIONS DE DÉPLOIEMENT:\n";
echo "1. Copier ce fichier sur le serveur de production\n";
echo "2. Exécuter: php fix_production_database.php\n";
echo "3. Tester la connexion avec admin@topoclimb.ch / admin123\n";
echo "4. Si problème persiste, vérifier les logs détaillés\n";

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";