<?php
header('Content-Type: text/plain');

echo "=== VÉRIFICATION TEMPLATE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$template_path = __DIR__ . '/resources/views/regions/index.twig';

if (file_exists($template_path)) {
    $content = file_get_contents($template_path);
    $lines = substr_count($content, "\n");
    $size = filesize($template_path);
    $modified = date('Y-m-d H:i:s', filemtime($template_path));
    
    echo "✅ Template trouvé\n";
    echo "Taille: $size bytes\n";
    echo "Lignes: $lines\n";
    echo "Modifié: $modified\n\n";
    
    // Vérifier si c'est le nouveau template
    if (strpos($content, 'regions-page') !== false) {
        echo "✅ Nouveau template simplifié détecté\n";
    } elseif (strpos($content, 'regions-page-modern') !== false) {
        echo "❌ Ancien template complexe détecté\n";
    } else {
        echo "⚠️ Template non identifié\n";
    }
    
    // Vérifier les éléments clés
    if (strpos($content, 'hero-modern') !== false) {
        echo "❌ Hero section complexe présente\n";
    } else {
        echo "✅ Pas de hero section complexe\n";
    }
    
    if (strpos($content, 'page-header') !== false) {
        echo "✅ En-tête simple présent\n";
    } else {
        echo "❌ En-tête simple manquant\n";
    }
    
    if (strpos($content, 'filters-section') !== false) {
        echo "✅ Section filtres présente\n";
    } else {
        echo "❌ Section filtres manquante\n";
    }
    
    // Afficher les premières lignes
    echo "\n=== PREMIÈRES LIGNES ===\n";
    $first_lines = explode("\n", $content);
    for ($i = 0; $i < min(20, count($first_lines)); $i++) {
        echo ($i + 1) . ": " . $first_lines[$i] . "\n";
    }
    
} else {
    echo "❌ Template non trouvé: $template_path\n";
}

echo "\n=== FIN VÉRIFICATION ===\n";
?>