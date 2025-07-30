<?php
/**
 * ANALYSE STRUCTURE BASE PRODUCTION avec .env
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🔍 ANALYSE STRUCTURE BASE PRODUCTION (.env)\n";
echo "=============================================\n\n";

try {
    // 1. Charger les variables d'environnement depuis .env
    echo "1️⃣ CHARGEMENT VARIABLES .env\n";
    echo str_repeat("-", 35) . "\n";
    
    $envFile = '.env';
    if (!file_exists($envFile)) {
        echo "❌ Fichier .env non trouvé\n";
        exit(1);
    }
    
    $envVars = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $envVars[$key] = $value;
            $_ENV[$key] = $value; // Définir dans $_ENV aussi
        }
    }
    
    echo "Variables .env chargées:\n";
    $dbVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PORT'];
    foreach ($dbVars as $var) {
        $value = $envVars[$var] ?? 'NON DÉFINIE';
        $display = ($var === 'DB_PASSWORD') ? (strlen($value) > 0 ? str_repeat('*', strlen($value)) : 'VIDE') : $value;
        echo "   - $var: $display\n";
    }
    
    // 2. Connexion manuelle avec les bonnes variables
    echo "\n2️⃣ CONNEXION BASE DE DONNÉES\n";
    echo str_repeat("-", 35) . "\n";
    
    $host = $envVars['DB_HOST'] ?? 'localhost';
    $database = $envVars['DB_DATABASE'] ?? '';
    $username = $envVars['DB_USERNAME'] ?? 'root';
    $password = $envVars['DB_PASSWORD'] ?? '';
    $port = $envVars['DB_PORT'] ?? '3306';
    
    if (empty($database)) {
        echo "❌ DB_DATABASE est vide dans .env\n";
        exit(1);
    }
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    echo "DSN: $dsn\n";
    echo "User: $username\n";
    echo "Password: " . (strlen($password) > 0 ? "Défini (" . strlen($password) . " caractères)" : "VIDE") . "\n";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connexion MySQL réussie\n\n";
    
    // 3. Analyser la structure table users
    echo "3️⃣ STRUCTURE TABLE USERS\n";
    echo str_repeat("-", 30) . "\n";
    
    $columns = $pdo->query("DESCRIBE users")->fetchAll();
    
    echo "Colonnes trouvées (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        $nullable = ($col['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
        $key = $col['Key'] ? " [{$col['Key']}]" : '';
        $default = $col['Default'] ? " DEFAULT({$col['Default']})" : '';
        echo "   - {$col['Field']} ({$col['Type']}) $nullable$key$default\n";
    }
    
    // 4. Identifier colonnes authentification
    echo "\n4️⃣ IDENTIFICATION COLONNES AUTH\n";
    echo str_repeat("-", 40) . "\n";
    
    $allColumns = array_column($columns, 'Field');
    $authConfig = [];
    
    // Email/Mail
    if (in_array('email', $allColumns)) {
        $authConfig['email_column'] = 'email';
        echo "✅ Colonne email: 'email'\n";
    } elseif (in_array('mail', $allColumns)) {
        $authConfig['email_column'] = 'mail';
        echo "✅ Colonne email: 'mail'\n";
    } else {
        echo "❌ Aucune colonne email/mail trouvée\n";
        exit(1);
    }
    
    // Password
    if (in_array('password_hash', $allColumns)) {
        $authConfig['password_column'] = 'password_hash';
        echo "✅ Colonne password: 'password_hash'\n";
    } elseif (in_array('password', $allColumns)) {
        $authConfig['password_column'] = 'password';
        echo "✅ Colonne password: 'password'\n";
    } else {
        echo "❌ Aucune colonne password trouvée\n";
        exit(1);
    }
    
    // Actif/Active
    if (in_array('actif', $allColumns)) {
        $authConfig['active_column'] = 'actif';
        echo "✅ Colonne actif: 'actif'\n";
    } elseif (in_array('is_active', $allColumns)) {
        $authConfig['active_column'] = 'is_active';
        echo "✅ Colonne actif: 'is_active'\n";
    } elseif (in_array('active', $allColumns)) {
        $authConfig['active_column'] = 'active';
        echo "✅ Colonne actif: 'active'\n";
    } else {
        $authConfig['active_column'] = null;
        echo "⚠️ Aucune colonne actif (optionnel)\n";
    }
    
    // Autorisation
    if (in_array('autorisation', $allColumns)) {
        $authConfig['role_column'] = 'autorisation';
        echo "✅ Colonne rôle: 'autorisation'\n";
    } elseif (in_array('role', $allColumns)) {
        $authConfig['role_column'] = 'role';
        echo "✅ Colonne rôle: 'role'\n";
    } else {
        $authConfig['role_column'] = null;
        echo "⚠️ Aucune colonne rôle trouvée\n";
    }
    
    // 5. Analyser les utilisateurs existants
    echo "\n5️⃣ UTILISATEURS EXISTANTS\n";
    echo str_repeat("-", 30) . "\n";
    
    $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    echo "Nombre total d'utilisateurs: $userCount\n\n";
    
    if ($userCount > 0) {
        $users = $pdo->query("SELECT * FROM users ORDER BY id LIMIT 10")->fetchAll();
        
        echo "Premiers utilisateurs:\n";
        foreach ($users as $user) {
            $email = $user[$authConfig['email_column']];
            $role = $user[$authConfig['role_column']] ?? 'N/A';
            $active = $authConfig['active_column'] ? ($user[$authConfig['active_column']] ?? 'N/A') : 'N/A';
            echo "   - ID: {$user['id']}, Email: $email, Rôle: $role, Actif: $active\n";
        }
        
        // Chercher un admin
        echo "\nRecherche administrateur:\n";
        $adminQuery = "SELECT * FROM users WHERE " . ($authConfig['role_column'] ?: 'id') . " = 0 LIMIT 1";
        $admin = $pdo->query($adminQuery)->fetch();
        
        if ($admin) {
            $adminEmail = $admin[$authConfig['email_column']];
            echo "✅ Admin trouvé: $adminEmail (ID: {$admin['id']})\n";
        } else {
            echo "⚠️ Aucun admin trouvé (autorisation = 0)\n";
            $firstUser = $users[0];
            $firstEmail = $firstUser[$authConfig['email_column']];
            echo "Premier utilisateur: $firstEmail (ID: {$firstUser['id']})\n";
        }
    }
    
    // 6. Générer la requête exacte
    echo "\n6️⃣ GÉNÉRATION CODE EXACT\n";
    echo str_repeat("-", 30) . "\n";
    
    $emailCol = $authConfig['email_column'];
    $passwordCol = $authConfig['password_column'];
    $activeCol = $authConfig['active_column'];
    
    if ($activeCol) {
        $whereClause = "$emailCol = ? AND $activeCol = 1";
        $params = '[$email]';
    } else {
        $whereClause = "$emailCol = ?";
        $params = '[$email]';
    }
    
    $exactQuery = "SELECT * FROM users WHERE $whereClause LIMIT 1";
    
    echo "Requête SQL exacte:\n";
    echo "   $exactQuery\n\n";
    
    echo "Code pour AuthService::attempt():\n";
    echo "```php\n";
    echo "// CONFIGURATION EXACTE POUR VOTRE BASE DE PRODUCTION\n";
    echo "\$result = \$this->db->fetchOne(\"$exactQuery\", $params);\n\n";
    echo "if (!\$result) {\n";
    echo "    return false;\n";
    echo "}\n\n";
    echo "// Vérifier le mot de passe\n";
    echo "if (!password_verify(\$password, \$result['$passwordCol'])) {\n";
    echo "    return false;\n";
    echo "}\n";
    echo "```\n\n";
    
    // 7. Test de la requête
    echo "7️⃣ TEST REQUÊTE\n";
    echo str_repeat("-", 20) . "\n";
    
    if ($userCount > 0 && isset($users[0])) {
        $testUser = $users[0];
        $testEmail = $testUser[$emailCol];
        
        echo "Test avec: $testEmail\n";
        
        $stmt = $pdo->prepare($exactQuery);
        $stmt->execute([$testEmail]);
        $testResult = $stmt->fetch();
        
        if ($testResult) {
            echo "✅ Requête fonctionne parfaitement\n";
            echo "   - Utilisateur trouvé: {$testResult['id']}\n";
            echo "   - Email: {$testResult[$emailCol]}\n";
            echo "   - Password hash: " . substr($testResult[$passwordCol], 0, 20) . "...\n";
        } else {
            echo "❌ Problème avec la requête\n";
        }
    }
    
    // 8. Créer le rapport final
    echo "\n8️⃣ GÉNÉRATION RAPPORT FINAL\n";
    echo str_repeat("-", 35) . "\n";
    
    $reportData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => [
            'host' => $host,
            'database' => $database,
            'port' => $port,
            'connection' => 'SUCCESS'
        ],
        'table_structure' => [
            'total_columns' => count($columns),
            'columns' => $allColumns
        ],
        'auth_config' => $authConfig,
        'users' => [
            'total_count' => $userCount,
            'admin_found' => isset($admin),
            'admin_email' => $admin ? $admin[$authConfig['email_column']] : null
        ],
        'sql_query' => $exactQuery,
        'php_code' => "\$result = \$this->db->fetchOne(\"$exactQuery\", $params);"
    ];
    
    $reportJson = json_encode($reportData, JSON_PRETTY_PRINT);
    file_put_contents('production_db_analysis_report.json', $reportJson);
    
    $reportMd = "# RAPPORT ANALYSE BASE DE PRODUCTION\n\n";
    $reportMd .= "**Date:** " . $reportData['timestamp'] . "\n\n";
    $reportMd .= "## 🗄️ Configuration Base de Données\n";
    $reportMd .= "- **Host:** {$reportData['database']['host']}\n";
    $reportMd .= "- **Database:** {$reportData['database']['database']}\n";
    $reportMd .= "- **Port:** {$reportData['database']['port']}\n";
    $reportMd .= "- **Connexion:** ✅ SUCCESS\n\n";
    
    $reportMd .= "## 📋 Structure Table Users\n";
    $reportMd .= "**Colonnes trouvées:** " . count($columns) . "\n\n";
    foreach ($columns as $col) {
        $reportMd .= "- `{$col['Field']}` ({$col['Type']}) " . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    $reportMd .= "\n## 🔐 Configuration Authentification\n";
    $reportMd .= "- **Email:** `{$authConfig['email_column']}`\n";
    $reportMd .= "- **Password:** `{$authConfig['password_column']}`\n";
    $reportMd .= "- **Actif:** `" . ($authConfig['active_column'] ?: 'aucune') . "`\n";
    $reportMd .= "- **Rôle:** `" . ($authConfig['role_column'] ?: 'aucune') . "`\n\n";
    
    $reportMd .= "## 👥 Utilisateurs\n";
    $reportMd .= "- **Total:** $userCount utilisateurs\n";
    $reportMd .= "- **Admin trouvé:** " . (isset($admin) ? "✅ " . $admin[$authConfig['email_column']] : "❌") . "\n\n";
    
    $reportMd .= "## 💻 Code à Utiliser\n";
    $reportMd .= "### Requête SQL\n";
    $reportMd .= "```sql\n$exactQuery\n```\n\n";
    $reportMd .= "### Code PHP (AuthService)\n";
    $reportMd .= "```php\n";
    $reportMd .= "// Remplacer dans AuthService::attempt()\n";
    $reportMd .= "\$result = \$this->db->fetchOne(\"$exactQuery\", $params);\n\n";
    $reportMd .= "if (!\$result) {\n    return false;\n}\n\n";
    $reportMd .= "if (!password_verify(\$password, \$result['{$authConfig['password_column']}'])) {\n    return false;\n}\n";
    $reportMd .= "```\n";
    
    file_put_contents('RAPPORT_ANALYSE_PRODUCTION.md', $reportMd);
    
    echo "✅ Rapport JSON: production_db_analysis_report.json\n";
    echo "✅ Rapport Markdown: RAPPORT_ANALYSE_PRODUCTION.md\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ ANALYSE TERMINÉE AVEC SUCCÈS\n";
    echo "📋 Configuration identifiée:\n";
    echo "   - Email: {$authConfig['email_column']}\n";
    echo "   - Password: {$authConfig['password_column']}\n";
    echo "   - Requête: $exactQuery\n";
    echo "🎯 Utilisez le code généré dans AuthService.php\n";
    echo str_repeat("=", 60) . "\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Rapport d'erreur
    $errorReport = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'env_file_exists' => file_exists('.env'),
        'env_vars_loaded' => isset($envVars) ? count($envVars) : 0
    ];
    
    file_put_contents('production_db_error_report.json', json_encode($errorReport, JSON_PRETTY_PRINT));
    echo "\n📋 Rapport d'erreur: production_db_error_report.json\n";
}

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";