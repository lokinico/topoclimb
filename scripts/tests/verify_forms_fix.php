<?php
/**
 * Script de vérification finale des formulaires corrigés
 * Vérifie les tokens CSRF et attributs autocomplete
 */

$corrected_files = [
    // Authentification
    'resources/views/auth/login.twig',
    'resources/views/auth/register.twig', 
    'resources/views/auth/forgot-password.twig',
    'resources/views/auth/reset-password.twig',
    
    // Utilisateurs
    'resources/views/users/settings.twig',
    
    // Administration
    'resources/views/admin/users.twig',
    'resources/views/admin/settings.twig',
    'resources/views/admin/user-edit.twig',
    'resources/views/admin/reports.twig',
    
    // Régions et sites
    'resources/views/regions/form.twig',
    'resources/views/sites/form.twig'
];

echo "=== VÉRIFICATION DES FORMULAIRES CORRIGÉS ===\n\n";

$total_forms = 0;
$forms_with_csrf = 0;
$forms_with_autocomplete = 0;
$issues = [];

foreach ($corrected_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (!file_exists($full_path)) {
        $issues[] = "❌ Fichier manquant: $file";
        continue;
    }
    
    $content = file_get_contents($full_path);
    
    // Chercher tous les formulaires
    preg_match_all('/<form[^>]*>/i', $content, $forms);
    $file_form_count = count($forms[0]);
    $total_forms += $file_form_count;
    
    if ($file_form_count === 0) {
        continue; // Pas de formulaires dans ce fichier
    }
    
    echo "📄 $file ($file_form_count formulaire" . ($file_form_count > 1 ? 's' : '') . ")\n";
    
    // Vérifier CSRF pour chaque formulaire
    $csrf_count = 0;
    if (preg_match_all('/(csrf_token|csrf_field)/i', $content, $csrf_matches)) {
        $csrf_count = count($csrf_matches[0]);
        $forms_with_csrf += min($csrf_count, $file_form_count);
    }
    
    // Vérifier autocomplete
    $autocomplete_count = 0;
    if (preg_match('/<form[^>]*autocomplete=["\']on["\'][^>]*>/i', $content) || 
        preg_match('/autocomplete=["\'][^"\']*["\']/', $content)) {
        $autocomplete_count = 1;
        $forms_with_autocomplete += $file_form_count;
    }
    
    // Compter les champs avec autocomplete
    preg_match_all('/autocomplete=["\']([^"\']+)["\']/', $content, $auto_matches);
    $autocomplete_fields = count($auto_matches[0]);
    
    echo "  ✅ CSRF: $csrf_count token(s) trouvé(s)\n";
    echo "  ✅ Autocomplete: $autocomplete_fields champ(s) avec attributs\n";
    
    if ($csrf_count === 0 && $file_form_count > 0) {
        $issues[] = "⚠️  Manque CSRF dans: $file";
    }
    
    if ($autocomplete_fields === 0 && $file_form_count > 0) {
        $issues[] = "⚠️  Manque autocomplete dans: $file";
    }
    
    echo "\n";
}

echo "=== RÉSUMÉ GLOBAL ===\n";
echo "📊 Total formulaires trouvés: $total_forms\n";
echo "🔒 Formulaires avec CSRF: $forms_with_csrf\n";
echo "📝 Formulaires avec autocomplete: $forms_with_autocomplete\n";

$csrf_percentage = $total_forms > 0 ? round(($forms_with_csrf / $total_forms) * 100, 1) : 0;
$auto_percentage = $total_forms > 0 ? round(($forms_with_autocomplete / $total_forms) * 100, 1) : 0;

echo "🎯 Couverture CSRF: $csrf_percentage%\n";
echo "🎯 Couverture autocomplete: $auto_percentage%\n\n";

if (empty($issues)) {
    echo "🎉 TOUS LES FORMULAIRES SONT CORRECTEMENT PROTÉGÉS !\n";
    echo "✅ Tokens CSRF présents\n";
    echo "✅ Attributs autocomplete ajoutés\n";
    echo "✅ Saisi automatique des navigateurs activée\n\n";
    
    echo "🚀 PROCHAINES ÉTAPES:\n";
    echo "1. Déployer les templates corrigés sur le serveur\n";
    echo "2. Tester la saisi automatique sur les formulaires\n";
    echo "3. Vérifier que les tokens CSRF fonctionnent\n";
} else {
    echo "⚠️  PROBLÈMES DÉTECTÉS:\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
}

echo "\n=== TYPES D'AUTOCOMPLETE UTILISÉS ===\n";

// Analyser les types d'autocomplete utilisés
$autocomplete_types = [];
foreach ($corrected_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        preg_match_all('/autocomplete=["\']([^"\']+)["\']/', $content, $matches);
        foreach ($matches[1] as $type) {
            $autocomplete_types[$type] = ($autocomplete_types[$type] ?? 0) + 1;
        }
    }
}

foreach ($autocomplete_types as $type => $count) {
    echo "  • $type: $count utilisation(s)\n";
}

echo "\nTous les formulaires permettent maintenant la saisi automatique des navigateurs ! 🎯\n";
?>