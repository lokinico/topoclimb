<?php
/**
 * Test Rapide JavaScript - Vérification erreurs après corrections
 */

echo "🧪 TEST RAPIDE JAVASCRIPT APRÈS CORRECTIONS" . PHP_EOL;
echo "===========================================" . PHP_EOL . PHP_EOL;

$baseUrl = 'http://localhost:8000';

function testJSFile($path) {
    global $baseUrl;
    
    if (!file_exists("public$path")) {
        echo "  ❌ Fichier manquant: public$path" . PHP_EOL;
        return false;
    }
    
    // Test syntaxe basique
    $content = file_get_contents("public$path");
    $hasEscapedChars = strpos($content, '\\n') !== false;
    $size = filesize("public$path");
    
    echo "  📄 $path: " . round($size/1024, 1) . "KB";
    
    if ($hasEscapedChars) {
        echo " ⚠️ Caractères échappés détectés" . PHP_EOL;
        return false;
    } else {
        echo " ✅ Syntaxe OK" . PHP_EOL;
        return true;
    }
}

echo "🔧 VÉRIFICATION FICHIERS CORE JAVASCRIPT" . PHP_EOL;
echo "----------------------------------------" . PHP_EOL;

$jsFiles = [
    '/js/core/index.js',
    '/js/core/api.js', 
    '/js/core/ui.js',
    '/js/core/utils.js',
    '/js/topoclimb.js',
    '/js/components/weather-widget.js'
];

$jsOk = 0;
foreach ($jsFiles as $file) {
    if (testJSFile($file)) {
        $jsOk++;
    }
}

echo PHP_EOL;
echo "📊 Résultat: $jsOk/" . count($jsFiles) . " fichiers JavaScript OK" . PHP_EOL . PHP_EOL;

echo "🌐 TEST ACCÈS PAGES PRINCIPALES" . PHP_EOL;
echo "------------------------------" . PHP_EOL;

$pages = [
    '/' => 'Accueil',
    '/regions' => 'Régions', 
    '/sites' => 'Sites',
    '/sectors' => 'Secteurs'
];

$pageOk = 0;
foreach ($pages as $path => $name) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false
    ]);
    
    $content = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ $name: Erreur cURL - $error" . PHP_EOL;
    } elseif ($code === 200) {
        echo "  ✅ $name: OK ($code)" . PHP_EOL;
        $pageOk++;
        
        // Vérifier présence nonce dans le HTML
        if (strpos($content, 'nonce=') !== false) {
            echo "    🔐 CSP nonce présent" . PHP_EOL;
        } else {
            echo "    ⚠️ CSP nonce manquant" . PHP_EOL;
        }
    } else {
        echo "  ⚠️ $name: Code $code" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "📊 Résultat: $pageOk/" . count($pages) . " pages accessibles" . PHP_EOL . PHP_EOL;

echo "🚀 ACTIONS RECOMMANDÉES" . PHP_EOL;
echo "=====================" . PHP_EOL;

if ($jsOk >= count($jsFiles) && $pageOk >= count($pages)) {
    echo "✅ EXCELLENT! Toutes les corrections semblent fonctionner." . PHP_EOL;
    echo "   → Tester maintenant dans navigateur:" . PHP_EOL;
    echo "   → Ouvrir http://localhost:8000" . PHP_EOL;
    echo "   → Console dev (F12) pour vérifier aucune erreur JS" . PHP_EOL;
} elseif ($jsOk >= count($jsFiles) * 0.8 && $pageOk >= count($pages) * 0.8) {
    echo "👍 BON! La plupart des corrections fonctionnent." . PHP_EOL;
    echo "   → Quelques ajustements mineurs peuvent être nécessaires" . PHP_EOL;
} else {
    echo "⚠️ ATTENTION! Des corrections supplémentaires sont nécessaires." . PHP_EOL;
    
    if ($jsOk < count($jsFiles) * 0.8) {
        echo "   → Problèmes JavaScript à résoudre" . PHP_EOL;
    }
    
    if ($pageOk < count($pages) * 0.8) {
        echo "   → Problèmes d'accès aux pages" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "💡 Prochaine étape: Test complet navigateur web" . PHP_EOL;