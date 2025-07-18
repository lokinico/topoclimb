#!/bin/bash

# Script simple pour tester les routes en production
# Usage: ./test_production_simple.sh [URL_BASE]

BASE_URL=${1:-"https://topoclimb.ch"}
OUTPUT_FILE="routes_test_$(date +%Y%m%d_%H%M%S).txt"

echo "🧪 Test rapide des routes TopoclimbCH"
echo "====================================="
echo "URL: $BASE_URL"
echo "Date: $(date)"
echo ""

# Liste des routes principales à tester
declare -a routes=(
    "GET:/:200"
    "GET:/map:200"
    "GET:/regions:200"
    "GET:/sites:200"
    "GET:/sectors:200"
    "GET:/routes:200"
    "GET:/equipment:200"
    "GET:/checklists:200"
    "GET:/books:200"
    "GET:/login:200"
    "GET:/register:200"
    "GET:/api/regions:200"
    "GET:/api/sites:200"
    "GET:/api/map/sites:200"
    "GET:/contact:200"
    "GET:/about:200"
    "GET:/route-inexistante:404"
)

total_tests=${#routes[@]}
successful_tests=0
failed_tests=0

# Créer le fichier de résultats
echo "Test des routes TopoclimbCH" > $OUTPUT_FILE
echo "============================" >> $OUTPUT_FILE
echo "URL: $BASE_URL" >> $OUTPUT_FILE
echo "Date: $(date)" >> $OUTPUT_FILE
echo "" >> $OUTPUT_FILE

for i in "${!routes[@]}"; do
    IFS=':' read -r method path expected_status <<< "${routes[$i]}"
    url="$BASE_URL$path"
    
    printf "[%d/%d] Testing %s %s ... " $((i+1)) $total_tests "$method" "$path"
    
    # Effectuer la requête
    start_time=$(date +%s%N)
    
    if command -v curl >/dev/null 2>&1; then
        # Utiliser curl si disponible
        response=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url" --max-time 10)
        curl_exit_code=$?
    else
        # Fallback vers wget
        response=$(wget -q -O /dev/null -S "$url" 2>&1 | grep "HTTP/" | tail -1 | awk '{print $2}')
        curl_exit_code=$?
    fi
    
    end_time=$(date +%s%N)
    duration=$(( (end_time - start_time) / 1000000 )) # en millisecondes
    
    # Vérifier le résultat
    if [ "$curl_exit_code" -eq 0 ] && [ "$response" = "$expected_status" ]; then
        echo -e "\033[32m✅ $response\033[0m (\033[33m${duration}ms\033[0m)"
        echo "✅ [$method] $path - Status: $response (${duration}ms)" >> $OUTPUT_FILE
        successful_tests=$((successful_tests + 1))
    else
        echo -e "\033[31m❌ $response\033[0m (\033[33m${duration}ms\033[0m)"
        echo "❌ [$method] $path - Status: $response (${duration}ms)" >> $OUTPUT_FILE
        failed_tests=$((failed_tests + 1))
    fi
    
    # Petite pause
    sleep 0.1
done

success_rate=$(( (successful_tests * 100) / total_tests ))

echo ""
echo -e "\033[34m📊 Résultats finaux\033[0m"
echo -e "\033[34m==================\033[0m"
echo -e "Tests réussis: \033[32m$successful_tests/$total_tests\033[0m (\033[32m$success_rate%\033[0m)"
echo -e "Tests échoués: \033[31m$failed_tests\033[0m"

# Ajouter au fichier de résultats
echo "" >> $OUTPUT_FILE
echo "=== RÉSULTATS FINAUX ===" >> $OUTPUT_FILE
echo "Tests réussis: $successful_tests/$total_tests ($success_rate%)" >> $OUTPUT_FILE
echo "Tests échoués: $failed_tests" >> $OUTPUT_FILE

echo ""
echo -e "\033[34m💾 Résultats sauvegardés dans: $OUTPUT_FILE\033[0m"

# Recommandations
echo ""
echo -e "\033[34m💡 Recommandations:\033[0m"
if [ $success_rate -ge 90 ]; then
    echo -e "\033[32m✅ Excellent! Plus de 90% des routes fonctionnent.\033[0m"
elif [ $success_rate -ge 75 ]; then
    echo -e "\033[33m⚠️  Bon état mais à améliorer. Quelques routes posent problème.\033[0m"
else
    echo -e "\033[31m❌ Problèmes critiques détectés. Intervention nécessaire.\033[0m"
fi

echo ""
echo "🔧 Pour lancer le test complet avec plus de détails:"
echo "   php test_production_routes.php"
echo ""
echo "Test terminé! 🎉"