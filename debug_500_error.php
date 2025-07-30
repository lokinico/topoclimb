<?php
/**
 * Script de diagnostic pour l'erreur 500 lors de la connexion
 */

echo "🔍 DIAGNOSTIC ERREUR 500 - CONNEXION\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Test 1: Vérification des fichiers critiques
    echo "1️⃣ VÉRIFICATION FICHIERS CRITIQUES\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $criticalFiles = [
        'src/Services/AuthService.php',
        'src/Core/Auth.php', 
        'src/Controllers/AuthController.php',
        'src/Core/Database.php',
        'climbing_sqlite.db'
    ];
    
    foreach ($criticalFiles as $file) {
        if (file_exists($file)) {
            echo "✅ $file\n";
        } else {
            echo "❌ $file - MANQUANT\n";
        }
    }
    
    // Test 2: Vérification autoloader
    echo "\n2️⃣ TEST AUTOLOADER ET CLASSES\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        echo "✅ Autoloader chargé\n";
        
        // Test des classes principales
        $classes = [
            'TopoclimbCH\\Services\\AuthService',
            'TopoclimbCH\\Core\\Auth',
            'TopoclimbCH\\Core\\Database',
            'TopoclimbCH\\Controllers\\AuthController'
        ];
        
        foreach ($classes as $class) {
            if (class_exists($class)) {
                echo "✅ $class\n";
            } else {
                echo "❌ $class - NON TROUVÉE\n";
            }
        }
    } else {
        echo "❌ vendor/autoload.php manquant\n";
    }
    
    // Test 3: Test base de données
    echo "\n3️⃣ TEST BASE DE DONNÉES\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion DB: OK\n";
    
    // Vérifier la structure users
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll();
    $columnNames = array_column($columns, 'name');
    
    echo "Colonnes users: " . implode(', ', $columnNames) . "\n";
    
    $hasEmail = in_array('email', $columnNames);
    $hasPasswordHash = in_array('password_hash', $columnNames);
    
    echo "   - email: " . ($hasEmail ? "✅" : "❌") . "\n";
    echo "   - password_hash: " . ($hasPasswordHash ? "✅" : "❌") . "\n";
    
    // Test 4: Test AuthService direct
    echo "\n4️⃣ TEST AUTHSERVICE DIRECT\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    try {
        // Test création Database
        $database = new TopoclimbCH\Core\Database();
        echo "✅ Database instanciée\n";
        
        // Mock des dépendances pour test
        $mockSession = new class {
            private $data = [];
            public function set($key, $value) { $this->data[$key] = $value; }
            public function get($key) { return $this->data[$key] ?? null; }
            public function has($key) { return isset($this->data[$key]); }
            public function remove($key) { unset($this->data[$key]); }
        };
        
        $mockCsrf = new class {
            public function generateToken() { return 'test_token'; }
            public function validateToken($token) { return true; }
        };
        
        $mockAuth = new class {
            private $user = null;
            public function setUser($user) { $this->user = $user; }
            public function user() { return $this->user; }
            public function check() { return $this->user !== null; }
            public function id() { return $this->user['id'] ?? null; }
        };
        
        // Test création AuthService
        $authService = new TopoclimbCH\Services\AuthService($database, $mockSession, $mockCsrf, $mockAuth);
        echo "✅ AuthService instancié\n";
        
        // Test login avec admin
        echo "\n   Test login admin@topoclimb.ch...\n";
        
        // Vérifier d'abord si l'utilisateur existe
        $emailColumn = $hasEmail ? 'email' : 'mail';
        $stmt = $database->query("SELECT * FROM users WHERE $emailColumn = ?", ['admin@topoclimb.ch']);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   ✅ Utilisateur trouvé: ID {$user['id']}\n";
            
            // Test password_verify
            $passwordField = $hasPasswordHash ? 'password_hash' : 'password';
            if (password_verify('admin123', $user[$passwordField])) {
                echo "   ✅ Mot de passe correct\n";
                
                // Test login complet
                $loginResult = $authService->login('admin@topoclimb.ch', 'admin123');
                
                if ($loginResult) {
                    echo "   ✅ Login AuthService: SUCCÈS\n";
                } else {
                    echo "   ❌ Login AuthService: ÉCHEC\n";
                }
                
            } else {
                echo "   ❌ Mot de passe incorrect\n";
            }
        } else {
            echo "   ❌ Utilisateur admin non trouvé\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur AuthService: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    // Test 5: Simulation requête POST login
    echo "\n5️⃣ SIMULATION REQUÊTE LOGIN\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Simuler les données POST
    $_POST = [
        'email' => 'admin@topoclimb.ch',
        'password' => 'admin123',
        'csrf_token' => 'test_token'
    ];
    
    echo "Données POST simulées:\n";
    foreach ($_POST as $key => $value) {
        $displayValue = $key === 'password' ? str_repeat('*', strlen($value)) : $value;
        echo "   - $key: $displayValue\n";
    }
    
    // Test création AuthController
    try {
        // Mock des dépendances
        $mockView = new class {
            public function render($template, $data = []) {
                return "Template: $template avec " . count($data) . " variables";
            }
        };
        
        $mockSession = new class {
            private $data = [];
            public function set($key, $value) { $this->data[$key] = $value; }
            public function get($key) { return $this->data[$key] ?? null; }
            public function has($key) { return isset($this->data[$key]); }
            public function flash($key, $value) { $this->data["flash_$key"] = $value; }
        };
        
        // Test instantiation AuthController
        $authController = new TopoclimbCH\Controllers\AuthController(
            $mockView,
            $mockSession,
            $mockCsrf,
            $database,
            $mockAuth
        );
        
        echo "✅ AuthController instancié\n";
        
    } catch (Exception $e) {
        echo "❌ Erreur AuthController: " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n📋 RECOMMANDATIONS:\n";
echo "1. Si erreur 500 persiste, vérifier les logs PHP du serveur\n";
echo "2. Activer display_errors dans php.ini temporairement\n";
echo "3. Vérifier les permissions des fichiers (644 pour PHP, 666 pour DB)\n";
echo "4. S'assurer que toutes les extensions PHP sont installées (PDO, SQLite)\n";
echo "5. Vérifier que le chemin vers climbing_sqlite.db est correct\n";

echo "\nDiagnostic terminé à " . date('Y-m-d H:i:s') . "\n";