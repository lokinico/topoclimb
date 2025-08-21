#!/bin/bash

echo "=== TEST ACCÈS AUX FORMULAIRES WEB ==="
echo ""

# Configuration
BASE_URL="http://localhost:8000"
SESSION_ID=""

# Fonction pour tester l'accès à une page
test_form_access() {
    local form_name="$1"
    local url="$2"
    local expected_elements="$3"
    
    echo "🧪 TEST: $form_name"
    echo "URL: $url"
    
    # Test d'accès à la page
    response=$(timeout 10 curl -s "$BASE_URL$url" -w "%{http_code}" -o /tmp/form_test.html)
    http_code="${response: -3}"
    
    if [ "$http_code" == "200" ]; then
        echo "  ✅ Page accessible (HTTP 200)"
        
        # Vérifier la présence d'un formulaire
        if grep -q "<form" /tmp/form_test.html; then
            echo "  ✅ Formulaire trouvé"
            
            # Vérifier les éléments attendus
            for element in $expected_elements; do
                if grep -q "name=\"$element\"" /tmp/form_test.html; then
                    echo "  ✓ Champ '$element' présent"
                else
                    echo "  ❌ Champ '$element' manquant"
                fi
            done
            
            # Vérifier le bouton submit
            if grep -q "type=\"submit\"" /tmp/form_test.html; then
                echo "  ✅ Bouton de soumission présent"
            else
                echo "  ❌ Bouton de soumission manquant"
            fi
            
        else
            echo "  ❌ Aucun formulaire trouvé"
        fi
        
    elif [ "$http_code" == "302" ]; then
        echo "  ⚠️  Page redirige (HTTP 302) - peut nécessiter une authentification"
    else
        echo "  ❌ Erreur HTTP: $http_code"
    fi
    
    echo ""
}

echo "🚀 Test des formulaires de création..."
echo ""

# Test des formulaires de création
test_form_access "Régions - Création" "/regions/create" "name description country_id"
test_form_access "Sites - Création" "/sites/create" "name description region_id"
test_form_access "Secteurs - Création" "/sectors/create" "name description site_id"
test_form_access "Voies - Création" "/routes/create" "name description sector_id difficulty"
test_form_access "Guides - Création" "/books/create" "title author description"
test_form_access "Ascensions - Création" "/ascents/create" "route_id ascent_date"
test_form_access "Événements - Création" "/events/create" "title description event_date"
test_form_access "Upload Média" "/media/upload" "file"

echo "📊 Test des pages de liste (accessibilité)..."
echo ""

# Test des pages de liste
test_form_access "Liste Régions" "/regions" ""
test_form_access "Liste Sites" "/sites" ""
test_form_access "Liste Secteurs" "/sectors" ""
test_form_access "Liste Voies" "/routes" ""
test_form_access "Liste Guides" "/books" ""

echo "✅ Test terminé!"
echo ""
echo "📝 Notes:"
echo "  - Si pages redirigent (302), l'authentification est requise"
echo "  - Pour tester avec authentification, connectez-vous via le navigateur"
echo "  - Les formulaires devraient contenir tous les champs nécessaires"
echo ""