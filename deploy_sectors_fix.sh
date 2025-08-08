#!/bin/bash

# Déploiement correction SectorController
echo "🚀 DÉPLOIEMENT CORRECTION SECTORS"
echo "================================"

# Variables
REMOTE_HOST="151.80.42.129"
REMOTE_USER="topoclimb"
REMOTE_PATH="/var/www/topoclimb.ch"
LOCAL_FILE="src/Controllers/SectorController.php"

echo "📂 Fichier local: $LOCAL_FILE"
echo "🌐 Serveur: $REMOTE_USER@$REMOTE_HOST"
echo "📁 Chemin distant: $REMOTE_PATH"

# Vérifier que le fichier local existe
if [ ! -f "$LOCAL_FILE" ]; then
    echo "❌ Fichier local non trouvé: $LOCAL_FILE"
    exit 1
fi

echo ""
echo "📤 Envoi du fichier..."

# Copier le fichier
scp "$LOCAL_FILE" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/$LOCAL_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Fichier envoyé avec succès !"
    
    echo ""
    echo "🔄 Vérification du déploiement..."
    
    # Test de la route
    echo "🧪 Test: https://topoclimb.ch/test/sectors/create"
    curl -s -I "https://topoclimb.ch/test/sectors/create" | head -1
    
    echo ""
    echo "✅ Déploiement terminé !"
    echo ""
    echo "🎯 URLs à tester:"
    echo "   - https://topoclimb.ch/test/sectors/create"
    echo "   - https://topoclimb.ch/sectors/create?site_id=20"
    
else
    echo "❌ Erreur lors de l'envoi du fichier"
    exit 1
fi