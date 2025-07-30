<?php
/**
 * Debug de l'erreur "Array to string conversion" dans Twig
 */

echo "🔍 DEBUG ERREUR TWIG - Array to string conversion\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Analyser les logs récents
echo "1️⃣ Recherche erreurs récentes...\n";

$logFiles = glob('/home/nibaechl/topoclimb/storage/logs/*.log');
if (empty($logFiles)) {
    echo "⚠️ Aucun fichier de log trouvé\n";
} else {
    echo "📁 Fichiers de log trouvés: " . count($logFiles) . "\n";
    
    foreach ($logFiles as $logFile) {
        echo "  - " . basename($logFile) . "\n";
        
        // Chercher les erreurs "Array to string"
        $content = file_get_contents($logFile);
        if (strpos($content, 'Array to string') !== false) {
            echo "    🚨 Erreur Array to string trouvée !\n";
            
            // Extraire les lignes avec l'erreur
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                if (strpos($line, 'Array to string') !== false) {
                    echo "    Ligne " . ($lineNum + 1) . ": " . trim($line) . "\n";
                    
                    // Afficher quelques lignes de contexte
                    for ($i = max(0, $lineNum - 2); $i <= min(count($lines) - 1, $lineNum + 2); $i++) {
                        if ($i !== $lineNum && !empty(trim($lines[$i]))) {
                            echo "      [" . ($i + 1) . "] " . trim($lines[$i]) . "\n";
                        }
                    }
                    echo "\n";
                }
            }
        }
    }
}

echo "\n2️⃣ Causes possibles de l'erreur...\n";
echo "┌─────────────────────────────────────────────────────────┐\n";
echo "│ CAUSES POSSIBLES ARRAY TO STRING CONVERSION            │\n";
echo "├─────────────────────────────────────────────────────────┤\n";
echo "│ 1. Variable array passée à echo/print dans template    │\n";
echo "│ 2. Méthode flash() recevant un array au lieu de string │\n";
echo "│ 3. Variable Twig mal formatée (ex: {{ var }} au lieu   │\n";
echo "│    de {{ var|join(',') }} pour un array)               │\n";
echo "│ 4. Données de session contenant des arrays             │\n";
echo "│ 5. CSRF token mal formaté                              │\n";
echo "└─────────────────────────────────────────────────────────┘\n";

echo "\n3️⃣ Vérification des templates Twig récents...\n";

$twigFiles = [
    '/home/nibaechl/topoclimb/resources/views/auth/login.twig',
    '/home/nibaechl/topoclimb/resources/views/layout/base.twig',
    '/home/nibaechl/topoclimb/resources/views/layout/main.twig'
];

foreach ($twigFiles as $twigFile) {
    if (file_exists($twigFile)) {
        echo "📄 Vérification: " . basename($twigFile) . "\n";
        $content = file_get_contents($twigFile);
        
        // Chercher des patterns problématiques
        $problematicPatterns = [
            '/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/' => 'Variables simples',
            '/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/' => 'Propriétés d\'objets',
            '/flash\.[a-zA-Z_][a-zA-Z0-9_]*/' => 'Messages flash',
            '/csrf_token/' => 'CSRF tokens'
        ];
        
        foreach ($problematicPatterns as $pattern => $description) {
            if (preg_match_all($pattern, $content, $matches)) {
                echo "  ✓ $description: " . count($matches[0]) . " trouvé(s)\n";
                if ($description === 'Messages flash' || $description === 'CSRF tokens') {
                    foreach (array_unique($matches[0]) as $match) {
                        echo "    - $match\n";
                    }
                }
            }
        }
    } else {
        echo "❌ Fichier non trouvé: " . basename($twigFile) . "\n";
    }
}

echo "\n4️⃣ Solution temporaire...\n";
echo "┌─────────────────────────────────────────────────────────┐\n";
echo "│ SOLUTIONS POUR CORRIGER L'ERREUR                       │\n";
echo "├─────────────────────────────────────────────────────────┤\n";
echo "│ 1. Vérifier AuthController::flash() - ne pas passer    │\n";
echo "│    d'arrays                                             │\n";
echo "│ 2. Dans templates Twig:                                │\n";
echo "│    - {{ array|join(',') }} pour afficher un array     │\n";
echo "│    - {% if array is iterable %} avant d'utiliser      │\n";
echo "│ 3. Vérifier les variables de session                   │\n";
echo "│ 4. Activer le debug Twig pour plus d'infos             │\n";
echo "└─────────────────────────────────────────────────────────┘\n";

echo "\n5️⃣ Test de connexion simple...\n";

// Test basique de connexion
try {
    $db = new PDO('sqlite:climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $testUser = $db->query("SELECT * FROM users WHERE mail = 'admin@test.ch'")->fetch(PDO::FETCH_ASSOC);
    
    if ($testUser) {
        echo "✅ Utilisateur test trouvé: admin@test.ch\n";
        echo "   - ID: {$testUser['id']}\n";
        echo "   - Autorisation: {$testUser['autorisation']}\n";
        echo "   - Password hash présent: " . (strlen($testUser['password']) > 0 ? 'Oui' : 'Non') . "\n";
        
        if (password_verify('test123', $testUser['password'])) {
            echo "   - Test password: ✅ OK\n";
        } else {
            echo "   - Test password: ❌ ÉCHEC\n";
        }
    } else {
        echo "❌ Utilisateur test non trouvé\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur DB: " . $e->getMessage() . "\n";
}

echo "\n🔧 PROCHAINES ÉTAPES:\n";
echo "1. Testez la connexion avec admin@test.ch / test123\n";
echo "2. Regardez les logs en temps réel pendant le test\n";
echo "3. Si l'erreur persiste, ajoutez du debug dans AuthController\n";
echo "4. Vérifiez le template auth/login.twig\n";
?>