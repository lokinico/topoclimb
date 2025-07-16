#!/bin/bash
# Script de déploiement TopoclimbCH pour Plesk

echo "🚀 Déploiement TopoclimbCH (Plesk)"
echo "================================="

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
if [ -d ".git" ]; then
    echo "Git détecté, mise à jour du code..."
    git fetch origin
    git reset --hard origin/main
else
    echo "⚠️  Git non configuré - le code doit être mis à jour manuellement"
    echo "   Assurez-vous d'avoir uploadé les derniers fichiers"
fi

# Note: Plesk gère Composer automatiquement dans /tmp
echo "📚 Dépendances Composer gérées par Plesk..."
echo "   Les dépendances sont disponibles dans /tmp/vendor/"

# Vérifier que l'autoloader Plesk est disponible
if [ ! -f "/tmp/vendor/autoload.php" ]; then
    echo "❌ Erreur: /tmp/vendor/autoload.php non trouvé"
    echo "   Vérifiez que Composer est configuré dans Plesk"
    exit 1
fi

echo "✅ Autoloader Plesk trouvé"

# Vérifier que bootstrap.php existe
if [ ! -f "bootstrap.php" ]; then
    echo "❌ Erreur: bootstrap.php non trouvé - fichier nécessaire pour l'application"
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
# NE PAS supprimer bootstrap.php car il est nécessaire

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