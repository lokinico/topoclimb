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

echo ""
echo "🎉 Déploiement terminé !"
echo "📋 Prochaines étapes :"
echo "   1. Vérifiez que Composer est configuré dans Plesk"
echo "   2. Testez : https://topoclimb.ch/diagnostic_simple.php"
echo "   3. Testez : https://topoclimb.ch/regions"
echo ""