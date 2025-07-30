<?php
/**
 * ANALYSE STRUCTURE BASE PRODUCTION - Connexion directe
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🔍 ANALYSE STRUCTURE BASE PRODUCTION (DIRECT)\n";
echo "===============================================\n\n";

try {
    // 1. Chercher les variables de connexion
    echo "1️⃣ RECHERCHE CONFIGURATION DB\n";
    echo str_repeat("-", 35) . "\n";
    
    $dbConfig = [];
    
    // Méthode 1: Variables d'environnement système
    $envVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PORT'];
    foreach ($envVars as $var) {
        $value = getenv($var) ?: $_ENV[$var] ?? $_SERVER[$var] ?? null;
        if ($value !== null) {
            $dbConfig[$var] = $value;
        }
    }
    
    // Méthode 2: Chercher dans les fichiers de config
    $configFiles = [
        'config/database.php',
        'config/config.php',
        '.env.example',
        'bootstrap/app.php'
    ];
    
    foreach ($configFiles as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            echo "✅ Fichier trouvé: $file\n";
            
            // Extraire les variables DB
            if (preg_match('/DB_HOST[\'"]?\s*[=:]\s*[\'"]?([^\'";\s]+)/', $content, $matches)) {
                $dbConfig['DB_HOST'] = $matches[1];
            }
            if (preg_match('/DB_DATABASE[\'"]?\s*[=:]\s*[\'"]?([^\'";\s]+)/', $content, $matches)) {
                $dbConfig['DB_DATABASE'] = $matches[1];
            }
            if (preg_match('/DB_USERNAME[\'"]?\s*[=:]\s*[\'"]?([^\'";\s]+)/', $content, $matches)) {
                $dbConfig['DB_USERNAME'] = $matches[1];
            }
            if (preg_match('/DB_PASSWORD[\'"]?\s*[=:]\s*[\'"]?([^\'";\s]*)/', $content, $matches)) {
                $dbConfig['DB_PASSWORD'] = $matches[1];
            }
        }
    }
    
    // Méthode 3: Configuration par défaut communes
    if (empty($dbConfig)) {
        echo "⚠️ Aucune config trouvée, utilisation valeurs par défaut\n";
        $dbConfig = [
            'DB_HOST' => 'localhost',
            'DB_DATABASE' => 'topoclimb',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => ''
        ];
    }
    
    echo "\nConfiguration détectée:\n";
    foreach ($dbConfig as $key => $value) {
        $display = ($key === 'DB_PASSWORD') ? (strlen($value) > 0 ? str_repeat('*', strlen($value)) : 'VIDE') : $value;
        echo "   - $key: $display\n";
    }
    
    // 2. Demander les informations manquantes
    echo "\n2️⃣ SAISIE INFORMATIONS MANQUANTES\n";
    echo str_repeat("-", 40) . "\n";
    
    if (empty($dbConfig['DB_HOST']) || $dbConfig['DB_HOST'] === 'localhost') {
        echo "⚠️ Veuillez modifier ce script avec vos vraies informations DB:\n";
        echo "   - Host: (ex: mysql.server.com)\n";
        echo "   - Database: (ex: sh139940_topoclimb)\n";
        echo "   - Username: (ex: sh139940_user)\n";
        echo "   - Password: (votre mot de passe)\n\n";
        
        // Configuration manuelle à compléter
        $manualConfig = [
            'host' => 'VOTRE_HOST_MYSQL',
            'database' => 'VOTRE_NOM_BASE',
            'username' => 'VOTRE_USERNAME',
            'password' => 'VOTRE_PASSWORD'
        ];
        
        echo "Modifiez les lignes suivantes dans ce script:\n";
        foreach ($manualConfig as $key => $value) {
            echo "   \$manualConfig['$key'] = '$value';\n";
        }
        echo "\nPuis relancez: php analyze_production_direct.php\n";
        
        // Tentative avec config détectée quand même
        $host = $dbConfig['DB_HOST'];
        $database = $dbConfig['DB_DATABASE'];
        $username = $dbConfig['DB_USERNAME'];
        $password = $dbConfig['DB_PASSWORD'] ?? '';
        
    } else {
        $host = $dbConfig['DB_HOST'];
        $database = $dbConfig['DB_DATABASE'];
        $username = $dbConfig['DB_USERNAME'];
        $password = $dbConfig['DB_PASSWORD'] ?? '';
    }
    
    // 3. Tentative de connexion
    echo "3️⃣ TENTATIVE CONNEXION\n";
    echo str_repeat("-", 30) . "\n";
    
    if ($host === 'VOTRE_HOST_MYSQL') {
        echo "❌ Configuration non modifiée - Impossible de continuer\n";
        echo "Modifiez les informations de connexion dans le script\n";
        exit(1);
    }
    
    $port = $dbConfig['DB_PORT'] ?? '3306';
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    
    echo "Tentative connexion:\n";
    echo "   DSN: $dsn\n";
    echo "   User: $username\n";
    echo "   Password: " . (strlen($password) > 0 ? "***" : "VIDE") . "\n";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connexion MySQL réussie!\n\n";
    
    // 4. Analyse structure (même code que précédemment)
    echo "4️⃣ STRUCTURE TABLE USERS\n";
    echo str_repeat("-", 30) . "\n";
    
    $columns = $pdo->query("DESCRIBE users")->fetchAll();
    
    echo "Colonnes trouvées (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        $nullable = ($col['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
        $key = $col['Key'] ? " [{$col['Key']}]" : '';
        echo "   - {$col['Field']} ({$col['Type']}) $nullable$key\n";
    }
    
    // 5. Identification colonnes auth
    $allColumns = array_column($columns, 'Field');
    $authConfig = [];
    
    // Email/Mail
    if (in_array('email', $allColumns)) {
        $authConfig['email_column'] = 'email';
    } elseif (in_array('mail', $allColumns)) {
        $authConfig['email_column'] = 'mail';
    }
    
    // Password
    if (in_array('password_hash', $allColumns)) {
        $authConfig['password_column'] = 'password_hash';
    } elseif (in_array('password', $allColumns)) {
        $authConfig['password_column'] = 'password';
    }
    
    // Actif
    if (in_array('actif', $allColumns)) {
        $authConfig['active_column'] = 'actif';
    } elseif (in_array('is_active', $allColumns)) {
        $authConfig['active_column'] = 'is_active';
    } else {
        $authConfig['active_column'] = null;
    }
    
    echo "\n5️⃣ CONFIGURATION AUTH DÉTECTÉE\n";
    echo str_repeat("-", 40) . "\n";
    echo "   - Email: " . ($authConfig['email_column'] ?? '❌ NON TROUVÉE') . "\n";
    echo "   - Password: " . ($authConfig['password_column'] ?? '❌ NON TROUVÉE') . "\n";
    echo "   - Actif: " . ($authConfig['active_column'] ?? 'aucune') . "\n";
    
    // 6. Test utilisateurs
    echo "\n6️⃣ UTILISATEURS EXISTANTS\n";
    echo str_repeat("-", 30) . "\n";
    
    $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    echo "Total: $userCount utilisateurs\n";
    
    if ($userCount > 0) {
        $users = $pdo->query("SELECT * FROM users LIMIT 5")->fetchAll();
        foreach ($users as $user) {
            $email = $user[$authConfig['email_column']] ?? 'N/A';
            $role = $user['autorisation'] ?? $user['role'] ?? 'N/A';
            echo "   - ID: {$user['id']}, Email: $email, Rôle: $role\n";
        }
    }
    
    // 7. CODE FINAL
    echo "\n7️⃣ CODE AUTHSERVICE EXACT\n";
    echo str_repeat("-", 35) . "\n";
    
    if (isset($authConfig['email_column']) && isset($authConfig['password_column'])) {
        $emailCol = $authConfig['email_column'];
        $passwordCol = $authConfig['password_column'];
        $activeCol = $authConfig['active_column'];
        
        $whereClause = $activeCol ? "$emailCol = ? AND $activeCol = 1" : "$emailCol = ?";
        $query = "SELECT * FROM users WHERE $whereClause LIMIT 1";
        
        echo "REMPLACEZ dans src/Services/AuthService.php :\n\n";
        echo "```php\n";
        echo "// LIGNE EXACTE À UTILISER:\n";
        echo "\$result = \$this->db->fetchOne(\"$query\", [\$email]);\n\n";
        echo "// Vérification password:\n";
        echo "if (!password_verify(\$password, \$result['$passwordCol'])) {\n";
        echo "    return false;\n";
        echo "}\n";
        echo "```\n\n";
        
        // 8. Test final
        if ($userCount > 0) {
            echo "8️⃣ TEST REQUÊTE\n";
            echo str_repeat("-", 20) . "\n";
            
            $testUser = $users[0];
            $testEmail = $testUser[$emailCol];
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$testEmail]);
            $result = $stmt->fetch();
            
            if ($result) {
                echo "✅ Test réussi avec: $testEmail\n";
                echo "   Utilisateur trouvé: ID {$result['id']}\n";
            } else {
                echo "❌ Test échoué\n";
            }
        }
        
        // 9. Rapport final
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🎯 RÉSUMÉ FINAL\n";
        echo "===============\n";
        echo "Base: $database ($userCount utilisateurs)\n";
        echo "Email: {$authConfig['email_column']}\n";
        echo "Password: {$authConfig['password_column']}\n";
        echo "Requête: $query\n";
        echo "\n✅ UTILISEZ LE CODE CI-DESSUS DANS AUTHSERVICE.PHP\n";
        echo str_repeat("=", 60) . "\n";
        
    } else {
        echo "❌ Configuration incomplète - colonnes manquantes\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\n🔧 PROBLÈME DE CONNEXION:\n";
        echo "1. Vérifiez vos identifiants MySQL\n";
        echo "2. Modifiez les variables dans ce script:\n";
        echo "   \$host = 'votre_host';\n";
        echo "   \$database = 'votre_base';\n";
        echo "   \$username = 'votre_user';\n";  
        echo "   \$password = 'votre_password';\n";
    }
}

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";