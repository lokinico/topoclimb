<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use Symfony\Component\HttpFoundation\Request;

echo "🔐 DEBUG POST LOGIN\n\n";

// Simuler la requête POST /login
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['PATH_INFO'] = '/login';

// Données POST simulées
$_POST = [
    'username' => 'admin_test',
    'password' => 'TestAdmin2025!',
    'csrf_token' => 'test-token'
];

echo "1. Simulation POST /login avec:\n";
echo "   - Username: admin_test\n";
echo "   - Password: TestAdmin2025!\n";
echo "   - CSRF Token: test-token\n\n";

try {
    // Initialiser services
    $session = new Session();
    $db = new Database();
    $auth = new Auth($session, $db);
    
    echo "2. Services initialisés\n";
    
    // Test direct de la méthode attempt
    echo "3. Test attempt direct...\n";
    $loginResult = $auth->attempt('admin_test', 'TestAdmin2025!');
    
    if ($loginResult) {
        echo "✅ Login direct réussi\n";
        echo "   Session auth_user_id: " . ($_SESSION['auth_user_id'] ?? 'non défini') . "\n";
        echo "   Session is_authenticated: " . ($_SESSION['is_authenticated'] ?? 'non défini') . "\n";
    } else {
        echo "❌ Login direct échoué\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}