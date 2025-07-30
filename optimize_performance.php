<?php
/**
 * Script d'optimisation des performances pour TopoclimbCH
 * Minification CSS/JS et optimisation cache
 */

echo "⚡ OPTIMISATION PERFORMANCE TOPOCLIMB\n";
echo "====================================\n\n";

/**
 * Minifier le CSS
 */
function minifyCSS($css) {
    // Supprimer commentaires
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    
    // Supprimer espaces inutiles
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
    
    // Supprimer espaces autour des caractères spéciaux
    $css = str_replace(['{ ', ' }', ': ', '; ', ', '], ['{', '}', ':', ';', ','], $css);
    
    return trim($css);
}

/**
 * Minifier le JavaScript
 */
function minifyJS($js) {
    // Supprimer commentaires de type //
    $js = preg_replace('/\/\/.*$/m', '', $js);
    
    // Supprimer commentaires de type /* */
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    
    // Supprimer espaces multiples
    $js = preg_replace('/\s+/', ' ', $js);
    
    // Supprimer espaces autour des opérateurs
    $js = str_replace([' = ', ' == ', ' === ', ' != ', ' !== ', ' && ', ' || '], 
                     ['=', '==', '===', '!=', '!==', '&&', '||'], $js);
    
    return trim($js);
}

/**
 * Créer version minifiée d'un fichier
 */
function createMinifiedVersion($originalFile, $minifiedFile, $type) {
    if (!file_exists($originalFile)) {
        echo "❌ Fichier non trouvé: {$originalFile}\n";
        return false;
    }
    
    $content = file_get_contents($originalFile);
    $originalSize = strlen($content);
    
    if ($type === 'css') {
        $minified = minifyCSS($content);
    } elseif ($type === 'js') {
        $minified = minifyJS($content);
    } else {
        echo "❌ Type non supporté: {$type}\n";
        return false;
    }
    
    $minifiedSize = strlen($minified);
    $reduction = round((($originalSize - $minifiedSize) / $originalSize) * 100, 1);
    
    file_put_contents($minifiedFile, $minified);
    
    echo "✅ {$originalFile}\n";
    echo "   → {$minifiedFile}\n";
    echo "   📊 {$originalSize} bytes → {$minifiedSize} bytes (-{$reduction}%)\n\n";
    
    return true;
}

// Minification des fichiers système de vues
echo "🎨 MINIFICATION CSS\n";
echo "==================\n";

$cssFiles = [
    'public/css/view-modes.css' => 'public/css/view-modes.min.css',
    'public/css/pages-common.css' => 'public/css/pages-common.min.css',
    'public/css/app.css' => 'public/css/app.min.css'
];

foreach ($cssFiles as $original => $minified) {
    createMinifiedVersion($original, $minified, 'css');
}

echo "🔧 MINIFICATION JAVASCRIPT\n";
echo "==========================\n";

$jsFiles = [
    'public/js/view-manager.js' => 'public/js/view-manager.min.js',
    'public/js/pages-common.js' => 'public/js/pages-common.min.js',
    'public/js/topoclimb.js' => 'public/js/topoclimb.min.js'
];

foreach ($jsFiles as $original => $minified) {
    createMinifiedVersion($original, $minified, 'js');
}

// Créer version de production du layout
echo "🏗️ CRÉATION LAYOUT PRODUCTION\n";
echo "==============================\n";

$layoutContent = file_get_contents('resources/views/layouts/app.twig');

if ($layoutContent) {
    // Remplacer les inclusions par les versions minifiées
    $productionLayout = str_replace([
        'css/view-modes.css',
        'css/pages-common.css', 
        'css/app.css',
        'js/view-manager.js',
        'js/pages-common.js',
        'js/topoclimb.js'
    ], [
        'css/view-modes.min.css',
        'css/pages-common.min.css',
        'css/app.min.css', 
        'js/view-manager.min.js',
        'js/pages-common.min.js',
        'js/topoclimb.min.js'
    ], $layoutContent);
    
    file_put_contents('resources/views/layouts/app.prod.twig', $productionLayout);
    echo "✅ Layout production créé: app.prod.twig\n\n";
}

// Génération cache manifest
echo "📋 GÉNÉRATION CACHE MANIFEST\n";
echo "=============================\n";

$cacheManifest = [
    'version' => date('Y-m-d-H-i-s'),
    'css' => [],
    'js' => []
];

foreach ($cssFiles as $original => $minified) {
    if (file_exists($minified)) {
        $cacheManifest['css'][] = [
            'file' => $minified,
            'size' => filesize($minified),
            'hash' => md5_file($minified)
        ];
    }
}

foreach ($jsFiles as $original => $minified) {
    if (file_exists($minified)) {
        $cacheManifest['js'][] = [
            'file' => $minified,
            'size' => filesize($minified), 
            'hash' => md5_file($minified)
        ];
    }
}

file_put_contents('public/cache-manifest.json', json_encode($cacheManifest, JSON_PRETTY_PRINT));
echo "✅ Cache manifest créé: cache-manifest.json\n\n";

// Instructions d'utilisation
echo "📋 INSTRUCTIONS PRODUCTION\n";
echo "===========================\n";
echo "1. Utilisez app.prod.twig au lieu de app.twig\n";
echo "2. Configurez le serveur web pour:\n";
echo "   • Gzip/Brotli sur les fichiers .min.css et .min.js\n";
echo "   • Cache headers longs (1 an) sur les assets\n";
echo "   • ETags basés sur les hash du manifest\n\n";

echo "🔧 CONFIGURATION NGINX RECOMMANDÉE:\n";
echo "====================================\n";
echo "location ~* \\.min\\.(css|js)$ {\n";
echo "    expires 1y;\n";
echo "    add_header Cache-Control \"public, immutable\";\n";
echo "    gzip_static on;\n";
echo "}\n\n";

echo "🔧 CONFIGURATION APACHE RECOMMANDÉE:\n";
echo "=====================================\n";
echo "<FilesMatch \"\\.(min\\.css|min\\.js)$\">\n";
echo "    ExpiresActive On\n";
echo "    ExpiresDefault \"access plus 1 year\"\n";
echo "    Header set Cache-Control \"public, immutable\"\n";
echo "</FilesMatch>\n\n";

echo "✅ Optimisation terminée!\n";
echo "📊 Vérifiez les tailles de fichiers réduites\n";
echo "🚀 Prêt pour déploiement en production\n";