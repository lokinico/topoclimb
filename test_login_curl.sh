#!/bin/bash

echo "🔐 TEST CONNEXION ADMIN ET FORMULAIRE"

# Créer une session curl avec cookies
COOKIE_JAR="/tmp/topoclimb_cookies.txt"

# 1. Récupérer la page de login pour obtenir le CSRF token
echo "1. Récupération page de connexion..."
LOGIN_PAGE=$(curl -s -c "$COOKIE_JAR" "http://localhost:8000/login")

# Extraire le token CSRF
CSRF_TOKEN=$(echo "$LOGIN_PAGE" | grep -oP 'name="csrf_token" value="\K[^"]*' | head -1)
if [ -n "$CSRF_TOKEN" ]; then
    echo "✅ Token CSRF récupéré: ${CSRF_TOKEN:0:20}..."
else
    echo "❌ Token CSRF introuvable"
    exit 1
fi

# 2. Tenter la connexion
echo "2. Tentative de connexion admin..."
LOGIN_RESPONSE=$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -d "username=admin_test" \
    -d "password=TestAdmin2025!" \
    -d "csrf_token=$CSRF_TOKEN" \
    -X POST "http://localhost:8000/login")

# Vérifier si la connexion a réussi (redirection ou pas de formulaire login)
if echo "$LOGIN_RESPONSE" | grep -q "login" && echo "$LOGIN_RESPONSE" | grep -q "password"; then
    echo "❌ Échec de connexion - toujours sur page login"
else
    echo "✅ Connexion probablement réussie"
fi

# 3. Tester l'accès à un formulaire protégé
echo "3. Test accès formulaire routes/create..."
ROUTES_FORM=$(curl -s -b "$COOKIE_JAR" "http://localhost:8000/routes/create")

if echo "$ROUTES_FORM" | grep -q "Créer une nouvelle voie\|Ajouter une voie"; then
    echo "✅ Formulaire routes accessible"
    
    # Vérifier la présence des éléments clés
    if echo "$ROUTES_FORM" | grep -q "region_id"; then
        echo "✅ Sélecteur région présent"
    fi
    
    if echo "$ROUTES_FORM" | grep -q "site_id"; then
        echo "✅ Sélecteur site présent" 
    fi
    
    if echo "$ROUTES_FORM" | grep -q "sector_id"; then
        echo "✅ Sélecteur secteur présent"
    fi
    
    if echo "$ROUTES_FORM" | grep -q "difficulty_system_id"; then
        echo "✅ Système de cotation présent"
    fi
    
    if echo "$ROUTES_FORM" | grep -q "autocomplete=\"off\""; then
        echo "✅ Autocomplete désactivé (pas de message sécurité)"
    fi
    
    if echo "$ROUTES_FORM" | grep -q "convert-to-gps-btn\|convert-to-lv95-btn"; then
        echo "✅ Boutons conversion coordonnées présents"
    fi
    
else
    echo "❌ Formulaire routes inaccessible ou erreur"
    echo "Réponse (100 premiers caractères):"
    echo "$ROUTES_FORM" | head -c 100
fi

# Nettoyer
rm -f "$COOKIE_JAR"

echo -e "\n🎯 RÉSUMÉ:"
echo "Serveur: http://localhost:8000"
echo "Admin: admin_test / TestAdmin2025!"
echo "Test automatique terminé - vérifiez manuellement pour validation complète"