#!/bin/bash

# Script de déploiement simple TopoclimbCH
# Version allégée pour développement actif
echo "🚀 Déploiement simple TopoclimbCH"
echo "================================"
echo "Commit: $(git rev-parse --short HEAD)"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Vérifications rapides des fichiers critiques
echo "🔍 Vérifications critiques..."
ERRORS=0

if [ ! -f "resources/views/map/index.twig" ]; then
    echo "❌ Template carte manquant"
    ERRORS=$((ERRORS + 1))
fi

if [ ! -f "src/Controllers/MapController.php" ]; then
    echo "❌ MapController manquant"
    ERRORS=$((ERRORS + 1))
fi

if [ ! -f "public/test-carte.html" ]; then
    echo "❌ Page test carte manquante"
    ERRORS=$((ERRORS + 1))
fi

if [ $ERRORS -gt 0 ]; then
    echo "❌ $ERRORS erreur(s) trouvée(s) - Arrêt du déploiement"
    exit 1
fi

echo "✅ Fichiers critiques OK"

# Créer le script de nettoyage pour production
echo "🧹 Création du script de nettoyage..."
cat > clear-production.php << 'EOF'
<?php
/**
 * Script de nettoyage production TopoclimbCH
 * À exécuter via gestionnaire de tâches Plesk
 */

echo "🧹 Nettoyage cache production TopoclimbCH\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n";

$cleaned = 0;

// 1. Vider cache Twig
if (is_dir(__DIR__ . '/storage/cache')) {
    $files = glob(__DIR__ . '/storage/cache/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $cleaned++;
        }
    }
    echo "✅ Cache Twig: $cleaned fichiers supprimés\n";
}

// 2. Vider sessions anciennes (> 24h)
if (is_dir(__DIR__ . '/storage/sessions')) {
    $files = glob(__DIR__ . '/storage/sessions/sess_*');
    $oldSessions = 0;
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) {
            unlink($file);
            $oldSessions++;
        }
    }
    echo "✅ Sessions: $oldSessions anciennes supprimées\n";
}

// 3. OPCache reset
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPCache reset\n";
}

// 4. Logs anciens (> 7 jours)
if (is_dir(__DIR__ . '/storage/logs')) {
    $files = glob(__DIR__ . '/storage/logs/*.log');
    $oldLogs = 0;
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < time() - (7 * 86400)) {
            unlink($file);
            $oldLogs++;
        }
    }
    if ($oldLogs > 0) {
        echo "✅ Logs: $oldLogs anciens supprimés\n";
    }
}

// 5. Marquer le nettoyage
file_put_contents(__DIR__ . '/last-cleanup.txt', date('Y-m-d H:i:s') . "\n");

echo "\n🎯 Nettoyage terminé !\n";
echo "Prochaine exécution recommandée dans 1 heure\n";
EOF

# Créer le dossier des rapports
REPORTS_DIR="deploy-reports"
mkdir -p "$REPORTS_DIR"

# Compter les fichiers
FILES=$(find . -name "*.php" -o -name "*.twig" -o -name "*.js" -o -name "*.css" | wc -l)
echo "📊 $FILES fichiers source détectés"

# Générer nom de rapport avec timestamp
REPORT_FILE="$REPORTS_DIR/deploy-$(date +%Y%m%d_%H%M%S).txt"

# Générer rapport simple
cat > "$REPORT_FILE" << EOF
RAPPORT DÉPLOIEMENT TopoclimbCH
===============================
Date: $(date '+%Y-%m-%d %H:%M:%S')
Commit: $(git rev-parse --short HEAD)
Message: $(git log -1 --pretty=format:'%s')
Fichiers: $FILES

FICHIERS CRITIQUES:
✅ Template carte: $([ -f "resources/views/map/index.twig" ] && echo "OK" || echo "MANQUANT")
✅ MapController: $([ -f "src/Controllers/MapController.php" ] && echo "OK" || echo "MANQUANT")  
✅ Page test: $([ -f "public/test-carte.html" ] && echo "OK" || echo "MANQUANT")
✅ Script nettoyage: clear-production.php créé

INSTRUCTIONS POST-DÉPLOIEMENT:
1. Uploader tous les fichiers sur Plesk
2. Exécuter: php clear-production.php
3. Tester: /test-carte.html puis /map
4. Configurer tâche automatique: php clear-production.php (toutes les heures)

STATUS: ✅ PRÊT POUR DÉPLOIEMENT
EOF

# Nettoyer les anciens rapports (garder seulement les 5 derniers)
echo "🗑️ Nettoyage anciens rapports..."
cd "$REPORTS_DIR"
ls -t deploy-*.txt 2>/dev/null | tail -n +6 | xargs rm -f 2>/dev/null || true
REPORTS_COUNT=$(ls deploy-*.txt 2>/dev/null | wc -l)
cd ..
echo "   ✅ $REPORTS_COUNT rapport(s) conservé(s)"

echo ""
echo "✅ Déploiement préparé avec succès !"
echo "=================================="
echo "📄 Rapport: $REPORT_FILE"
echo "📁 Dossier rapports: $REPORTS_DIR/ ($REPORTS_COUNT fichiers)"
echo "🧹 Script nettoyage: clear-production.php"
echo ""
echo "🚀 Actions suivantes :"
echo "1. Uploader tous les fichiers vers Plesk"
echo "2. Exécuter: php clear-production.php"
echo "3. Tester: /test-carte.html → /map"
echo "4. Configurer tâche Plesk: php clear-production.php (1h)"
echo ""
echo "📊 $FILES fichiers source prêts"
echo "📋 Pour voir tous les rapports: ls $REPORTS_DIR/"
echo "🗑️ Pour nettoyer: rm -rf $REPORTS_DIR/"