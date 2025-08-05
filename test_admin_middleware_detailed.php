<?php
/**
 * ANALYSE DÉTAILLÉE ADMINMIDDLEWARE - Problème spécifique mentionné dans CLAUDE.md
 * Le problème: "Niveau 1 ET 0 requis mais logique incorrecte"
 */

require_once __DIR__ . '/bootstrap.php';

// Couleurs pour l'affichage
$colors = [
    'success' => "\033[32m",
    'error' => "\033[31m",
    'warning' => "\033[33m",
    'info' => "\033[36m",
    'reset' => "\033[0m"
];

function colorize($text, $color) {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

echo colorize("=== ANALYSE DÉTAILLÉE ADMINMIDDLEWARE ===\n", 'info');
echo colorize("Analyse du problème spécifique mentionné dans CLAUDE.md\n\n", 'warning');

try {
    $db = new TopoclimbCH\Core\Database();
    $session = new TopoclimbCH\Core\Session();
    $middleware = new TopoclimbCH\Middleware\AdminMiddleware($session, $db);
    
    // Utiliser réflexion pour analyser la logique permissions
    $reflection = new ReflectionClass($middleware);
    $permissionsProperty = $reflection->getProperty('permissions');
    $permissionsProperty->setAccessible(true);
    $permissions = $permissionsProperty->getValue($middleware);
    
    echo colorize("1. ANALYSE MATRICE DES PERMISSIONS\n", 'info');
    echo str_repeat('-', 50) . "\n";
    
    // Analyser les permissions par niveau
    foreach ($permissions as $level => $routes) {
        echo colorize("Niveau $level:\n", 'info');
        foreach ($routes as $route => $allowed) {
            $status = $allowed ? 'AUTORISÉ' : 'REFUSÉ';
            $color = $allowed ? 'success' : 'error';
            echo colorize("  $route: $status\n", $color);
        }
        echo "\n";
    }
    
    echo colorize("2. TEST DES SCÉNARIOS PROBLÉMATIQUES\n", 'info');
    echo str_repeat('-', 50) . "\n";
    
    // Scénarios de test spécifiques
    $testScenarios = [
        // Problème mentionné dans CLAUDE.md: système admin
        ['level' => '0', 'path' => '/admin/system', 'description' => 'Super Admin → Système (doit être AUTORISÉ)'],
        ['level' => '1', 'path' => '/admin/system', 'description' => 'Admin → Système (doit être REFUSÉ selon matrice)'],
        ['level' => '1', 'path' => '/admin/database', 'description' => 'Admin → Base de données (doit être REFUSÉ)'],
        ['level' => '1', 'path' => '/admin/security', 'description' => 'Admin → Sécurité (doit être REFUSÉ)'],
        ['level' => '1', 'path' => '/admin/permissions', 'description' => 'Admin → Permissions (doit être REFUSÉ)'],
        
        // Tests escalade de privilèges
        ['level' => '2', 'path' => '/admin', 'description' => 'Modérateur → Admin panel (doit être REFUSÉ)'],
        ['level' => '2', 'path' => '/admin/users', 'description' => 'Modérateur → Gestion users (doit être AUTORISÉ)'],
        ['level' => '3', 'path' => '/admin', 'description' => 'Utilisateur → Admin panel (doit être REFUSÉ)'],
        ['level' => '4', 'path' => '/admin', 'description' => 'En attente → Admin panel (doit être REFUSÉ)'],
        ['level' => '5', 'path' => '/admin', 'description' => 'Banni → Admin panel (doit être REFUSÉ)'],
    ];
    
    $hasPermissionMethod = $reflection->getMethod('hasPermission');
    $hasPermissionMethod->setAccessible(true);
    
    $bugs = 0;
    foreach ($testScenarios as $scenario) {
        $result = $hasPermissionMethod->invoke($middleware, $scenario['level'], $scenario['path']);
        
        // Déterminer résultat attendu
        $expected = isset($permissions[$scenario['level']][$scenario['path']]) ? 
                   $permissions[$scenario['level']][$scenario['path']] : 
                   false; // Par défaut refusé
        
        $status = ($result === $expected) ? 'OK' : 'BUG';
        $color = ($result === $expected) ? 'success' : 'error';
        
        echo colorize("Test: {$scenario['description']}\n", 'info');
        echo colorize("  Résultat: " . ($result ? 'AUTORISÉ' : 'REFUSÉ') . " - $status\n", $color);
        
        if ($result !== $expected) {
            $bugs++;
            echo colorize("  ⚠️  PROBLÈME: Attendu " . ($expected ? 'AUTORISÉ' : 'REFUSÉ') . " mais obtenu " . ($result ? 'AUTORISÉ' : 'REFUSÉ') . "\n", 'error');
        }
        echo "\n";
    }
    
    echo colorize("3. ANALYSE LOGIQUE HASPERMISSION()\n", 'info');
    echo str_repeat('-', 50) . "\n";
    
    // Tester logique avec utilisateurs non autorisés
    $unauthorizedLevels = ['3', '4', '5', null];
    foreach ($unauthorizedLevels as $level) {
        $result = $hasPermissionMethod->invoke($middleware, $level, '/admin');
        if ($result) {
            echo colorize("🚨 BUG CRITIQUE: Niveau '$level' autorisé sur /admin\n", 'error');
            $bugs++;
        } else {
            echo colorize("✓ Niveau '$level' correctement bloqué sur /admin\n", 'success');
        }
    }
    
    echo colorize("\n4. TEST PATTERNS WILDCARDS\n", 'info');
    echo str_repeat('-', 50) . "\n";
    
    // Test patterns avec wildcards
    $wildcardTests = [
        ['level' => '0', 'path' => '/admin/users/123/edit', 'expected' => true],
        ['level' => '1', 'path' => '/admin/users/456/edit', 'expected' => true],
        ['level' => '2', 'path' => '/admin/users/789/edit', 'expected' => true],
        ['level' => '3', 'path' => '/admin/users/999/edit', 'expected' => false],
    ];
    
    foreach ($wildcardTests as $test) {
        $result = $hasPermissionMethod->invoke($middleware, $test['level'], $test['path']);
        $status = ($result === $test['expected']) ? 'OK' : 'BUG';
        $color = ($result === $test['expected']) ? 'success' : 'error';
        
        echo colorize("Niveau {$test['level']} → {$test['path']}: " . ($result ? 'AUTORISÉ' : 'REFUSÉ') . " - $status\n", $color);
        
        if ($result !== $test['expected']) {
            $bugs++;
        }
    }
    
    echo colorize("\n5. VÉRIFICATION MÉTHODE requireMinLevel()\n", 'info');
    echo str_repeat('-', 50) . "\n";
    
    // Créer un utilisateur de test pour requireMinLevel
    $testUser = $db->fetchOne("SELECT * FROM users WHERE autorisation = '1' LIMIT 1");
    if ($testUser) {
        // Simuler connexion
        $auth = new TopoclimbCH\Core\Auth($session, $db);
        $user = TopoclimbCH\Models\User::fromDatabase($testUser);
        $auth->login($user);
        
        // Créer nouveau middleware avec utilisateur connecté
        $middlewareWithUser = new TopoclimbCH\Middleware\AdminMiddleware($session, $db);
        
        $requireMinLevelMethod = $reflection->getMethod('requireMinLevel');
        $requireMinLevelMethod->setAccessible(true);
        
        $levelTests = [
            ['userLevel' => '1', 'minRequired' => '0', 'expected' => false], // Admin ne peut pas accéder niveau Super Admin
            ['userLevel' => '1', 'minRequired' => '1', 'expected' => true],  // Admin peut accéder niveau Admin
            ['userLevel' => '1', 'minRequired' => '2', 'expected' => true],  // Admin peut accéder niveau Modérateur
        ];
        
        foreach ($levelTests as $test) {
            $result = $requireMinLevelMethod->invoke($middlewareWithUser, $test['minRequired']);
            $status = ($result === $test['expected']) ? 'OK' : 'BUG';
            $color = ($result === $test['expected']) ? 'success' : 'error';
            
            echo colorize("User niveau {$test['userLevel']}, min requis {$test['minRequired']}: " . 
                         ($result ? 'AUTORISÉ' : 'REFUSÉ') . " - $status\n", $color);
            
            if ($result !== $test['expected']) {
                $bugs++;
            }
        }
        
        $auth->logout();
    }
    
    echo colorize("\n" . str_repeat('=', 60) . "\n", 'info');
    echo colorize("RÉSULTAT ANALYSE ADMINMIDDLEWARE\n", 'info');
    echo colorize(str_repeat('=', 60) . "\n", 'info');
    
    if ($bugs > 0) {
        echo colorize("🚨 $bugs BUGS DÉTECTÉS dans AdminMiddleware\n", 'error');
        echo colorize("\nActions correctives requises:\n", 'warning');
        echo colorize("1. Vérifier logique hasPermission() pour niveaux non autorisés\n", 'warning');
        echo colorize("2. Tester patterns wildcards avec utilisateurs réels\n", 'warning');
        echo colorize("3. Valider requireMinLevel() avec différents niveaux\n", 'warning');
    } else {
        echo colorize("✅ AdminMiddleware fonctionne correctement!\n", 'success');
        echo colorize("Aucun bug détecté dans la logique des permissions.\n", 'success');
    }
    
} catch (Exception $e) {
    echo colorize("❌ Erreur lors de l'analyse: " . $e->getMessage() . "\n", 'error');
}

echo colorize("\nAnalyse terminée.\n", 'info');
?>