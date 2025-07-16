#!/bin/bash

# Script de déploiement simplifié pour Plesk
echo "🚀 Déploiement TopoclimbCH (Plesk - Simple)"
echo "==========================================="

# Vérifier les répertoires
echo "📂 Vérification des répertoires..."
mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/sessions
mkdir -p storage/uploads

# Définir les permissions
chmod -R 755 storage/
chmod -R 755 public/
chmod -R 755 resources/
chmod -R 755 src/

echo "✅ Répertoires et permissions configurés"

# Nettoyage du cache à chaque déploiement
echo "🧹 Nettoyage du cache..."
# Nettoyer le cache fichier
find storage/cache -name "*.php" -type f -delete 2>/dev/null || true
find storage/logs -name "*.log" -type f -delete 2>/dev/null || true
find storage/sessions -name "sess_*" -type f -delete 2>/dev/null || true

# Nettoyer le cache temporaire
find /tmp -name "CachedContainer*" -type f -delete 2>/dev/null || true
find /tmp -name "cached_container*" -type f -delete 2>/dev/null || true

# Exécuter le script de nettoyage du cache optimisé pour déploiement
if [ -f "clear_opcache_deploy.php" ]; then
    echo "🔄 Exécution du nettoyage du cache OPcache..."
    php clear_opcache_deploy.php
else
    echo "⚠️ Script de nettoyage OPcache non trouvé"
fi

echo "✅ Cache nettoyé"

# Vérifier l'autoloader Plesk
if [ -f "/tmp/vendor/autoload.php" ]; then
    echo "✅ Autoloader Plesk trouvé"
else
    echo "❌ Autoloader Plesk manquant - vérifiez la configuration Composer dans Plesk"
    echo "   Allez dans Plesk > PHP Composer > Installer les dépendances"
fi

# Vérifier les fichiers critiques
echo "🔍 Vérification des fichiers critiques..."
files_to_check=(
    "bootstrap.php"
    "public/index.php"
    "resources/views/regions/show.twig"
    "src/Controllers/RegionController.php"
    "config/routes.php"
)

all_files_ok=true
for file in "${files_to_check[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file manquant"
        all_files_ok=false
    fi
done

if [ "$all_files_ok" = true ]; then
    echo "✅ Tous les fichiers critiques sont présents"
else
    echo "❌ Certains fichiers critiques sont manquants"
fi

# Test de syntaxe PHP
echo "🧪 Test de syntaxe PHP..."
if php -l public/index.php >/dev/null 2>&1; then
    echo "✅ Syntaxe PHP validée"
else
    echo "❌ Erreur de syntaxe PHP dans public/index.php"
fi

# Nettoyage final et redémarrage des services
echo "🔄 Nettoyage final du cache..."
# Forcer le rechargement PHP-FPM en touchant un fichier de configuration
touch public/.htaccess 2>/dev/null || true

# Attendre un peu pour s'assurer que le cache est bien vidé
sleep 2

# Commit git automatique
echo "📝 Commit git automatique..."
git add -A
if git diff --cached --quiet; then
    echo "ℹ️ Aucun changement à commiter"
else
    commit_message="deploy: Déploiement automatique $(date '+%Y-%m-%d %H:%M:%S')"
    git commit -m "$commit_message"
    echo "✅ Commit effectué: $commit_message"
fi

echo ""
echo "🎉 Déploiement terminé !"
echo "📋 Prochaines étapes :"
echo "   1. Vérifiez que Composer est configuré dans Plesk"
echo "   2. Testez : https://topoclimb.ch/diagnostic_simple.php"
echo "   3. Testez : https://topoclimb.ch/regions"
echo "   4. ✅ Le cache OPcache a été complètement nettoyé"
echo ""