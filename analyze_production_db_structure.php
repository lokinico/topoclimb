<?php
/**
 * ANALYSE STRUCTURE BASE PRODUCTION - IDENTIFICATION EXACTE
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🔍 ANALYSE STRUCTURE BASE PRODUCTION\n";
echo "=====================================\n\n";

require_once 'vendor/autoload.php';
require_once 'src/Core/Database.php';

try {
    // Connexion avec la vraie Database de production
    $db = new TopoclimbCH\Core\Database();
    $connection = $db->getConnection();
    
    echo "✅ Connexion établie\n\n";
    
    // 1. Analyser la structure de la table users
    echo "1️⃣ STRUCTURE TABLE USERS\n";
    echo str_repeat("-", 30) . "\n";
    
    // Pour MySQL
    $columns = $connection->query("DESCRIBE users")->fetchAll();
    
    echo "Colonnes trouvées:\n";
    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']}) " . 
             ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . 
             ($col['Key'] ? ' KEY' : '') . "\n";
    }
    
    // 2. Identifier les colonnes pour l'authentification
    echo "\n2️⃣ COLONNES POUR AUTHENTIFICATION\n";
    echo str_repeat("-", 40) . "\n";
    
    $authColumns = [];
    $allColumns = array_column($columns, 'Field');
    
    // Chercher colonne email/mail
    if (in_array('email', $allColumns)) {
        $authColumns['email'] = 'email';
        echo "✅ Colonne email: 'email'\n";
    } elseif (in_array('mail', $allColumns)) {
        $authColumns['email'] = 'mail';
        echo "✅ Colonne email: 'mail'\n";
    } else {
        echo "❌ Aucune colonne email/mail trouvée\n";
    }
    
    // Chercher colonne password
    if (in_array('password_hash', $allColumns)) {
        $authColumns['password'] = 'password_hash';
        echo "✅ Colonne password: 'password_hash'\n";
    } elseif (in_array('password', $allColumns)) {
        $authColumns['password'] = 'password';
        echo "✅ Colonne password: 'password'\n";
    } else {
        echo "❌ Aucune colonne password trouvée\n";
    }
    
    // Chercher colonne actif
    if (in_array('actif', $allColumns)) {
        $authColumns['active'] = 'actif';
        echo "✅ Colonne actif: 'actif'\n";
    } elseif (in_array('is_active', $allColumns)) {
        $authColumns['active'] = 'is_active';
        echo "✅ Colonne actif: 'is_active'\n";
    } elseif (in_array('active', $allColumns)) {
        $authColumns['active'] = 'active';
        echo "✅ Colonne actif: 'active'\n";
    } else {
        echo "⚠️ Aucune colonne actif trouvée (optionnel)\n";
        $authColumns['active'] = null;
    }
    
    // 3. Lister quelques utilisateurs
    echo "\n3️⃣ UTILISATEURS EXISTANTS\n";
    echo str_repeat("-", 30) . "\n";
    
    $users = $connection->query("SELECT * FROM users LIMIT 5")->fetchAll();
    echo "Nombre d'utilisateurs: " . count($users) . "\n";
    
    if (count($users) > 0) {
        $firstUser = $users[0];
        echo "\nPremier utilisateur (structure):\n";
        foreach ($firstUser as $key => $value) {
            if (!is_numeric($key)) { // Éviter doublons PDO
                $displayValue = (strlen($value) > 50) ? substr($value, 0, 50) . "..." : $value;
                echo "   - $key: $displayValue\n";
            }
        }
    }
    
    // 4. Générer le code AuthService exact
    echo "\n4️⃣ GÉNÉRATION CODE AUTHSERVICE\n";
    echo str_repeat("-", 40) . "\n";
    
    if (isset($authColumns['email']) && isset($authColumns['password'])) {
        $emailCol = $authColumns['email'];
        $passwordCol = $authColumns['password'];
        $activeCol = $authColumns['active'];
        
        echo "Configuration détectée:\n";
        echo "   - Email: $emailCol\n";
        echo "   - Password: $passwordCol\n";
        echo "   - Actif: " . ($activeCol ?: 'aucune') . "\n\n";
        
        // Générer le code exact
        $whereClause = $activeCol ? "$emailCol = ? AND $activeCol = 1" : "$emailCol = ?";
        
        $newCode = "            // CONFIGURATION EXACTE POUR VOTRE BASE DE PRODUCTION
            \$result = \$this->db->fetchOne(\"SELECT * FROM users WHERE $whereClause LIMIT 1\", [\$email]);";
        
        echo "Code à utiliser dans AuthService::attempt():\n";
        echo "```php\n";
        echo $newCode . "\n";
        echo "```\n\n";
        
        // 5. Créer le fichier AuthService corrigé
        echo "5️⃣ CRÉATION AUTHSERVICE CORRIGÉ\n";
        echo str_repeat("-", 40) . "\n";
        
        $authServiceFile = 'src/Services/AuthService.php';
        $content = file_get_contents($authServiceFile);
        
        // Sauvegarder l'original
        file_put_contents($authServiceFile . '.original', $content);
        
        // Remplacer la section problématique
        $pattern = '/\/\/ .*Auto-détection.*?(?=if \(\!\$result\))/s';
        $replacement = $newCode . "\n\n            ";
        
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            file_put_contents($authServiceFile, $newContent);
            echo "✅ AuthService.php corrigé automatiquement\n";
            echo "✅ Sauvegarde: AuthService.php.original\n";
        } else {
            echo "⚠️ Pattern non trouvé, correction manuelle nécessaire\n";
            echo "Remplacez la section auto-détection par:\n$newCode\n";
        }
        
        // 6. Test final
        echo "\n6️⃣ TEST FINAL\n";
        echo str_repeat("-", 20) . "\n";
        
        if (count($users) > 0) {
            $testUser = $users[0];
            $testEmail = $testUser[$emailCol];
            
            echo "Test avec utilisateur: $testEmail\n";
            
            // Essayer de récupérer avec la requête exacte
            $testQuery = "SELECT * FROM users WHERE $whereClause LIMIT 1";
            $stmt = $connection->prepare($testQuery);
            $stmt->execute([$testEmail]);
            $result = $stmt->fetch();
            
            if ($result) {
                echo "✅ Requête fonctionne parfaitement\n";
                echo "   - ID: {$result['id']}\n";
                echo "   - Email: {$result[$emailCol]}\n";
                echo "   - Autorisation: " . ($result['autorisation'] ?? 'N/A') . "\n";
            } else {
                echo "❌ Problème avec la requête\n";
            }
        }
        
    } else {
        echo "❌ Impossible de générer le code - colonnes manquantes\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "ANALYSE TERMINÉE - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 50) . "\n";