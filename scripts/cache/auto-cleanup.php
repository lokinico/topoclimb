<?php
/**
 * Script de nettoyage automatique TopoclimbCH
 * À configurer dans le gestionnaire de tâches Plesk
 * 
 * Commande Plesk : php /chemin/vers/auto-cleanup.php
 * Fréquence recommandée : Toutes les heures
 */

// Configuration
$maxSessionAge = 86400;  // 24h en secondes
$maxLogAge = 604800;     // 7 jours en secondes

// Début du rapport
echo "🧹 NETTOYAGE AUTOMATIQUE TopoclimbCH\n";
echo "====================================\n";
echo "Heure: " . date('Y-m-d H:i:s') . "\n";
echo "PID: " . getmypid() . "\n\n";

$totalCleaned = 0;
$errors = [];
$warnings = [];

try {
    // 1. Cache Twig (le plus important)
    echo "1. Nettoyage cache Twig...\n";
    $cacheDir = __DIR__ . '/storage/cache';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        $cacheCleaned = 0;
        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $cacheCleaned++;
                } else {
                    $warnings[] = "Impossible de supprimer cache: $file";
                }
            }
        }
        echo "   ✅ $cacheCleaned fichiers cache supprimés\n";
        $totalCleaned += $cacheCleaned;
    } else {
        echo "   ⚠️ Dossier cache introuvable\n";
    }

    // 2. Sessions anciennes
    echo "2. Nettoyage sessions anciennes...\n";
    $sessionDir = __DIR__ . '/storage/sessions';
    if (is_dir($sessionDir)) {
        $sessionFiles = glob($sessionDir . '/sess_*');
        $sessionsRemoved = 0;
        foreach ($sessionFiles as $file) {
            if (is_file($file) && filemtime($file) < (time() - $maxSessionAge)) {
                if (unlink($file)) {
                    $sessionsRemoved++;
                } else {
                    $warnings[] = "Session non supprimable: $file";
                }
            }
        }
        echo "   ✅ $sessionsRemoved sessions expirées supprimées\n";
        $totalCleaned += $sessionsRemoved;
    } else {
        echo "   ⚠️ Dossier sessions introuvable\n";
    }

    // 3. OPCache PHP (crucial après déploiement) - NON CRITIQUE
    echo "3. Reset OPCache PHP...\n";
    if (function_exists('opcache_reset')) {
        if (opcache_reset()) {
            echo "   ✅ OPCache reseté\n";
        } else {
            echo "   ⚠️ Échec reset OPCache (non critique)\n";
            // Ne pas ajouter aux erreurs - c'est optionnel
        }
    } else {
        echo "   ℹ️ OPCache non disponible (normal sur certains serveurs)\n";
    }

    // 4. Logs anciens
    echo "4. Nettoyage logs anciens...\n";
    $logDir = __DIR__ . '/storage/logs';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/*.log');
        $logsRemoved = 0;
        foreach ($logFiles as $file) {
            if (is_file($file) && filemtime($file) < (time() - $maxLogAge)) {
                if (unlink($file)) {
                    $logsRemoved++;
                } else {
                    $warnings[] = "Log non supprimable: $file";
                }
            }
        }
        if ($logsRemoved > 0) {
            echo "   ✅ $logsRemoved logs anciens supprimés\n";
        } else {
            echo "   ℹ️ Aucun log ancien à supprimer\n";
        }
        $totalCleaned += $logsRemoved;
    } else {
        echo "   ⚠️ Dossier logs introuvable\n";
    }

    // 5. Nettoyage uploads temporaires (optionnel)
    echo "5. Nettoyage uploads temporaires...\n";
    $uploadDir = __DIR__ . '/storage/uploads';
    if (is_dir($uploadDir)) {
        $tempFiles = glob($uploadDir . '/tmp_*');
        $tempRemoved = 0;
        foreach ($tempFiles as $file) {
            if (is_file($file) && filemtime($file) < (time() - 3600)) { // 1h
                if (unlink($file)) {
                    $tempRemoved++;
                }
            }
        }
        if ($tempRemoved > 0) {
            echo "   ✅ $tempRemoved fichiers temporaires supprimés\n";
        } else {
            echo "   ℹ️ Aucun fichier temporaire à supprimer\n";
        }
        $totalCleaned += $tempRemoved;
    } else {
        echo "   ℹ️ Dossier uploads introuvable\n";
    }

} catch (Exception $e) {
    $errors[] = "Erreur générale: " . $e->getMessage();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// 6. Statistiques finales
echo "\n📊 RÉSUMÉ:\n";
echo "==========\n";
echo "Total nettoyé: $totalCleaned éléments\n";
echo "Erreurs critiques: " . count($errors) . "\n";
echo "Avertissements: " . count($warnings) . "\n";

if (!empty($errors)) {
    echo "\n❌ ERREURS CRITIQUES:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️ AVERTISSEMENTS (non critiques):\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

// 7. Enregistrer l'exécution
$logEntry = sprintf(
    "[%s] Nettoyage: %d éléments, %d erreurs critiques, %d avertissements\n",
    date('Y-m-d H:i:s'),
    $totalCleaned,
    count($errors),
    count($warnings)
);

file_put_contents(__DIR__ . '/cleanup.log', $logEntry, FILE_APPEND | LOCK_EX);

// 8. Marquer la dernière exécution
file_put_contents(__DIR__ . '/last-cleanup.txt', date('Y-m-d H:i:s'));

// Status final
if (count($errors) > 0) {
    echo "\n❌ NETTOYAGE TERMINÉ AVEC ERREURS CRITIQUES\n";
    $exitCode = 1;
} else {
    echo "\n🎯 NETTOYAGE TERMINÉ AVEC SUCCÈS\n";
    $exitCode = 0;
}

echo "Next run: " . date('Y-m-d H:i:s', time() + 3600) . "\n";
echo "Log: cleanup.log\n";

if (count($warnings) > 0) {
    echo "Note: " . count($warnings) . " avertissement(s) non critique(s)\n";
}

// Code de sortie - seulement erreur si problèmes critiques
exit($exitCode);
?>