#!/bin/bash

# Script de déploiement Git pour Plesk
echo "🚀 Déploiement Git TopoclimbCH"
echo "=============================="

# Vérifier les actions Git dans Plesk
echo "📋 Instructions pour déployer via Plesk Git :"
echo ""
echo "1. Connectez-vous à votre interface Plesk"
echo "2. Allez dans 'Git' > 'Déployer'"
echo "3. Cliquez sur 'Déployer' pour récupérer les dernières modifications"
echo "4. Ou utilisez les commandes Git manuelles ci-dessous"
echo ""

# Vérifier si Git est configuré
if [ -d ".git" ]; then
    echo "✅ Dépôt Git détecté"
    
    # Afficher le statut Git
    echo "📊 Statut Git actuel :"
    git status --porcelain
    git log --oneline -3
    
    echo ""
    echo "🔄 Commandes Git à exécuter sur le serveur :"
    echo "git fetch origin"
    echo "git reset --hard origin/main"
    echo "git pull origin main"
    
else
    echo "❌ Pas de dépôt Git configuré"
    echo "💡 Configurez Git dans Plesk ou uploadez manuellement les fichiers"
fi

echo ""
echo "📁 Fichiers modifiés récemment :"
echo "- public/diagnostic_simple.php (version améliorée)"
echo "- bootstrap.php (logique Plesk)"
echo "- resources/views/regions/show.twig (nouvelle version)"
echo ""
echo "🔍 Après déploiement, testez :"
echo "- https://topoclimb.ch/diagnostic_simple.php"
echo "- https://topoclimb.ch/regions"
echo ""