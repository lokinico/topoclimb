#!/bin/bash
#
# Script de déploiement TopoclimbCH
# Usage: ./deploy_topoclimb.sh [branch]
#

set -e  # Arrêter en cas d'erreur

# Configuration
BRANCH=${1:-main}
PROJECT_ROOT="$(pwd)"
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"

echo "🚀 Déploiement TopoclimbCH - Branche: $BRANCH"
echo "📁 Répertoire: $PROJECT_ROOT"
echo "⏰ Date: $(date)"
echo "========================================"

# 1. Backup de sécurité (optionnel)
echo "💾 Création d'un backup de sécurité..."
mkdir -p "$BACKUP_DIR"
cp -r cache/ "$BACKUP_DIR/" 2>/dev/null || echo "⚠️  Pas de cache à sauvegarder"
cp -r storage/cache/ "$BACKUP_DIR/" 2>/dev/null || echo "⚠️  Pas de storage cache à sauvegarder"
echo "✅ Backup créé dans: $BACKUP_DIR"

# 2. Git pull
echo "📥 Récupération des derniers changements..."
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"
echo "✅ Code mis à jour"

# 3. Vider les caches
echo "🧹 Vidage des caches..."

# Cache Twig
if [ -d "cache/views" ]; then
    rm -rf cache/views/*
    echo "✅ Cache Twig vidé"
fi

# Cache de stockage
if [ -d "storage/cache" ]; then
    rm -rf storage/cache/*
    echo "✅ Cache de stockage vidé"
fi

# Cache général
find cache -name "*.php" -type f -delete 2>/dev/null || true
echo "✅ Cache général vidé"

# 4. Composer (si nécessaire)
if [ -f "composer.json" ] && command -v composer &> /dev/null; then
    echo "📦 Mise à jour des dépendances Composer..."
    composer install --no-dev --optimize-autoloader
    echo "✅ Dépendances mises à jour"
fi

# 5. Permissions
echo "🔐 Correction des permissions..."
chmod -R 755 cache/ storage/ 2>/dev/null || true
chmod -R 644 cache/views/ storage/cache/ 2>/dev/null || true
echo "✅ Permissions corrigées"

# 6. Test de santé basique
echo "🏥 Test de santé de l'application..."
if curl -s -f "https://topoclimb.ch/" > /dev/null; then
    echo "✅ Application accessible"
else
    echo "⚠️  Application peut-être inaccessible"
fi

# 7. Nettoyage
echo "🧹 Nettoyage post-déploiement..."
# Supprimer les anciens logs (> 30 jours)
find storage/logs -name "*.log" -type f -mtime +30 -delete 2>/dev/null || true
# Supprimer les anciens backups (> 7 jours)
find backups -type d -mtime +7 -exec rm -rf {} + 2>/dev/null || true
echo "✅ Nettoyage terminé"

echo "========================================"
echo "🎉 Déploiement terminé avec succès!"
echo "💡 Les templates seront recompilés automatiquement"
echo "🔗 Vérifiez: https://topoclimb.ch/sectors"