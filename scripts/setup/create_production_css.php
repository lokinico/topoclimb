<?php
/**
 * Créer version de production du CSS sans éléments de debug
 */

echo "🎨 NETTOYAGE CODE DEBUG POUR PRODUCTION\n";
echo "=======================================\n\n";

function removeDebugFromCSS($cssFile, $outputFile) {
    if (!file_exists($cssFile)) {
        echo "❌ Fichier non trouvé: {$cssFile}\n";
        return false;
    }
    
    $css = file_get_contents($cssFile);
    $originalLines = substr_count($css, "\n");
    
    // Supprimer les sections de debug visuel
    $debugSections = [
        '/\/\* DEBUG VISUEL.*?\*\/.*?(?=\/\*|$)/s',
        '/\.entities-container \.view-grid\.active::before.*?\}/s',
        '/\.entities-container \.view-list\.active::before.*?\}/s', 
        '/\.entities-container \.view-compact\.active::before.*?\}/s',
        '/content: "[^"]*VUE [^"]*ACTIVE[^"]*";[^}]*\}/s'
    ];
    
    foreach ($debugSections as $pattern) {
        $css = preg_replace($pattern, '', $css);
    }
    
    // Supprimer les bordures de debug
    $css = preg_replace('/border: 3px solid (green|blue|orange) !important;/', '', $css);
    $css = preg_replace('/background: rgba\([^)]+\) !important;/', '', $css);
    
    // Supprimer les commentaires de debug
    $css = preg_replace('/\/\* DEBUG.*?\*\//', '', $css);
    $css = preg_replace('/\/\* À SUPPRIMER EN PROD.*?\*\//', '', $css);
    
    // Nettoyer les lignes vides multiples
    $css = preg_replace('/\n\s*\n\s*\n/', "\n\n", $css);
    
    $cleanedLines = substr_count($css, "\n");
    $removedLines = $originalLines - $cleanedLines;
    
    file_put_contents($outputFile, $css);
    
    echo "✅ {$cssFile} → {$outputFile}\n";
    echo "   📊 {$originalLines} lignes → {$cleanedLines} lignes (-{$removedLines} debug)\n\n";
    
    return true;
}

function removeDebugFromJS($jsFile, $outputFile) {
    if (!file_exists($jsFile)) {
        echo "❌ Fichier non trouvé: {$jsFile}\n";
        return false;
    }
    
    $js = file_get_contents($jsFile);
    $originalSize = strlen($js);
    
    // Supprimer les console.log de debug
    $js = preg_replace('/console\.(log|warn|error|info)\([^;]*\);?/', '', $js);
    
    // Supprimer les commentaires de debug
    $js = preg_replace('/\/\/ DEBUG.*/', '', $js);
    $js = preg_replace('/\/\* DEBUG.*?\*\//', '', $js);
    
    // Supprimer les sections de test/debug
    $js = preg_replace('/\/\/ Test.*?\n/', '', $js);
    $js = preg_replace('/\/\/ À supprimer en prod.*?\n/', '', $js);
    
    // Nettoyer les lignes vides
    $js = preg_replace('/\n\s*\n/', "\n", $js);
    
    $cleanedSize = strlen($js);
    $reduction = round((($originalSize - $cleanedSize) / $originalSize) * 100, 1);
    
    file_put_contents($outputFile, $js);
    
    echo "✅ {$jsFile} → {$outputFile}\n";
    echo "   📊 {$originalSize} bytes → {$cleanedSize} bytes (-{$reduction}% debug)\n\n";
    
    return true;
}

// Nettoyer les fichiers CSS
echo "🎨 NETTOYAGE CSS DE PRODUCTION\n";
echo "===============================\n";

$cssFiles = [
    'public/css/view-modes.css' => 'public/css/view-modes.prod.css',
    'public/css/pages-common.css' => 'public/css/pages-common.prod.css'
];

foreach ($cssFiles as $original => $production) {
    removeDebugFromCSS($original, $production);
}

// Nettoyer les fichiers JavaScript
echo "🔧 NETTOYAGE JAVASCRIPT DE PRODUCTION\n";
echo "======================================\n";

$jsFiles = [
    'public/js/view-manager.js' => 'public/js/view-manager.prod.js',
    'public/js/pages-common.js' => 'public/js/pages-common.prod.js'
];

foreach ($jsFiles as $original => $production) {
    removeDebugFromJS($original, $production);
}

// Créer les versions minifiées des fichiers de production
echo "⚡ MINIFICATION DES VERSIONS PRODUCTION\n";
echo "=======================================\n";

function minifyCSS($css) {
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    '], '', $css);
    $css = str_replace(['{ ', ' }', ': ', '; ', ', '], ['{', '}', ':', ';', ','], $css);
    return trim($css);
}

function minifyJS($js) {
    $js = preg_replace('/\/\/.*$/m', '', $js);
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    $js = preg_replace('/\s+/', ' ', $js);
    return trim($js);
}

// Minifier les versions production
foreach ($cssFiles as $original => $production) {
    if (file_exists($production)) {
        $content = file_get_contents($production);
        $minified = minifyCSS($content);
        $minFile = str_replace('.prod.css', '.prod.min.css', $production);
        file_put_contents($minFile, $minified);
        
        $originalSize = strlen($content);
        $minifiedSize = strlen($minified);
        $reduction = round((($originalSize - $minifiedSize) / $originalSize) * 100, 1);
        
        echo "✅ {$production} → {$minFile}\n";
        echo "   📊 {$originalSize} bytes → {$minifiedSize} bytes (-{$reduction}%)\n\n";
    }
}

foreach ($jsFiles as $original => $production) {
    if (file_exists($production)) {
        $content = file_get_contents($production);
        $minified = minifyJS($content);
        $minFile = str_replace('.prod.js', '.prod.min.js', $production);
        file_put_contents($minFile, $minified);
        
        $originalSize = strlen($content);
        $minifiedSize = strlen($minified);
        $reduction = round((($originalSize - $minifiedSize) / $originalSize) * 100, 1);
        
        echo "✅ {$production} → {$minFile}\n";
        echo "   📊 {$originalSize} bytes → {$minifiedSize} bytes (-{$reduction}%)\n\n";
    }
}

// Créer layout de production sans debug
echo "🏗️ LAYOUT PRODUCTION SANS DEBUG\n";
echo "=================================\n";

$layoutContent = file_get_contents('resources/views/layouts/app.twig');

if ($layoutContent) {
    // Remplacer par les versions production minifiées
    $productionLayout = str_replace([
        'css/view-modes.css',
        'css/pages-common.css',
        'js/view-manager.js', 
        'js/pages-common.js'
    ], [
        'css/view-modes.prod.min.css',
        'css/pages-common.prod.min.css',
        'js/view-manager.prod.min.js',
        'js/pages-common.prod.min.js'
    ], $layoutContent);
    
    file_put_contents('resources/views/layouts/app.production.twig', $productionLayout);
    echo "✅ Layout production sans debug: app.production.twig\n\n";
}

// Générer rapport des versions
echo "📊 RAPPORT VERSIONS DISPONIBLES\n";
echo "================================\n";
echo "🧪 **Développement (avec debug)**:\n";
echo "   • app.twig (layout de base)\n";
echo "   • *.css et *.js (versions complètes avec debug)\n\n";

echo "⚡ **Production (optimisée)**:\n";
echo "   • app.production.twig (layout production)\n";
echo "   • *.prod.min.css et *.prod.min.js (optimisées sans debug)\n\n";

echo "📋 **Instructions d'utilisation**:\n";
echo "===================================\n";
echo "1. **En développement**: Utilisez les fichiers normaux (avec debug)\n";
echo "2. **En production**: Utilisez app.production.twig comme layout\n";
echo "3. **Variables d'environnement**: Configurez ENVIRONMENT=production\n";
echo "4. **Serveur web**: Activez compression sur *.prod.min.* \n\n";

echo "✅ Nettoyage terminé!\n";
echo "🎯 Versions production prêtes sans éléments de debug\n";
echo "📦 Tailles optimisées pour performance maximale\n";