<?php
session_start();
require_once 'bootstrap.php';
use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== CRÉER SESSION VALIDE ===\n\n";

// Simuler une session utilisateur valide (admin niveau 1)
$_SESSION['user_id'] = 8; // ID de admin@test.ch
$_SESSION['user'] = [
    'id' => 8,
    'nom' => 'Admin',
    'prenom' => 'User',
    'mail' => 'admin@test.ch',
    'autorisation' => '1', // Admin
    'username' => 'admin'
];

echo "✅ Session créée pour utilisateur admin (niveau 1)\n";
echo "Session ID: " . session_id() . "\n";

// Sauvegarder l'ID de session pour réutilisation
file_put_contents('/tmp/session_id.txt', session_id());
echo "Session ID sauvegardé dans /tmp/session_id.txt\n";

// Test d'accès aux secteurs maintenant
echo "\n=== TEST ACCÈS SECTEURS ===\n";

try {
    // Créer une requête HTTP avec les cookies de session
    $sessionId = session_id();
    $cookie = "PHPSESSID={$sessionId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: {$cookie}\r\n"
        ]
    ]);
    
    echo "Test avec curl...\n";
    $cmd = "curl -s -b 'PHPSESSID={$sessionId}' 'http://localhost:8000/sectors' | grep -E 'Secteur Sud|1 secteur|empty-state' | head -3";
    echo "Commande: {$cmd}\n";
    
    $result = shell_exec($cmd);
    echo "Résultat: " . ($result ?: "Aucun résultat") . "\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n✅ Session prête pour test avec curl\n";