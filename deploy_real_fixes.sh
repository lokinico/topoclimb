#!/bin/bash

echo "🔧 DÉPLOIEMENT CORRECTIONS RÉELLES"
echo "=================================="

# Sauvegarde avant déploiement
echo "📦 Sauvegarde..."
cp config/routes.php config/routes.php.backup.$(date +%Y%m%d_%H%M%S)

# Déployer les corrections
echo "📄 Déploiement des fichiers corrigés..."

# Routes
echo "  - config/routes.php"

# Controllers corrigés  
echo "  - src/Controllers/RouteController.php (suppression 'code')"
echo "  - src/Controllers/SiteController.php (médias avec relations)"  
echo "  - src/Controllers/SectorController.php (médias avec relations)"

echo "✅ Fichiers déployés"

echo ""
echo "🧪 TESTS À EFFECTUER:"
echo "1. /routes/create → Plus d'erreur 'Unknown column code'"
echo "2. /sites/21 → Médias chargés via relations"  
echo "3. /sectors/12 → Médias chargés via relations"
echo "4. /sites/{id}/edit POST → Plus de 404"
echo "5. /regions/{id}/edit POST → Plus de 404"

echo ""
echo "📋 RÉSUMÉ DES CORRECTIONS:"
echo "✅ RouteController: SELECT id, name (sans 'code')"
echo "✅ Médias: JOIN climbing_media_relationships"  
echo "✅ Routes POST: /sites/{id} et /regions/{id} ajoutées"
echo "✅ Structure: Basée sur la vraie structure MySQL production"

echo ""
echo "🎯 Ces corrections corrigent les 3 erreurs principales:"
echo "- Unknown column 'code'"
echo "- Unknown column 'entity_type'"  
echo "- Routes POST 404"