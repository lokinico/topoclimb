<?php
/**
 * CORRECTION URGENTE AUTHENTIFICATION PRODUCTION
 * Corrige le problème 'Unknown column email' en remplaçant par 'mail'
 */

echo "🚨 CORRECTION URGENTE PRODUCTION\n";
echo "=================================\n\n";

$files = [
    'src/Services/AuthService.php',
    'src/Core/Auth.php'
];

$corrections = [
    "WHERE email =" => "WHERE mail =",
    "WHERE email'" => "WHERE mail'",
    "email = ?" => "mail = ?",
    "'email'" => "'mail'",
    "\"email\"" => "\"mail\""
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "❌ Fichier non trouvé: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $modified = false;
    
    foreach ($corrections as $search => $replace) {
        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            $modified = true;
            echo "✅ $file: $search → $replace\n";
        }
    }
    
    if ($modified) {
        // Sauvegarde
        file_put_contents($file . '.backup-' . date('Y-m-d-H-i-s'), $originalContent);
        file_put_contents($file, $content);
        echo "✅ $file mis à jour\n\n";
    } else {
        echo "✅ $file: aucune modification nécessaire\n\n";
    }
}

echo "🎉 CORRECTION TERMINÉE!\n";
echo "Tentez maintenant la connexion avec:\n";
echo "- Login: nicolas.baechler@outlook.com (ou tout autre email)\n";
echo "- Password: [votre mot de passe]\n";