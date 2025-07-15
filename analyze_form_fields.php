<?php

require_once 'bootstrap.php';

echo "🔍 Analyse des champs de formulaires\n";
echo "====================================\n\n";

class FormFieldAnalyzer {
    private $baseUrl = 'http://localhost:8000';
    
    public function analyzeFields(string $path, string $description) {
        echo "📋 Analyse : $description ($path)\n";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                // Extraire les champs input
                preg_match_all('/<input[^>]*name="([^"]*)"[^>]*>/', $response, $matches);
                $inputFields = $matches[1] ?? [];
                
                // Extraire les champs select
                preg_match_all('/<select[^>]*name="([^"]*)"[^>]*>/', $response, $selectMatches);
                $selectFields = $selectMatches[1] ?? [];
                
                // Extraire les champs textarea
                preg_match_all('/<textarea[^>]*name="([^"]*)"[^>]*>/', $response, $textareaMatches);
                $textareaFields = $textareaMatches[1] ?? [];
                
                $allFields = array_merge($inputFields, $selectFields, $textareaFields);
                $allFields = array_unique($allFields);
                
                echo "Champs trouvés (" . count($allFields) . ") : " . implode(', ', $allFields) . "\n";
                
                // Filtrer les champs pertinents (exclure q, csrf_token, etc.)
                $relevantFields = array_filter($allFields, function($field) {
                    return !in_array($field, ['q', 'csrf_token', '_token']);
                });
                
                if (!empty($relevantFields)) {
                    echo "Champs pertinents : " . implode(', ', $relevantFields) . "\n";
                }
            } else {
                echo "❌ Erreur HTTP $httpCode\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

// Démarrer le serveur
echo "🚀 Démarrage du serveur...\n";
$serverCmd = 'php -S localhost:8000 -t ' . __DIR__ . ' > /dev/null 2>&1 & echo $!';
$serverPid = exec($serverCmd);

if ($serverPid) {
    echo "✅ Serveur démarré (PID: $serverPid)\n\n";
    sleep(2);
    
    $analyzer = new FormFieldAnalyzer();
    
    // Analyser tous les formulaires
    $forms = [
        '/test/regions/create' => 'Formulaire régions',
        '/test/sites/create' => 'Formulaire sites',
        '/test/sectors/create' => 'Formulaire secteurs',
        '/test/routes/create' => 'Formulaire routes',
        '/test/books/create' => 'Formulaire books'
    ];
    
    foreach ($forms as $path => $description) {
        $analyzer->analyzeFields($path, $description);
    }
    
    // Arrêter le serveur
    echo "🛑 Arrêt du serveur...\n";
    exec("kill $serverPid");
    
} else {
    echo "❌ Impossible de démarrer le serveur\n";
}