<?php
/**
 * Script de test local pour TopoclimbCH
 * Teste les routes critiques réparées sur l'environnement de développement
 */

// Configuration
$BASE_URL = 'http://localhost:8080'; // Serveur de développement
$TIMEOUT = 5;

// Couleurs pour le terminal
$colors = [
    'success' => "\033[32m",
    'error' => "\033[31m",
    'warning' => "\033[33m",
    'info' => "\033[34m",
    'reset' => "\033[0m"
];

function colorize($text, $color, $colors) {
    return $colors[$color] . $text . $colors['reset'];
}

function testRoute($url, $expectedStatus = 200, $expectedContent = null) {
    global $TIMEOUT, $colors;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $TIMEOUT);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TopoclimbCH-LocalTest/1.0');
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    $error = curl_error($ch);
    curl_close($ch);
    
    $success = ($httpCode === $expectedStatus);
    if ($expectedContent && $success) {
        $success = (strpos($response, $expectedContent) !== false);
    }
    
    $statusColor = $success ? 'success' : 'error';
    $statusText = $success ? '✅' : '❌';
    
    echo $statusText . " ";
    echo colorize(basename($url), 'info', $colors) . " ";
    echo "({$responseTime}ms) ";
    
    if (!$success) {
        echo colorize("Status: $httpCode", 'error', $colors);
        if ($error) {
            echo colorize(" - $error", 'error', $colors);
        }
    }
    echo "\n";
    
    return $success;
}

// Démarrer le serveur de développement en arrière-plan
echo colorize("🚀 Démarrage du serveur de développement...", 'info', $colors) . "\n";
exec('php -S localhost:8080 -t public/ > /dev/null 2>&1 &');
sleep(2); // Attendre que le serveur démarre

// Tests critiques
echo colorize("🧪 Tests des routes critiques réparées", 'info', $colors) . "\n";
echo colorize("=====================================", 'info', $colors) . "\n";

$tests = [
    ['/checklists', 200, 'Checklists de sécurité'],
    ['/equipment', 200, 'Types d\'équipement'],
    ['/map', 200, 'Carte Interactive'],
    ['/', 200, 'TopoclimbCH'],
    ['/regions', 200, null],
    ['/sites', 200, null],
    ['/sectors', 200, null],
    ['/routes', 200, null]
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test) {
    $url = $BASE_URL . $test[0];
    if (testRoute($url, $test[1], $test[2] ?? null)) {
        $passed++;
    }
}

echo "\n" . colorize("📊 Résultat: $passed/$total tests réussis", 'info', $colors) . "\n";

if ($passed === $total) {
    echo colorize("✅ Tous les tests sont passés! Prêt pour le déploiement.", 'success', $colors) . "\n";
} else {
    echo colorize("❌ Certains tests ont échoué. Vérifiez les routes.", 'error', $colors) . "\n";
}

// Arrêter le serveur
exec('pkill -f "php -S localhost:8080"');

exit($passed === $total ? 0 : 1);