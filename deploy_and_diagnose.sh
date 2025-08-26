#!/bin/bash

echo "🚀 DÉPLOIEMENT ET DIAGNOSTIC PRODUCTION"
echo "======================================"
echo "Date: $(date)"
echo ""

# 1. Commit des changements actuels
echo "📝 Commit des changements actuels..."
git add .
git commit -m "🔧 Add production diagnostic script

- check_production_status.php for DB structure analysis
- Supports both MySQL and SQLite detection  
- Tests problematic sectors query with fallback

🤖 Generated with Claude Code"

# 2. Push vers le repo
echo "⬆️  Push vers GitHub..."
git push origin main

echo ""
echo "✅ Déploiement local terminé"
echo ""
echo "📋 PROCHAINES ÉTAPES EN PRODUCTION:"
echo "1. Se connecter au serveur de production"
echo "2. Faire git pull dans le répertoire web"
echo "3. Exécuter: php check_production_status.php"
echo "4. Analyser les différences de structure DB"
echo ""
echo "🎯 URL DE TEST:"
echo "https://topoclimb.ch/sectors?debug_sectors=allow"
echo ""
echo "📊 Si l'erreur persiste, il faudra:"
echo "- Ajouter les colonnes manquantes en production"
echo "- Ou créer un fallback dans SectorService.php"