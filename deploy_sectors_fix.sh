#!/bin/bash

# Script de déploiement urgent - Correction secteurs production
# TopoclimbCH - Fix colonnes 'active' manquantes

echo "🚨 DÉPLOIEMENT URGENT - Correction secteurs"
echo "==========================================="
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Commit: $(git rev-parse --short HEAD)"
echo ""

# 1. Vérifier les fichiers critiques
echo "🔍 Vérification fichiers critiques..."

FILES_OK=true
if [ ! -f "quick_fix_active.php" ]; then
    echo "❌ quick_fix_active.php manquant"
    FILES_OK=false
fi

if [ ! -f "src/Services/SectorService.php" ]; then
    echo "❌ SectorService.php manquant"
    FILES_OK=false
fi

if [ ! -f "src/Controllers/SectorController.php" ]; then
    echo "❌ SectorController.php manquant"
    FILES_OK=false
fi

if [ "$FILES_OK" = false ]; then
    echo "❌ Fichiers manquants - Arrêt du déploiement"
    exit 1
fi

echo "✅ Tous les fichiers critiques présents"
echo ""

# 2. Instructions de déploiement production
echo "📋 INSTRUCTIONS DÉPLOIEMENT PRODUCTION:"
echo "======================================"
echo ""
echo "1. 📤 Upload des fichiers sur le serveur :"
echo "   - quick_fix_active.php"
echo "   - src/Services/SectorService.php (avec fallback 4 niveaux)"
echo "   - src/Controllers/SectorController.php"
echo ""
echo "2. 🔧 Sur le serveur, exécuter :"
echo "   php quick_fix_active.php"
echo ""
echo "3. 🧪 Tester :"
echo "   curl https://topoclimb.ch/sectors"
echo "   ou visiter: https://topoclimb.ch/sectors"
echo ""
echo "4. 📊 Si des erreurs persistent, diagnostiquer :"
echo "   php diagnose_code_column.php"
echo ""
echo "5. ✅ Vérifier les logs :"
echo "   tail -f storage/logs/app-*.log"
echo ""

# 3. Récapitulatif des corrections
echo "🛠️ CORRECTIONS APPLIQUÉES:"
echo "========================="
echo "✅ SectorService.php - Fallback 4 niveaux pour colonnes manquantes"
echo "✅ quick_fix_active.php - Ajout automatique colonne 'active'"
echo "✅ Bypass debug temporaire - ?debug_sectors=allow"
echo "✅ Gestion erreurs SQL améliorée"
echo ""

# 4. Test local avant déploiement
echo "🧪 Test local rapide..."
if php -l quick_fix_active.php > /dev/null 2>&1; then
    echo "✅ quick_fix_active.php - Syntaxe OK"
else
    echo "❌ quick_fix_active.php - Erreur syntaxe"
    exit 1
fi

if php -l src/Services/SectorService.php > /dev/null 2>&1; then
    echo "✅ SectorService.php - Syntaxe OK"
else
    echo "❌ SectorService.php - Erreur syntaxe"
    exit 1
fi

echo ""
echo "🎯 DÉPLOIEMENT PRÊT !"
echo "==================="
echo ""
echo "⚠️  IMPORTANT: Ce déploiement corrige le problème critique"
echo "    'Unknown column active' sur la page /sectors"
echo ""
echo "📞 En cas de problème, rollback possible avec:"
echo "   git checkout HEAD~1 src/Services/SectorService.php"
echo ""
echo "🚀 Bonne chance !"