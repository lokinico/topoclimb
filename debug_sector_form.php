<?php
echo "=== DEBUG FORMULAIRE SECTEUR ===\n\n";

// Démarrer serveur
exec('pkill -f "php.*8000" 2>/dev/null');
sleep(1);
$cmd = '/home/nibaechl/.config/herd-lite/bin/php -S localhost:8000 -t public/ > /dev/null 2>&1 &';
exec($cmd);
sleep(2);

// 1. Test login admin
echo "🔐 Test login admin...\n";
$login_html = file_get_contents('http://localhost:8000/login');
echo "Login page: " . strlen($login_html) . " caractères\n";

// 2. Extraire CSRF
if (preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $login_html, $matches)) {
    $csrf_token = $matches[1];
    echo "CSRF token: " . substr($csrf_token, 0, 10) . "...\n";
    
    // 3. Se connecter
    $post_data = http_build_query([
        'email' => 'admin@topoclimbch.com',
        'password' => 'admin123',
        'csrf_token' => $csrf_token
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Debug/1.0'
            ],
            'content' => $post_data
        ]
    ]);
    
    $response = file_get_contents('http://localhost:8000/login', false, $context);
    
    $session = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (strpos($header, 'Set-Cookie:') === 0 && strpos($header, 'PHPSESSID=') !== false) {
                if (preg_match('/PHPSESSID=([^;]+)/', $header, $matches)) {
                    $session = 'PHPSESSID=' . $matches[1];
                    echo "Session obtenue: " . substr($matches[1], 0, 10) . "...\n";
                    break;
                }
            }
        }
    }
    
    if ($session) {
        // 4. Accéder au formulaire secteur avec session
        echo "\n📝 Accès formulaire secteur avec session...\n";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Debug/1.0',
                    'Cookie: ' . $session
                ],
                'timeout' => 10
            ]
        ]);
        
        $sector_html = file_get_contents('http://localhost:8000/sectors/create', false, $context);
        echo "Formulaire reçu: " . strlen($sector_html) . " caractères\n";
        
        // 5. Analyser le contenu
        if (strpos($sector_html, 'Connexion') !== false) {
            echo "❌ PROBLÈME: Redirigé vers login\n";
        } elseif (strpos($sector_html, '<form') !== false) {
            echo "✅ Formulaire présent\n";
            
            // Compter les champs input
            $input_count = substr_count($sector_html, '<input');
            echo "Inputs trouvés: $input_count\n";
            
            // Chercher champs spécifiques
            $champs_a_chercher = ['name', 'code', 'description', 'region_id', 'csrf_token', 'altitude'];
            echo "\n🔍 Recherche champs spécifiques:\n";
            
            foreach ($champs_a_chercher as $champ) {
                if (preg_match('/name=["\']' . $champ . '["\']/i', $sector_html)) {
                    echo "  ✅ $champ: TROUVÉ\n";
                } else {
                    echo "  ❌ $champ: MANQUANT\n";
                }
            }
            
            // Chercher balises textarea
            $textarea_count = substr_count($sector_html, '<textarea');
            echo "\nTextareas: $textarea_count\n";
            
            // Chercher select
            $select_count = substr_count($sector_html, '<select');
            echo "Selects: $select_count\n";
            
            // Afficher début du formulaire pour debug
            if (preg_match('/<form[^>]*>/', $sector_html, $form_match)) {
                echo "\nBalise form trouvée:\n" . $form_match[0] . "\n";
            }
            
            // Sauvegarder un échantillon pour debug
            $sample = substr($sector_html, 0, 2000);
            file_put_contents('/tmp/sector_form_sample.html', $sample);
            echo "\nÉchantillon sauvé dans /tmp/sector_form_sample.html\n";
            
        } else {
            echo "❌ PROBLÈME: Pas de formulaire dans la réponse\n";
            // Sauvegarder pour debug
            file_put_contents('/tmp/sector_response_debug.html', $sector_html);
            echo "Réponse sauvée dans /tmp/sector_response_debug.html\n";
        }
    } else {
        echo "❌ Impossible d'obtenir session\n";
    }
} else {
    echo "❌ CSRF token non trouvé dans login\n";
}