<?php
/**
 * Script pour corriger les attributs autocomplete dans tous les formulaires
 */

echo "🔧 Correction des formulaires - Autocomplete\n";
echo "============================================\n";

$formsToFix = [
    // Formulaires critiques
    'resources/views/auth/forgot-password.twig' => [
        'email' => 'email'
    ],
    'resources/views/auth/reset-password.twig' => [
        'password' => 'new-password',
        'password_confirmation' => 'new-password'
    ],
    'resources/views/users/settings.twig' => [
        'prenom' => 'given-name',
        'nom' => 'family-name',
        'email' => 'email',
        'ville' => 'address-level2'
    ],
    'resources/views/regions/form.twig' => [
        'form' => 'on',
        'name' => 'off',
        'code' => 'off'
    ],
    'resources/views/sites/form.twig' => [
        'form' => 'on',
        'name' => 'off',
        'code' => 'off'
    ],
    'resources/views/routes/form.twig' => [
        'form' => 'on',
        'name' => 'off'
    ]
];

$fixed = 0;
$errors = 0;

foreach ($formsToFix as $file => $autocompletes) {
    $fullPath = __DIR__ . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠️  $file n'existe pas\n";
        continue;
    }
    
    echo "🔄 Traitement de $file...\n";
    
    $content = file_get_contents($fullPath);
    $originalContent = $content;
    
    foreach ($autocompletes as $field => $autocomplete) {
        if ($field === 'form') {
            // Ajouter autocomplete au formulaire
            if (strpos($content, 'autocomplete=') === false) {
                $content = preg_replace(
                    '/(<form[^>]*)(>)/',
                    '$1 autocomplete="' . $autocomplete . '"$2',
                    $content
                );
            }
        } else {
            // Ajouter autocomplete aux champs spécifiques
            $patterns = [
                // Pattern pour input avec id
                '/(<input[^>]*id="' . $field . '"[^>]*)(>)/',
                // Pattern pour input avec name
                '/(<input[^>]*name="' . $field . '"[^>]*)(>)/'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content) && strpos($content, 'autocomplete="') === false) {
                    $content = preg_replace(
                        $pattern,
                        '$1 autocomplete="' . $autocomplete . '"$2',
                        $content
                    );
                    break;
                }
            }
        }
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($fullPath, $content)) {
            echo "✅ $file corrigé\n";
            $fixed++;
        } else {
            echo "❌ Erreur d'écriture pour $file\n";
            $errors++;
        }
    } else {
        echo "ℹ️  $file déjà correct\n";
    }
}

echo "\n📊 RÉSUMÉ:\n";
echo "✅ Fichiers corrigés: $fixed\n";
echo "❌ Erreurs: $errors\n";

// Vérification supplémentaire des tokens CSRF
echo "\n🔒 Vérification des tokens CSRF...\n";

$files = glob(__DIR__ . '/resources/views/**/*.twig');
$missingCsrf = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Si le fichier contient un formulaire POST
    if (preg_match('/<form[^>]*method=["\']?post["\']?/i', $content)) {
        // Vérifier la présence du token CSRF
        if (strpos($content, 'csrf_token') === false) {
            $missingCsrf[] = str_replace(__DIR__ . '/', '', $file);
        }
    }
}

if (empty($missingCsrf)) {
    echo "✅ Tous les formulaires POST ont un token CSRF\n";
} else {
    echo "⚠️  Formulaires sans token CSRF:\n";
    foreach ($missingCsrf as $file) {
        echo "   - $file\n";
    }
}

echo "\n🎉 Correction terminée!\n";