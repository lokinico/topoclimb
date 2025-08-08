#!/usr/bin/env php
<?php

/**
 * Script de test pour l'authentification locale
 */
require_once __DIR__ . '/bootstrap.php';

echo "🔍 Test authentification locale\n";
echo "==============================\n\n";

// Test 1: Variables serveur
echo "1. Variables serveur:\n";
echo "   SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'non défini') . "\n";
echo "   SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'non défini') . "\n";
echo "   HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'non défini') . "\n";
echo "\n";

// Test 2: Conditions auto-login
$isLocalhost = isset($_SERVER['SERVER_NAME']) && 
    ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');
$isPort8000 = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '8000';

echo "2. Conditions auto-login:\n";
echo "   localhost: " . ($isLocalhost ? "✅" : "❌") . "\n";
echo "   port 8000: " . ($isPort8000 ? "✅" : "❌") . "\n";
echo "   auto-login: " . ($isLocalhost && $isPort8000 ? "✅" : "❌") . "\n";
echo "\n";

// Test 3: Session actuelle
session_start();
echo "3. Session actuelle:\n";
echo "   session_id: " . session_id() . "\n";
echo "   user_id: " . ($_SESSION['user_id'] ?? 'non défini') . "\n";
echo "   logged_in: " . ($_SESSION['logged_in'] ?? 'false') . "\n";
echo "   login_type: " . ($_SESSION['login_type'] ?? 'non défini') . "\n";
echo "\n";

// Test 4: Forcer authentification locale si nécessaire
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "4. Activation auto-login forcée:\n";
    
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'dev-admin';
    $_SESSION['email'] = 'dev@localhost';
    $_SESSION['access_level'] = 5;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_type'] = 'development';
    $_SESSION['dev_auto_login'] = true;
    
    echo "   ✅ Session configurée pour auto-login\n";
} else {
    echo "4. Auto-login déjà actif\n";
}

echo "\n";

// Test 5: URLs générées
echo "5. URLs générées:\n";
echo "   url('/'): " . url('/') . "\n";
echo "   url('regions'): " . url('regions') . "\n";
echo "   asset('css/app.css'): " . asset('css/app.css') . "\n";
echo "\n";

echo "✅ Test terminé !\n";