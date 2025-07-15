#!/bin/bash
# Script de déploiement TopoclimbCH

echo "🚀 Déploiement TopoclimbCH"
echo "========================="

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "composer.json" ]; then
    echo "❌ Erreur: composer.json non trouvé. Êtes-vous dans le bon répertoire ?"
    exit 1
fi

# Sauvegarde des fichiers critiques
echo "📦 Sauvegarde des fichiers critiques..."
cp .env .env.backup 2>/dev/null || echo "⚠️  Pas de fichier .env à sauvegarder"

# Mise à jour du code (si vous utilisez Git)
echo "📥 Mise à jour du code..."
git fetch origin
git reset --hard origin/staging  # ou origin/main selon votre branche

# Nettoyer Composer
echo "🧹 Nettoyage Composer..."
composer clear-cache

# Supprimer le répertoire vendor existant
echo "🗑️  Suppression de vendor..."
rm -rf vendor/

# Réinstaller les dépendances
echo "📚 Installation des dépendances..."
composer install --no-dev --optimize-autoloader --no-interaction

# Vérifier l'installation
echo "✅ Vérification de l'installation..."
if [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Erreur: vendor/autoload.php non trouvé après installation"
    exit 1
fi

# Vérifier les fichiers Symfony
if [ ! -f "vendor/symfony/deprecation-contracts/function.php" ]; then
    echo "❌ Erreur: symfony/deprecation-contracts/function.php non trouvé"
    exit 1
fi

# Créer le fichier .env si nécessaire
if [ ! -f ".env" ]; then
    echo "📝 Création du fichier .env..."
    cp .env.production.example .env
    echo "⚠️  N'oubliez pas de configurer .env avec vos paramètres !"
fi

# Vérifier les permissions
echo "🔒 Vérification des permissions..."
chmod -R 755 public/
chmod -R 755 resources/
chmod -R 755 src/

# Nettoyer les fichiers temporaires
echo "🧹 Nettoyage des fichiers temporaires..."
rm -rf storage/logs/*.log
rm -rf storage/cache/*
rm -rf test_*.php
rm -rf *.db

# Test rapide
echo "🧪 Test rapide..."
php -l public/index.php
if [ $? -eq 0 ]; then
    echo "✅ Syntaxe PHP validée"
else
    echo "❌ Erreur de syntaxe PHP"
    exit 1
fi

echo ""
echo "🎉 Déploiement terminé avec succès !"
echo "📋 Actions à effectuer manuellement :"
echo "   1. Configurer le fichier .env"
echo "   2. Créer la base de données"
echo "   3. Tester l'application"
echo ""
echo "🔗 Testez votre site : https://topoclimb.ch"