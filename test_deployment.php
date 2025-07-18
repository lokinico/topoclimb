<?php
/**
 * Script de test pour l'environnement de déploiement TopoclimbCH
 * 
 * Ce script teste les routes critiques qui ont été réparées dans le commit récent
 * et vérifie que l'application fonctionne correctement en production.
 */

// Configuration
$BASE_URL = 'https://topoclimb.ch'; // Modifier selon votre domaine
$TIMEOUT = 10; // Timeout en secondes
$DEBUG = true; // Activer pour voir les détails

// Couleurs pour le terminal
$colors = [
    'success' => "\033[32m", // Vert
    'error' => "\033[31m",   // Rouge
    'warning' => "\033[33m", // Jaune
    'info' => "\033[34m",    // Bleu
    'reset' => "\033[0m"     // Reset
];

function colorize($text, $color, $colors) {
    return $colors[$color] . $text . $colors['reset'];
}

function testRoute($url, $expectedStatus = 200, $expectedContent = null) {
    global $TIMEOUT, $DEBUG, $colors;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $TIMEOUT);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TopoclimbCH-TestBot/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Pour les tests
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'url' => $url,
        'status' => $httpCode,
        'content_type' => $contentType,
        'response_time' => $responseTime,
        'error' => $error,
        'success' => false
    ];
    
    // Vérifier le statut HTTP
    if ($httpCode === $expectedStatus) {
        $result['success'] = true;
    } else {
        $result['success'] = false;
        $result['error'] = "Expected status $expectedStatus, got $httpCode";
    }
    
    // Vérifier le contenu si spécifié
    if ($expectedContent && $result['success']) {
        if (strpos($response, $expectedContent) === false) {
            $result['success'] = false;
            $result['error'] = "Expected content '$expectedContent' not found";
        }
    }
    
    // Afficher le résultat
    $statusColor = $result['success'] ? 'success' : 'error';
    $statusText = $result['success'] ? '✅ PASS' : '❌ FAIL';
    
    echo colorize($statusText, $statusColor, $colors) . " ";
    echo colorize($url, 'info', $colors) . " ";
    echo "({$responseTime}ms) ";
    
    if (!$result['success']) {
        echo colorize("- {$result['error']}", 'error', $colors);
    }
    echo "\n";
    
    if ($DEBUG && !$result['success']) {
        echo "  Debug: Status $httpCode, Content-Type: $contentType\n";
        if ($response) {
            echo "  First 200 chars: " . substr($response, 0, 200) . "...\n";
        }
    }
    
    return $result;
}

// Tests à exécuter
$tests = [
    // Routes critiques réparées
    [
        'name' => 'Checklists de sécurité',
        'url' => '/checklists',
        'expected_status' => 200,
        'expected_content' => 'Checklists de sécurité'
    ],
    [
        'name' => 'Gestion d\'équipement',
        'url' => '/equipment',
        'expected_status' => 200,
        'expected_content' => 'Types d\'équipement'
    ],
    [
        'name' => 'Carte interactive',
        'url' => '/map',
        'expected_status' => 200,
        'expected_content' => 'Carte Interactive'
    ],
    
    // Routes principales
    [
        'name' => 'Page d\'accueil',
        'url' => '/',
        'expected_status' => 200,
        'expected_content' => 'TopoclimbCH'
    ],
    [
        'name' => 'Régions',
        'url' => '/regions',
        'expected_status' => 200,
        'expected_content' => null
    ],
    [
        'name' => 'Sites',
        'url' => '/sites',
        'expected_status' => 200,
        'expected_content' => null
    ],
    [
        'name' => 'Secteurs',
        'url' => '/sectors',
        'expected_status' => 200,
        'expected_content' => null
    ],
    [
        'name' => 'Voies',
        'url' => '/routes',
        'expected_status' => 200,
        'expected_content' => null
    ],
    
    // APIs
    [
        'name' => 'API Régions',
        'url' => '/api/regions',
        'expected_status' => 200,
        'expected_content' => null
    ],
    [
        'name' => 'API Sites Map',
        'url' => '/api/map/sites',
        'expected_status' => 200,
        'expected_content' => null
    ],
    
    // Assets critiques
    [
        'name' => 'CSS Principal',
        'url' => '/css/app.css',
        'expected_status' => 200,
        'expected_content' => null
    ],
    [
        'name' => 'CSS Carte',
        'url' => '/css/pages/map.css',
        'expected_status' => 200,
        'expected_content' => null
    ]
];

// Exécuter les tests
echo colorize("🧪 TESTS DE DÉPLOIEMENT TOPOCLIMBCH", 'info', $colors) . "\n";
echo colorize("=====================================", 'info', $colors) . "\n";
echo "URL de base: " . colorize($BASE_URL, 'info', $colors) . "\n";
echo "Timeout: {$TIMEOUT}s\n\n";

$results = [];
$totalTests = count($tests);
$passedTests = 0;

foreach ($tests as $test) {
    $url = $BASE_URL . $test['url'];
    $result = testRoute($url, $test['expected_status'], $test['expected_content']);
    $results[] = $result;
    
    if ($result['success']) {
        $passedTests++;
    }
}

// Résumé
echo "\n" . colorize("📊 RÉSUMÉ DES TESTS", 'info', $colors) . "\n";
echo colorize("===================", 'info', $colors) . "\n";
echo "Total: $totalTests tests\n";
echo colorize("Réussis: $passedTests", 'success', $colors) . "\n";
echo colorize("Échoués: " . ($totalTests - $passedTests), 'error', $colors) . "\n";

$successRate = round(($passedTests / $totalTests) * 100, 1);
$rateColor = $successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'error');
echo "Taux de réussite: " . colorize("{$successRate}%", $rateColor, $colors) . "\n";

// Statistiques de performance
$responseTimes = array_column($results, 'response_time');
$avgResponseTime = round(array_sum($responseTimes) / count($responseTimes), 2);
$maxResponseTime = max($responseTimes);
$minResponseTime = min($responseTimes);

echo "\n" . colorize("⚡ PERFORMANCES", 'info', $colors) . "\n";
echo colorize("===============", 'info', $colors) . "\n";
echo "Temps de réponse moyen: {$avgResponseTime}ms\n";
echo "Temps de réponse max: {$maxResponseTime}ms\n";
echo "Temps de réponse min: {$minResponseTime}ms\n";

// Tests échoués
$failedTests = array_filter($results, function($result) {
    return !$result['success'];
});

if (!empty($failedTests)) {
    echo "\n" . colorize("❌ TESTS ÉCHOUÉS", 'error', $colors) . "\n";
    echo colorize("================", 'error', $colors) . "\n";
    foreach ($failedTests as $test) {
        echo "- " . colorize($test['url'], 'error', $colors) . ": {$test['error']}\n";
    }
}

// Recommandations
echo "\n" . colorize("💡 RECOMMANDATIONS", 'info', $colors) . "\n";
echo colorize("==================", 'info', $colors) . "\n";

if ($successRate >= 90) {
    echo colorize("✅ Excellent! L'application fonctionne correctement.", 'success', $colors) . "\n";
} elseif ($successRate >= 70) {
    echo colorize("⚠️  Quelques problèmes détectés. Vérifiez les tests échoués.", 'warning', $colors) . "\n";
} else {
    echo colorize("🚨 Problèmes critiques détectés. Intervention nécessaire.", 'error', $colors) . "\n";
}

if ($avgResponseTime > 2000) {
    echo colorize("⚠️  Temps de réponse élevé. Considérez l'optimisation.", 'warning', $colors) . "\n";
} elseif ($avgResponseTime < 500) {
    echo colorize("✅ Excellentes performances de réponse.", 'success', $colors) . "\n";
}

// Code de sortie
exit($passedTests === $totalTests ? 0 : 1);