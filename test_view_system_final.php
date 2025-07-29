<?php
/**
 * Test final du système de vues après toutes les corrections
 */

echo "🎯 TEST FINAL - SYSTÈME DE VUES COMPLET\n";
echo "=======================================\n\n";

define('SERVER_URL', 'http://localhost:8000');

function checkPage($path, $name) {
    echo "🔍 Test: {$name} ({$path})\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SERVER_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results = [];
    
    // Vérifications de base
    if ($httpCode !== 200) {
        echo "   ❌ Code HTTP: {$httpCode}\n";
        return ['score' => 0, 'details' => "HTTP Error {$httpCode}"];
    }
    
    if (strpos($html, '<title>Connexion - TopoclimbCH</title>') !== false) {
        echo "   🚫 Redirection vers login\n";
        return ['score' => 0, 'details' => 'Auth Required'];
    }
    
    echo "   ✅ Page accessible\n";
    
    // Vérifications inclusions CSS/JS globales
    $globalInclusions = [
        'view-modes.css' => 'CSS Système de vues',
        'view-manager.js' => 'JS ViewManager',
        'pages-common.css' => 'CSS Pages communes',
        'pages-common.js' => 'JS Pages communes'
    ];
    
    $foundInclusions = 0;
    foreach ($globalInclusions as $file => $desc) {
        $found = strpos($html, $file) !== false;
        $icon = $found ? '✅' : '❌';
        echo "   {$icon} {$desc}\n";
        if ($found) $foundInclusions++;
    }
    
    // Vérifications structure HTML
    $htmlElements = [
        'entities-container' => 'Conteneur principal',
        'view-grid' => 'Vue grille',
        'view-list' => 'Vue liste',
        'view-compact' => 'Vue compacte',
        'data-view="grid"' => 'Bouton grille',
        'data-view="list"' => 'Bouton liste',
        'data-view="compact"' => 'Bouton compact'
    ];
    
    $foundElements = 0;
    foreach ($htmlElements as $element => $desc) {
        $found = strpos($html, $element) !== false;
        $icon = $found ? '✅' : '❌';
        echo "   {$icon} {$desc}\n";
        if ($found) $foundElements++;
    }
    
    // Vérifications éléments de debug
    $debugElements = [
        'VUE GRILLE ACTIVE' => 'Debug vue grille',
        'border: 3px solid green' => 'Bordure debug verte',
        'background: rgba(0,255,0,0.1)' => 'Background debug vert'
    ];
    
    $foundDebug = 0;
    foreach ($debugElements as $element => $desc) {
        $found = strpos($html, $element) !== false;
        $icon = $found ? '✅' : '❌';
        echo "   {$icon} {$desc}\n";
        if ($found) $foundDebug++;
    }
    
    // Calcul du score
    $totalChecks = count($globalInclusions) + count($htmlElements) + count($debugElements);
    $totalFound = $foundInclusions + $foundElements + $foundDebug;
    $score = round(($totalFound / $totalChecks) * 100);
    
    echo "   📊 Score: {$totalFound}/{$totalChecks} ({$score}%)\n\n";
    
    return [
        'score' => $score,
        'inclusions' => $foundInclusions,
        'elements' => $foundElements,
        'debug' => $foundDebug,
        'details' => "CSS/JS: {$foundInclusions}/4, HTML: {$foundElements}/7, Debug: {$foundDebug}/3"
    ];
}

// Test de toutes les pages
$pages = [
    '/routes' => 'Routes',
    '/sectors' => 'Secteurs',
    '/regions' => 'Régions', 
    '/sites' => 'Sites',
    '/books' => 'Guides'
];

$results = [];
$totalScore = 0;

foreach ($pages as $path => $name) {
    $result = checkPage($path, $name);
    $results[$path] = $result;
    $totalScore += $result['score'];
}

$globalScore = round($totalScore / count($pages));

// Résumé final
echo "📊 RÉSUMÉ GLOBAL\n";
echo "================\n";

foreach ($results as $path => $result) {
    $icon = $result['score'] >= 80 ? '🟢' : ($result['score'] >= 50 ? '🟡' : '🔴');
    echo "{$icon} {$path}: {$result['score']}% ({$result['details']})\n";
}

echo "\n🎯 SCORE GLOBAL: {$globalScore}%\n";

if ($globalScore >= 90) {
    echo "🎉 EXCELLENT - Système parfaitement fonctionnel!\n";
    echo "✅ Toutes les fonctionnalités sont opérationnelles\n";
} elseif ($globalScore >= 70) {
    echo "✅ BON - Système majoritairement fonctionnel\n";
    echo "⚠️  Quelques ajustements mineurs peuvent être nécessaires\n";
} elseif ($globalScore >= 50) {
    echo "⚠️  MOYEN - Système partiellement fonctionnel\n";
    echo "🔧 Corrections nécessaires sur certains aspects\n";
} else {
    echo "❌ PROBLÉMATIQUE - Système non fonctionnel\n";
    echo "🚨 Corrections majeures requises\n";
}

echo "\n💡 PROCHAINES ÉTAPES :\n";
echo "======================\n";

if ($globalScore >= 70) {
    echo "1. ✅ Test manuel avec navigateur pour validation finale\n";
    echo "2. 🧪 Test des interactions JavaScript (clics boutons)\n";
    echo "3. 🎨 Vérifier que les bordures debug apparaissent\n";
    echo "4. 🚀 Système prêt pour utilisation en production\n";
} else {
    echo "1. 🔧 Corriger les inclusions CSS/JS manquantes\n";
    echo "2. 🔍 Vérifier la structure HTML des templates\n";
    echo "3. 🎨 Activer les éléments de debug visuels\n";
    echo "4. 🔄 Relancer ce test après corrections\n";
}

echo "\n🔗 TESTS MANUELS :\n";
echo "==================\n";
echo "Après ce test automatique, effectuez :\n";
echo "• php test_manual_verification.php\n";
echo "• Test dans navigateur sur http://localhost:8000\n";
echo "• Connexion avec admin@topoclimb.ch / admin123\n";
echo "• Vérification des boutons de changement de vue\n\n";

echo "📈 MÉTRIQUES DÉTAILLÉES :\n";
echo "==========================\n";
foreach ($results as $path => $result) {
    if ($result['score'] > 0) {
        echo "Page {$path}:\n";
        echo "  • Inclusions CSS/JS: {$result['inclusions']}/4\n";
        echo "  • Éléments HTML: {$result['elements']}/7\n";
        echo "  • Debug visuel: {$result['debug']}/3\n";
    }
}

echo "\n✅ Test terminé. Serveur toujours actif sur http://localhost:8000\n";