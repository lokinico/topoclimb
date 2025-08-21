#!/bin/bash

echo "=== TEST CORRECTIONS ROUTES ET FORMULAIRES ==="
echo ""

BASE_URL="http://localhost:8000"

# Test fonction pour vérifier l'accès
test_route() {
    local name="$1"
    local url="$2"
    
    echo "🧪 TEST: $name"
    echo "URL: $BASE_URL$url"
    
    response=$(timeout 10 curl -s "$BASE_URL$url" -w "%{http_code}" -o /tmp/route_test.html)
    http_code="${response: -3}"
    
    if [ "$http_code" == "200" ]; then
        echo "  ✅ Page accessible (HTTP 200)"
        
        # Vérifier la présence d'un formulaire
        if grep -q "<form" /tmp/route_test.html; then
            echo "  ✅ Formulaire trouvé"
            
            # Vérifier l'action du formulaire
            form_action=$(grep -o 'action="[^"]*"' /tmp/route_test.html | head -1)
            echo "  📝 Action du formulaire: $form_action"
            
            # Vérifier autocomplete
            if grep -q 'autocomplete="off"' /tmp/route_test.html; then
                echo "  ✅ Autocomplete désactivé (évite message sécurité)"
            elif grep -q 'autocomplete="on"' /tmp/route_test.html; then
                echo "  ⚠️  Autocomplete activé (peut causer message sécurité)"
            fi
            
        else
            echo "  ❌ Aucun formulaire trouvé"
        fi
        
    elif [ "$http_code" == "302" ]; then
        echo "  ⚠️  Page redirige (HTTP 302) - authentification requise"
    else
        echo "  ❌ Erreur HTTP: $http_code"
    fi
    
    echo ""
}

echo "🔧 Test des corrections de routes appliquées..."
echo ""

# Test des routes corrigées
test_route "Régions - Création (CORRIGÉ)" "/regions/create"
test_route "Sites - Création" "/sites/create" 
test_route "Secteurs - Création (CORRIGÉ)" "/sectors/create"
test_route "Voies - Création (CORRIGÉ)" "/routes/create"
test_route "Guides - Création (CORRIGÉ)" "/books/create"

echo "📝 RÉCAPITULATIF DES CORRECTIONS APPLIQUÉES:"
echo ""
echo "✅ Route /regions/create déplacée AVANT /regions/{id}"
echo "✅ Formulaire secteurs corrigé: /sectors → /sectors/create"
echo "✅ Formulaire routes corrigé: /routes → /routes/create" 
echo "✅ Formulaire books corrigé: /books → /books/create"
echo "✅ Attribut autocomplete=off sur tous formulaires (évite message sécurité)"
echo ""
echo "🔄 CORRECTIONS RESTANTES À FAIRE:"
echo "❌ Listes déroulantes dynamiques région→site→secteur"
echo "❌ Boutons conversion coordonnées GPS↔LV95"
echo "❌ Ordre champs dans formulaire secteurs"
echo "❌ Système de cotation dans formulaire routes"
echo ""