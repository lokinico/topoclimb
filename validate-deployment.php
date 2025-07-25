<?php
// Script de validation pré-déploiement
echo "🔍 VALIDATION PRÉ-DÉPLOIEMENT ViewManager\n\n";

$errors = [];
$warnings = [];

// 1. Vérifier que tous les templates ont la structure requise
$templates = [
    'resources/views/books/index.twig',
    'resources/views/regions/index.twig', 
    'resources/views/sites/index.twig',
    'resources/views/sectors/index.twig',
    'resources/views/routes/index.twig'
];

foreach ($templates as $template) {
    echo "📄 Vérification $template...\n";
    
    if (!file_exists($template)) {
        $errors[] = "❌ Template manquant: $template";
        continue;
    }
    
    $content = file_get_contents($template);
    
    // Vérifier présence des 3 vues
    $hasGrid = strpos($content, 'view-grid') !== false;
    $hasList = strpos($content, 'view-list') !== false;
    $hasCompact = strpos($content, 'view-compact') !== false;
    
    if (!$hasGrid || !$hasList || !$hasCompact) {
        $errors[] = "❌ Structure incomplète dans $template (grid:$hasGrid, list:$hasList, compact:$hasCompact)";
    } else {
        echo "   ✅ 3 vues présentes\n";
    }
    
    // Vérifier les boutons
    $hasButtons = strpos($content, 'data-view="grid"') !== false && 
                  strpos($content, 'data-view="list"') !== false && 
                  strpos($content, 'data-view="compact"') !== false;
    
    if (!$hasButtons) {
        $errors[] = "❌ Boutons ViewManager manquants dans $template";
    } else {
        echo "   ✅ Boutons ViewManager présents\n";
    }
    
    // Vérifier les classes CSS
    $hasContainer = strpos($content, 'entities-container') !== false;
    if (!$hasContainer) {
        $errors[] = "❌ Classe entities-container manquante dans $template";
    } else {
        echo "   ✅ Container CSS présent\n";
    }
}

// 2. Vérifier les ressources CSS/JS
echo "\n📦 Vérification des ressources...\n";

$resources = [
    'public/css/view-modes.css',
    'public/js/view-manager.js'
];

foreach ($resources as $resource) {
    if (!file_exists($resource)) {
        $errors[] = "❌ Ressource manquante: $resource";
    } else {
        echo "   ✅ $resource présent\n";
    }
}

// 3. Vérifier le contenu des ressources
if (file_exists('public/css/view-modes.css')) {
    $css = file_get_contents('public/css/view-modes.css');
    
    if (strpos($css, '.entities-container .view-grid.active') === false) {
        $warnings[] = "⚠️  CSS view-modes.css pourrait avoir un problème de spécificité";
    } else {
        echo "   ✅ CSS spécificité correcte\n";
    }
}

if (file_exists('public/js/view-manager.js')) {
    $js = file_get_contents('public/js/view-manager.js');
    
    if (strpos($js, 'detectInitialView') === false) {
        $warnings[] = "⚠️  JavaScript ViewManager pourrait manquer detectInitialView()";
    } else {
        echo "   ✅ JavaScript detectInitialView présent\n";
    }
}

// 4. Vérifier la configuration serveur
echo "\n🔧 Vérification configuration...\n";

if (file_exists('public/.htaccess')) {
    echo "   ✅ .htaccess présent\n";
} else {
    $warnings[] = "⚠️  .htaccess manquant dans public/";
}

// Résumé
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RÉSUMÉ DE VALIDATION\n";
echo str_repeat("=", 50) . "\n";

if (empty($errors)) {
    echo "✅ AUCUNE ERREUR CRITIQUE\n";
} else {
    echo "❌ ERREURS CRITIQUES (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
}

if (empty($warnings)) {
    echo "✅ AUCUN AVERTISSEMENT\n";
} else {
    echo "⚠️  AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
}

echo "\n";

if (empty($errors)) {
    echo "🚀 PRÊT POUR LE DÉPLOIEMENT !\n";
    echo "\n📋 Instructions déploiement:\n";
    echo "1. Faire git push origin main\n";
    echo "2. Sur le serveur: git pull origin main\n"; 
    echo "3. Vider le cache: rm -rf cache/* (si cache présent)\n";
    echo "4. Tester les URLs: /books, /regions, /sites, /sectors, /routes\n";
    echo "5. Vérifier que les boutons Cartes/Liste/Compact fonctionnent\n";
    exit(0);
} else {
    echo "❌ CORRIGER LES ERREURS AVANT DÉPLOIEMENT\n";
    exit(1);
}
?>