<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

// Same session setup as login
$_SESSION['auth_user_id'] = 7;
$_SESSION['is_authenticated'] = true;

echo 'SESSION DATA:' . PHP_EOL;
echo '  auth_user_id: ' . ($_SESSION['auth_user_id'] ?? 'NULL') . PHP_EOL;
echo '  is_authenticated: ' . ($_SESSION['is_authenticated'] ?? 'NULL') . PHP_EOL;

$db = new Database();
$session = new Session();

echo 'SESSION CLASS:' . PHP_EOL;
echo '  session->get(auth_user_id): ' . ($session->get('auth_user_id') ?? 'NULL') . PHP_EOL;
echo '  session->get(is_authenticated): ' . ($session->get('is_authenticated') ?? 'NULL') . PHP_EOL;

// Create Auth and track step by step
echo 'AUTH CREATION:' . PHP_EOL;
$auth = new Auth($session, $db);

echo 'AUTH METHODS:' . PHP_EOL;
echo '  auth->check(): ' . ($auth->check() ? 'TRUE' : 'FALSE') . PHP_EOL; 
echo '  auth->user(): ' . ($auth->user() ? 'USER OBJECT' : 'NULL') . PHP_EOL;
echo '  auth->id(): ' . ($auth->id() ?? 'NULL') . PHP_EOL;
echo '  auth->role(): ' . $auth->role() . PHP_EOL;

// Test direct DB query in checkSession style
echo 'DIRECT DB CHECK:' . PHP_EOL;
$userId = $_SESSION['auth_user_id'] ?? $session->get('auth_user_id') ?? null;
echo '  Found userId: ' . ($userId ?? 'NULL') . PHP_EOL;

if ($userId) {
    $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
    $result = $db->query($query, [$userId])->fetch();
    echo '  DB query result: ' . ($result ? 'FOUND USER' : 'NOT FOUND') . PHP_EOL;
    if ($result) {
        echo '  User ID: ' . ($result['id'] ?? 'NULL') . PHP_EOL;
        echo '  Username: ' . ($result['username'] ?? 'NULL') . PHP_EOL;
        echo '  Autorisation: ' . ($result['autorisation'] ?? 'NULL') . PHP_EOL;
    }
}