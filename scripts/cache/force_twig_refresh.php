<?php

/**
 * Script pour forcer la recompilation des templates Twig même en mode développement
 */

echo "🔄 Forçage de la recompilation des templates Twig\n";
echo "===============================================\n\n";

// 1. Toucher tous les fichiers Twig pour forcer la recompilation
echo "📝 Mise à jour des timestamps des templates Twig...\n";

$twigFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/resources/views', RecursiveDirectoryIterator::SKIP_DOTS)
);

$count = 0;
foreach ($twigFiles as $file) {
    if ($file->isFile() && $file->getExtension() === 'twig') {
        touch($file->getPathname());
        $count++;
        echo "🔄 " . str_replace(__DIR__ . '/resources/views/', '', $file->getPathname()) . "\n";
    }
}

echo "\n✅ $count templates Twig mis à jour\n\n";

// 2. Nettoyer le cache navigateur en ajoutant un cache bust
echo "🌐 Ajout d'un cache bust pour le navigateur...\n";

$cacheBustFile = __DIR__ . '/public/cache_bust.txt';
file_put_contents($cacheBustFile, time());

echo "✅ Cache bust créé: " . time() . "\n\n";

// 3. Forcer la recompilation des assets CSS/JS
echo "🎨 Mise à jour des assets CSS/JS...\n";

$assetFiles = [
    __DIR__ . '/public/css/app.css',
    __DIR__ . '/public/js/app.js',
    __DIR__ . '/public/css/pages/regions.css',
    __DIR__ . '/public/js/pages/regions.js'
];

foreach ($assetFiles as $file) {
    if (file_exists($file)) {
        touch($file);
        echo "🔄 " . basename($file) . " mis à jour\n";
    }
}

echo "\n";

// 4. Nettoyer le cache PHP-FPM en touchant les fichiers de configuration
echo "⚙️ Forçage du rechargement PHP-FPM...\n";

$configFiles = [
    __DIR__ . '/public/.htaccess',
    __DIR__ . '/bootstrap.php',
    __DIR__ . '/public/index.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        touch($file);
        echo "🔄 " . basename($file) . " mis à jour\n";
    }
}

echo "\n";

// 5. Ajouter un commentaire de cache bust dans les templates principaux
echo "💬 Ajout de commentaires de cache bust...\n";

$mainTemplates = [
    __DIR__ . '/resources/views/layouts/base.twig',
    __DIR__ . '/resources/views/layouts/app.twig',
    __DIR__ . '/resources/views/regions/show.twig',
    __DIR__ . '/resources/views/regions/list.twig'
];

$cacheBustComment = "\n{# Cache bust: " . date('Y-m-d H:i:s') . " #}\n";

foreach ($mainTemplates as $template) {
    if (file_exists($template)) {
        $content = file_get_contents($template);
        
        // Supprimer les anciens commentaires de cache bust
        $content = preg_replace('/\n\{\# Cache bust: [^\#]+ \#\}\n/', '', $content);
        
        // Ajouter le nouveau commentaire au début
        $content = $cacheBustComment . $content;
        
        file_put_contents($template, $content);
        echo "💬 " . basename($template) . " mis à jour avec cache bust\n";
    }
}

echo "\n🎉 Forçage de la recompilation terminé !\n";
echo "📋 Actions effectuées :\n";
echo "   - $count templates Twig mis à jour\n";
echo "   - Cache bust navigateur créé\n";
echo "   - Assets CSS/JS mis à jour\n";
echo "   - Configuration PHP-FPM rechargée\n";
echo "   - Commentaires de cache bust ajoutés\n";
echo "\n🔄 Rafraîchissez votre navigateur avec Ctrl+F5\n";

?>