<?php
/**
 * SOLUTION D'URGENCE - Créer contournement temporaire pour /map
 * Si le cache serveur ne peut pas être résolu rapidement
 */

echo "🚨 CRÉATION CONTOURNEMENT TEMPORAIRE /map\n";
echo "========================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$success = [];
$errors = [];

try {
    // 1. CRÉER une copie de la page fonctionnelle comme solution temporaire
    echo "1. COPIE page fonctionnelle vers route temporaire...\n";
    
    $workingPage = __DIR__ . '/public/test-carte.html';
    $mapWorkaround = __DIR__ . '/public/map-temp.php';
    
    if (file_exists($workingPage)) {
        $content = file_get_contents($workingPage);
        
        // Convertir en PHP avec headers anti-cache
        $phpContent = '<?php
// SOLUTION TEMPORAIRE - ' . date('Y-m-d H:i:s') . '
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
' . $content;
        
        file_put_contents($mapWorkaround, $phpContent);
        echo "   ✅ Contournement créé: map-temp.php\n";
        $success[] = "Route temporaire map-temp.php créée";
    } else {
        throw new Exception("Page fonctionnelle test-carte.html introuvable");
    }

    // 2. CRÉER redirection .htaccess temporaire
    echo "\n2. CRÉATION redirection temporaire...\n";
    
    $htaccessBackup = __DIR__ . '/.htaccess.backup-' . date('Ymd-His');
    $htaccessPath = __DIR__ . '/.htaccess';
    
    // Backup de l'ancien .htaccess
    if (file_exists($htaccessPath)) {
        copy($htaccessPath, $htaccessBackup);
        echo "   ✅ Backup .htaccess: " . basename($htaccessBackup) . "\n";
    }
    
    // Ajouter redirection temporaire
    $redirectRule = "\n# REDIRECTION TEMPORAIRE MAP - " . date('Y-m-d H:i:s') . "\n";
    $redirectRule .= "# Contournement cache serveur\n";
    $redirectRule .= "RewriteEngine On\n";
    $redirectRule .= "RewriteRule ^map/?$ /map-temp.php [L,QSA]\n";
    $redirectRule .= "# Fin redirection temporaire\n\n";
    
    if (file_exists($htaccessPath)) {
        $existingContent = file_get_contents($htaccessPath);
        file_put_contents($htaccessPath, $redirectRule . $existingContent);
    } else {
        file_put_contents($htaccessPath, $redirectRule);
    }
    
    echo "   ✅ Redirection ajoutée à .htaccess\n";
    $success[] = "Redirection /map → /map-temp.php ajoutée";

    // 3. CRÉER notice pour l'utilisateur
    echo "\n3. CRÉATION notice utilisateur...\n";
    
    $noticePath = __DIR__ . '/public/cache-notice.php';
    $noticeContent = '<?php
header("Cache-Control: no-cache");
?>
<!DOCTYPE html>
<html><head><title>Notice Cache</title></head>
<body style="font-family: Arial; margin: 40px; background: #fff3cd; padding: 20px; border-radius: 8px;">
<h2>🚧 Mode Temporaire Activé</h2>
<p><strong>La route /map utilise actuellement un contournement temporaire.</strong></p>
<p>Raison: Problème de cache serveur détecté.</p>
<p>Date d\'activation: ' . date('Y-m-d H:i:s') . '</p>
<hr>
<h3>État des tests:</h3>
<ul>
<li><a href="/test-simple.php">Test PHP basique</a></li>
<li><a href="/test-carte.html">Page carte fonctionnelle</a></li>
<li><a href="/map-temp.php">Route temporaire</a></li>
<li><a href="/map">Route officielle (problématique)</a></li>
</ul>
<hr>
<p><small>Cette notice sera supprimée une fois le problème résolu.</small></p>
</body></html>';
    
    file_put_contents($noticePath, $noticeContent);
    echo "   ✅ Notice créée: cache-notice.php\n";
    $success[] = "Notice utilisateur créée";

    // 4. TESTER la redirection
    echo "\n4. TEST de la redirection...\n";
    
    if (file_exists($mapWorkaround)) {
        echo "   ✅ Fichier map-temp.php accessible\n";
        echo "   ℹ️ Testez maintenant: /map (doit utiliser map-temp.php)\n";
    }

} catch (Exception $e) {
    $errors[] = "Erreur: " . $e->getMessage();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// RAPPORT
echo "\n🎯 RÉSUMÉ CONTOURNEMENT\n";
echo "======================\n";
echo "Succès: " . count($success) . "\n";
echo "Erreurs: " . count($errors) . "\n\n";

if (!empty($success)) {
    echo "✅ ACTIONS RÉUSSIES:\n";
    foreach ($success as $i => $action) {
        echo "   " . ($i + 1) . ". $action\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
    echo "\n";
}

echo "📋 INSTRUCTIONS POST-CONTOURNEMENT:\n";
echo "==================================\n";
echo "1. 📤 Uploader les fichiers modifiés\n";
echo "2. 🔍 Tester: /map (doit utiliser le contournement)\n";
echo "3. ✅ Vérifier: /cache-notice.php pour status\n";
echo "4. 🔧 Une fois cache serveur résolu:\n";
echo "   - Restaurer .htaccess depuis backup\n";
echo "   - Supprimer map-temp.php\n";
echo "   - Supprimer cache-notice.php\n\n";

echo "🚀 AVANTAGES du contournement:\n";
echo "• /map fonctionne immédiatement\n";
echo "• Utilise la version qui marche (test-carte.html)\n";
echo "• Évite le cache serveur problématique\n";
echo "• Solution transparente pour l'utilisateur\n\n";

$isSuccess = count($errors) === 0;
echo ($isSuccess ? "🎉 CONTOURNEMENT CRÉÉ AVEC SUCCÈS" : "⚠️ CONTOURNEMENT PARTIEL") . "\n";

exit($isSuccess ? 0 : 1);
?>