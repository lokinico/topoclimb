#!/bin/bash

# Script de mise à jour pour déploiements Plesk existants
# Applique uniquement les corrections critiques récentes

echo "🔄 Mise à jour TopoclimbCH pour Plesk"
echo "===================================="
echo "Commit: $(git rev-parse --short HEAD)"
echo "Date: $(date)"
echo ""

# Vérifier qu'on est dans un déploiement existant
if [ ! -f "composer.json" ]; then
    echo "❌ Erreur: composer.json non trouvé"
    echo "Ce script doit être exécuté depuis la racine du projet"
    exit 1
fi

# Créer un backup des fichiers critiques
echo "📋 Création d'un backup des fichiers critiques..."
BACKUP_DIR="backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# Backup des fichiers qui vont être modifiés
cp -r resources/views/checklists/ $BACKUP_DIR/checklists-old/ 2>/dev/null || echo "⚠️  Pas de backup checklists"
cp -r resources/views/equipment/ $BACKUP_DIR/equipment-old/ 2>/dev/null || echo "⚠️  Pas de backup equipment"
cp -r resources/views/map/ $BACKUP_DIR/map-old/ 2>/dev/null || echo "⚠️  Pas de backup map"
cp public/css/pages/map.css $BACKUP_DIR/map-old.css 2>/dev/null || echo "⚠️  Pas de backup map.css"
cp src/Controllers/ChecklistController.php $BACKUP_DIR/ChecklistController-old.php 2>/dev/null || echo "⚠️  Pas de backup ChecklistController"
cp src/Controllers/EquipmentController.php $BACKUP_DIR/EquipmentController-old.php 2>/dev/null || echo "⚠️  Pas de backup EquipmentController"

echo "✅ Backup créé dans: $BACKUP_DIR"

# Mise à jour des fichiers critiques (à adapter selon votre méthode de déploiement)
echo ""
echo "🔄 Application des corrections..."
echo "⚠️  IMPORTANT: Vous devez maintenant :"
echo "1. Remplacer les fichiers suivants par leurs versions corrigées :"
echo "   - resources/views/checklists/index.twig"
echo "   - resources/views/equipment/index.twig"
echo "   - resources/views/map/index.twig"
echo "   - public/css/pages/map.css"
echo "   - src/Controllers/ChecklistController.php"
echo "   - src/Controllers/EquipmentController.php"
echo ""
echo "2. Vérifier que les templates utilisent 'layouts/app.twig' au lieu de 'base.twig'"
echo ""
echo "3. Tester les routes après mise à jour :"
echo "   - /checklists"
echo "   - /equipment"
echo "   - /map"
echo ""
echo "4. Exécuter le script de validation :"
echo "   php plesk-config.php"
echo ""

# Vérifier les permissions
echo "🔧 Vérification des permissions..."
chmod -R 755 public/
chmod -R 755 resources/
chmod -R 777 storage/

echo "✅ Permissions mises à jour"
echo ""
echo "📊 Résumé des corrections à appliquer :"
echo "✅ Fix templates Twig (base.twig → layouts/app.twig)"
echo "✅ Fix contrôleurs (injection de dépendances)"
echo "✅ Fix carte interactive (tuiles simplifiées)"
echo "✅ Fix gestion d'erreurs"
echo ""
echo "⚠️  N'oubliez pas de remplacer les fichiers manuellement !"
echo "📋 Backup disponible dans: $BACKUP_DIR"