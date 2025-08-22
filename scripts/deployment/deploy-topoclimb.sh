#!/bin/bash
# Script de déploiement TopoclimbCH - Version spécifique pour votre hébergement

echo "🚀 Déploiement TopoclimbCH sur votre serveur"
echo "============================================="

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "composer.json" ]; then
    echo "❌ Erreur: composer.json non trouvé. Êtes-vous dans le bon répertoire ?"
    exit 1
fi

# Sauvegarde des fichiers critiques
echo "📦 Sauvegarde des fichiers critiques..."
cp .env .env.backup.$(date +%Y%m%d_%H%M%S) 2>/dev/null || echo "⚠️  Pas de fichier .env à sauvegarder"

# Nettoyer Composer complètement
echo "🧹 Nettoyage complet de Composer..."
composer clear-cache
rm -rf vendor/
rm -f composer.lock

# Réinstaller les dépendances proprement
echo "📚 Installation des dépendances (cela peut prendre quelques minutes)..."
composer install --no-dev --optimize-autoloader --no-interaction --verbose

# Vérifier l'installation critique
echo "✅ Vérification de l'installation..."
if [ ! -f "vendor/autoload.php" ]; then
    echo "❌ ERREUR CRITIQUE: vendor/autoload.php non trouvé après installation"
    echo "Essayez de réinstaller Composer sur votre serveur"
    exit 1
fi

# Vérifier les fichiers Symfony spécifiques
echo "🔍 Vérification des fichiers Symfony..."
if [ ! -f "vendor/symfony/deprecation-contracts/function.php" ]; then
    echo "❌ ERREUR: symfony/deprecation-contracts/function.php non trouvé"
    echo "Tentative de réinstallation de Symfony..."
    composer require symfony/deprecation-contracts --no-dev
fi

# Configuration du fichier .env
echo "📝 Configuration du fichier .env..."
if [ ! -f ".env" ]; then
    cp .env.production.example .env
    echo "✅ Fichier .env créé depuis le template"
    echo "⚠️  N'oubliez pas de configurer .env avec vos paramètres !"
else
    echo "⚠️  Fichier .env existe déjà. Sauvegardé dans .env.backup.*"
fi

# Créer les répertoires nécessaires
echo "📁 Création des répertoires nécessaires..."
mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/sessions

# Vérifier les permissions
echo "🔒 Configuration des permissions..."
chmod -R 755 public/
chmod -R 755 resources/
chmod -R 755 src/
chmod -R 755 storage/
chmod -R 777 storage/logs/    # Logs doivent être accessibles en écriture
chmod -R 777 storage/cache/   # Cache doit être accessible en écriture

# Nettoyer les fichiers temporaires et de développement
echo "🧹 Nettoyage des fichiers temporaires..."
rm -rf storage/logs/*.log
rm -rf storage/cache/*
rm -rf test_*.php
rm -rf *.db
rm -rf *.sqlite
rm -rf debug_production.php
rm -rf bootstrap.php

# Test de syntaxe PHP
echo "🧪 Test de syntaxe PHP..."
php -l public/index.php
if [ $? -eq 0 ]; then
    echo "✅ Syntaxe PHP validée"
else
    echo "❌ Erreur de syntaxe PHP dans public/index.php"
    exit 1
fi

# Test des principales classes
echo "🧪 Test des classes principales..."
php -r "
require 'vendor/autoload.php';
if (class_exists('Symfony\\Component\\HttpFoundation\\Request')) {
    echo 'Symfony HttpFoundation: OK' . PHP_EOL;
} else {
    echo 'Symfony HttpFoundation: ERREUR' . PHP_EOL;
    exit(1);
}
"

# Vérifier que les fichiers critiques sont présents
echo "🔍 Vérification des fichiers critiques..."
critical_files=(
    "vendor/autoload.php"
    "vendor/symfony/deprecation-contracts/function.php"
    "src/Core/Database.php"
    "src/Core/Router.php"
    "config/routes.php"
    "public/index.php"
)

for file in "${critical_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file MANQUANT"
        exit 1
    fi
done

echo ""
echo "🎉 Déploiement terminé avec succès !"
echo "=================================="
echo ""
echo "📋 Vérifications finales à effectuer :"
echo "   1. ✅ Dépendances Composer installées"
echo "   2. ✅ Fichier .env configuré"
echo "   3. ✅ Permissions configurées"
echo "   4. ✅ Fichiers critiques présents"
echo ""
echo "🔗 Testez maintenant votre site : http://topoclimb.ch"
echo ""
echo "⚠️  En cas d'erreur, vérifiez les logs dans storage/logs/"
echo "💡 Pour debug, ajoutez ?debug=1 à votre URL"