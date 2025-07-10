#!/bin/bash

# =======================================================
# 🚀 Script de déploiement simple TopoclimbCH
# =======================================================

echo "🚀 Déploiement TopoclimbCH - $(date)"

# Nettoyage
echo "🧹 Nettoyage..."
rm -rf vendor/ composer.lock

# Installation Composer
echo "📦 Installation Composer..."
if /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction; then
    echo "✅ Composer OK"
elif php /tmp/composer.phar install --no-dev --optimize-autoloader --no-interaction 2>/dev/null; then
    echo "✅ Composer (téléchargé) OK"
else
    echo "⬇️ Téléchargement Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp
    php /tmp/composer.phar install --no-dev --optimize-autoloader --no-interaction
    echo "✅ Installation terminée"
fi

# Création répertoires et permissions
echo "📁 Répertoires et permissions..."
mkdir -p storage/cache storage/logs storage/uploads public/uploads
php clear_cache.php 2>/dev/null || echo "⚠️ clear_cache.php skip"
chmod -R 755 storage/ public/uploads/ 2>/dev/null || true
chmod 644 .env 2>/dev/null || true

# Vérification
if [ -f "vendor/autoload.php" ]; then
    echo "✅ DÉPLOIEMENT RÉUSSI"
    echo "📊 Vendor: $(du -sh vendor | cut -f1)"
else
    echo "❌ ÉCHEC - vendor/autoload.php manquant"
    exit 1
fi

echo "🎉 TopoclimbCH déployé avec succès !"