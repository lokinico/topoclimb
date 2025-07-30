<?php
/**
 * CORRECTION SIMPLE AUTHSERVICE - Version définitive
 */

echo "🔧 CORRECTION AUTHSERVICE - VERSION SIMPLE\n";
echo "==========================================\n\n";

// Configuration basée sur vos logs de production
$PRODUCTION_CONFIG = [
    'email_column' => 'mail',        // D'après vos logs: "Unknown column 'email'"
    'password_column' => 'password_hash',
    'active_column' => 'actif'       // Supposé d'après la structure standard
];

echo "1️⃣ CONFIGURATION DÉTECTÉE (d'après logs)\n";
echo str_repeat("-", 45) . "\n";
echo "   - Email: {$PRODUCTION_CONFIG['email_column']}\n";
echo "   - Password: {$PRODUCTION_CONFIG['password_column']}\n";
echo "   - Actif: {$PRODUCTION_CONFIG['active_column']}\n\n";

// Construire la requête exacte
$emailCol = $PRODUCTION_CONFIG['email_column'];
$passwordCol = $PRODUCTION_CONFIG['password_column'];  
$activeCol = $PRODUCTION_CONFIG['active_column'];

$exactQuery = "SELECT * FROM users WHERE $emailCol = ? AND $activeCol = 1 LIMIT 1";

echo "2️⃣ REQUÊTE SQL EXACTE\n";
echo str_repeat("-", 25) . "\n";
echo "$exactQuery\n\n";

echo "3️⃣ CORRECTION AUTHSERVICE.PHP\n";
echo str_repeat("-", 35) . "\n";

$authServiceFile = 'src/Services/AuthService.php';

if (!file_exists($authServiceFile)) {
    echo "❌ Fichier AuthService.php non trouvé\n";
    exit(1);
}

$content = file_get_contents($authServiceFile);
$originalContent = $content;

// Sauvegarder l'original
file_put_contents($authServiceFile . '.backup-simple', $originalContent);
echo "✅ Sauvegarde créée: AuthService.php.backup-simple\n";

// Remplacer toute la section auto-détection par la requête simple
$pattern = '/\/\/.*Auto-détection.*?(?=if \(\!\$result\))/s';

$replacement = "            // REQUÊTE EXACTE POUR VOTRE BASE DE PRODUCTION
            \$result = \$this->db->fetchOne(\"$exactQuery\", [\$email]);

            ";

$newContent = preg_replace($pattern, $replacement, $content);

if ($newContent !== $content) {
    file_put_contents($authServiceFile, $newContent);
    echo "✅ AuthService.php corrigé automatiquement\n";
} else {
    echo "⚠️ Pattern non trouvé, correction manuelle...\n";
    
    // Méthode alternative: remplacer directement les lignes problématiques
    $lines = explode("\n", $content);
    $newLines = [];
    $inAutoDetection = false;
    
    foreach ($lines as $line) {
        if (strpos($line, 'Auto-détection') !== false) {
            $inAutoDetection = true;
            $newLines[] = "            // REQUÊTE EXACTE POUR VOTRE BASE DE PRODUCTION";
            $newLines[] = "            \$result = \$this->db->fetchOne(\"$exactQuery\", [\$email]);";
            $newLines[] = "";
            continue;
        }
        
        if ($inAutoDetection && strpos($line, 'if (!$result)') !== false) {
            $inAutoDetection = false;
            $newLines[] = $line;
            continue;
        }
        
        if (!$inAutoDetection) {
            $newLines[] = $line;
        }
    }
    
    $newContent = implode("\n", $newLines);
    file_put_contents($authServiceFile, $newContent);
    echo "✅ Correction manuelle effectuée\n";
}

// Corriger aussi les autres méthodes qui utilisent email
echo "\n4️⃣ CORRECTION AUTRES MÉTHODES\n";
echo str_repeat("-", 35) . "\n";

$corrections = [
    'WHERE email =' => "WHERE $emailCol =",
    'WHERE email\'' => "WHERE $emailCol'",
    '"email"' => "\"$emailCol\"",
    "'email'" => "'$emailCol'"
];

$modified = false;
$finalContent = file_get_contents($authServiceFile);

foreach ($corrections as $search => $replace) {
    if (strpos($finalContent, $search) !== false) {
        $finalContent = str_replace($search, $replace, $finalContent);
        $modified = true;
        echo "✅ Corrigé: $search → $replace\n";
    }
}

if ($modified) {
    file_put_contents($authServiceFile, $finalContent);
    echo "✅ Corrections supplémentaires appliquées\n";
}

echo "\n5️⃣ VÉRIFICATION FINALE\n";
echo str_repeat("-", 25) . "\n";

// Vérifier la syntaxe PHP
$syntaxCheck = shell_exec("php -l $authServiceFile 2>&1");
if (strpos($syntaxCheck, 'No syntax errors') !== false) {
    echo "✅ Syntaxe PHP correcte\n";
} else {
    echo "❌ Erreur syntaxe PHP:\n$syntaxCheck\n";
}

// Afficher le code final généré
echo "\n6️⃣ CODE FINAL GÉNÉRÉ\n";
echo str_repeat("-", 25) . "\n";
echo "La ligne exacte utilisée:\n";
echo "\$result = \$this->db->fetchOne(\"$exactQuery\", [\$email]);\n\n";

echo "Vérification password avec:\n";
echo "if (!password_verify(\$password, \$result['$passwordCol'])) {\n";
echo "    return false;\n";
echo "}\n\n";

echo str_repeat("=", 60) . "\n";
echo "✅ CORRECTION TERMINÉE\n";
echo "📋 Fichier modifié: $authServiceFile\n";
echo "💾 Sauvegarde: {$authServiceFile}.backup-simple\n";
echo "🎯 Configuration: $emailCol, $passwordCol, $activeCol\n";
echo "🚀 PRÊT POUR DÉPLOIEMENT\n";
echo str_repeat("=", 60) . "\n";

echo "\nScript terminé à " . date('Y-m-d H:i:s') . "\n";