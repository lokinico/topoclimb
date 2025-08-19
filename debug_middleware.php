<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

// Simulate session data from successful login
$_SESSION['auth_user_id'] = 7;
$_SESSION['is_authenticated'] = true;

echo "=== MIDDLEWARE SIMULATION ===" . PHP_EOL;

$currentPath = '/routes/create';
echo "Path: $currentPath" . PHP_EOL;

$PUBLIC_ROUTES = [
    '/',
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/about',
    '/contact',
    '/privacy',
    '/terms',
    // Temporaire pour les tests
    '/regions',
    '/regions/create',
    '/sites',
    '/sites/create',
    '/sectors',
    '/sectors/create',
    '/routes',
    '/routes/create',
    '/books',
    '/books/create',
    '/profile',
    '/settings',
    '/admin',
    '/admin/users'
];

echo "Is public route: " . (in_array($currentPath, $PUBLIC_ROUTES) ? "YES" : "NO") . PHP_EOL;

// Vérification de l'authentification
$hasAuthUserId = isset($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'];
$isAuthenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

echo "Has auth_user_id: " . ($hasAuthUserId ? "YES (" . $_SESSION['auth_user_id'] . ")" : "NO") . PHP_EOL;
echo "Is authenticated: " . ($isAuthenticated ? "YES" : "NO") . PHP_EOL;

echo "=== AUTH CLASS SIMULATION ===" . PHP_EOL;

$db = new Database();
$session = new Session();
$auth = new Auth($session, $db);

echo "Auth check: " . ($auth->check() ? "TRUE" : "FALSE") . PHP_EOL;
echo "User ID: " . ($auth->id() ?? "NULL") . PHP_EOL;
echo "User role: " . $auth->role() . PHP_EOL;

echo "=== MIDDLEWARE RESULT ===" . PHP_EOL;

if (in_array($currentPath, $PUBLIC_ROUTES)) {
    echo "RESULT: Should proceed (public route)" . PHP_EOL;
} elseif ($hasAuthUserId && $isAuthenticated) {
    echo "RESULT: Should proceed (authenticated)" . PHP_EOL;
} else {
    echo "RESULT: Should redirect to login" . PHP_EOL;
}