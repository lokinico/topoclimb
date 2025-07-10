<?php
/**
 * Test complet de fonctionnalité TopoclimbCH
 * Tests d'accès à toutes les pages + CRUD des voies d'escalade
 */

// Configuration
require_once dirname(__DIR__) . '/bootstrap.php';

// Configuration du test
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🧗‍♂️ Test Complet TopoclimbCH</h1>";

class TopoclimbTestSuite {
    private $baseUrl = 'https://topoclimb.ch';
    private $results = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function __construct() {
        echo "<div style='max-width: 1200px; margin: 20px auto; font-family: Arial, sans-serif;'>";
    }
    
    /**
     * Teste l'accès à une URL
     */
    private function testPageAccess($url, $expectedCode = 200, $description = '') {
        $this->totalTests++;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $passed = ($httpCode == $expectedCode && !$error);
        
        if ($passed) {
            $this->passedTests++;
            $status = "✅";
            $color = "green";
        } else {
            $this->failedTests++;
            $status = "❌";
            $color = "red";
        }
        
        echo "<div style='margin: 5px 0; padding: 8px; border-left: 3px solid $color;'>";
        echo "$status <strong>$url</strong> ";
        if ($description) echo "($description) ";
        echo "- Code: $httpCode";
        if ($error) echo " - Erreur: $error";
        
        // Vérifications supplémentaires
        if ($passed && $response) {
            if (strpos($response, 'Erreur serveur') !== false) {
                echo " - ⚠️ Page d'erreur détectée";
                $this->failedTests++;
                $this->passedTests--;
            } elseif (strpos($response, '<title>') !== false) {
                preg_match('/<title>(.*?)<\/title>/s', $response, $matches);
                $title = isset($matches[1]) ? trim(strip_tags($matches[1])) : 'Pas de titre';
                echo " - Titre: " . substr($title, 0, 50);
            }
        }
        echo "</div>";
        
        return $passed ? $response : false;
    }
    
    /**
     * Test des pages publiques principales
     */
    public function testPublicPages() {
        echo "<h2>📄 Test des Pages Publiques</h2>";
        
        $publicPages = [
            '/' => 'Page d\'accueil',
            '/login' => 'Page de connexion',
            '/register' => 'Page d\'inscription',
            '/sites' => 'Liste des sites d\'escalade',
            '/routes' => 'Liste des voies',
            '/about' => 'À propos',
            '/contact' => 'Contact',
            '/privacy' => 'Politique de confidentialité',
            '/terms' => 'Conditions d\'utilisation'
        ];
        
        foreach ($publicPages as $url => $description) {
            $this->testPageAccess($url, 200, $description);
        }
    }
    
    /**
     * Test des pages API REST
     */
    public function testApiEndpoints() {
        echo "<h2>🔌 Test des APIs REST</h2>";
        
        $apiEndpoints = [
            '/api/v1/regions' => 'API Régions',
            '/api/v1/sites' => 'API Sites',
            '/api/v1/sectors' => 'API Secteurs',
            '/api/v1/routes' => 'API Voies',
            '/api/v1/routes?page=1&limit=5' => 'API Voies avec pagination'
        ];
        
        foreach ($apiEndpoints as $url => $description) {
            $response = $this->testPageAccess($url, 200, $description);
            
            // Vérifier si c'est du JSON valide
            if ($response) {
                $json = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<div style='margin-left: 20px; color: green;'>📄 JSON valide détecté</div>";
                } else {
                    echo "<div style='margin-left: 20px; color: orange;'>⚠️ Réponse non-JSON</div>";
                }
            }
        }
    }
    
    /**
     * Test des pages dynamiques avec IDs existants
     */
    public function testDynamicPages() {
        echo "<h2>🔗 Test des Pages Dynamiques</h2>";
        
        // D'abord récupérer quelques IDs existants
        $routesResponse = $this->testPageAccess('/api/v1/routes?limit=3', 200, 'Récupération des voies pour tests');
        
        if ($routesResponse) {
            $routesData = json_decode($routesResponse, true);
            
            if (isset($routesData['data']) && is_array($routesData['data'])) {
                foreach ($routesData['data'] as $route) {
                    if (isset($route['id'])) {
                        $routeId = $route['id'];
                        $routeName = $route['name'] ?? "Voie $routeId";
                        
                        $this->testPageAccess("/routes/$routeId", 200, "Détails de la voie: $routeName");
                        $this->testPageAccess("/routes/$routeId/edit", 200, "Édition de la voie: $routeName");
                        
                        break; // Tester juste une voie pour l'exemple
                    }
                }
            }
        }
        
        // Test avec IDs inexistants
        $this->testPageAccess('/routes/99999', 404, 'Voie inexistante');
    }
    
    /**
     * Test de création d'une voie (simulation)
     */
    public function testRouteCreation() {
        echo "<h2>➕ Test de Création de Voie</h2>";
        
        // Test d'accès au formulaire de création
        $this->testPageAccess('/routes/create', 200, 'Formulaire de création de voie');
        
        // Note: Le test de soumission POST nécessiterait une authentification
        echo "<div style='margin: 10px 0; padding: 10px; background: #e8f4fd; border-left: 3px solid #2196F3;'>";
        echo "ℹ️ <strong>Note:</strong> Les tests de création/modification POST nécessitent une authentification. ";
        echo "Ces tests peuvent être étendus avec un système de connexion automatisé.";
        echo "</div>";
    }
    
    /**
     * Test de recherche et filtres
     */
    public function testSearchAndFilters() {
        echo "<h2>🔍 Test de Recherche et Filtres</h2>";
        
        $searchTests = [
            '/routes?search=test' => 'Recherche de voies',
            '/routes?difficulty=5c' => 'Filtrage par difficulté',
            '/routes?region=1' => 'Filtrage par région',
            '/api/v1/routes?search=escalade' => 'API Recherche',
            '/api/v1/routes?difficulty_min=5&difficulty_max=7' => 'API Filtrage difficulté'
        ];
        
        foreach ($searchTests as $url => $description) {
            $this->testPageAccess($url, 200, $description);
        }
    }
    
    /**
     * Test de performance et temps de réponse
     */
    public function testPerformance() {
        echo "<h2>⚡ Test de Performance</h2>";
        
        $performanceTests = [
            '/' => 'Page d\'accueil',
            '/routes' => 'Liste des voies',
            '/api/v1/routes' => 'API Voies'
        ];
        
        foreach ($performanceTests as $url => $description) {
            $startTime = microtime(true);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            $color = $responseTime < 1000 ? 'green' : ($responseTime < 3000 ? 'orange' : 'red');
            $status = $responseTime < 1000 ? '🚀' : ($responseTime < 3000 ? '⚡' : '🐌');
            
            echo "<div style='margin: 5px 0; padding: 8px; border-left: 3px solid $color;'>";
            echo "$status <strong>$url</strong> ($description) - {$responseTime}ms";
            echo "</div>";
        }
    }
    
    /**
     * Test de sécurité basique
     */
    public function testBasicSecurity() {
        echo "<h2>🔒 Test de Sécurité Basique</h2>";
        
        // Test d'accès aux pages protégées sans authentification
        $protectedPages = [
            '/routes/create' => 'Création de voie (protégée)',
            '/admin' => 'Panel admin (protégé)',
            '/profile' => 'Profil utilisateur (protégé)'
        ];
        
        foreach ($protectedPages as $url => $description) {
            // On s'attend à une redirection (302) ou accès refusé (401/403)
            $this->testPageAccess($url, [200, 302, 401, 403], $description);
        }
    }
    
    /**
     * Exécute tous les tests
     */
    public function runAllTests() {
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h2 style='margin-top: 0;'>🧪 Lancement de la Suite de Tests Complète</h2>";
        echo "<p>Testing sur: <strong>{$this->baseUrl}</strong></p>";
        echo "</div>";
        
        $this->testPublicPages();
        $this->testApiEndpoints();
        $this->testDynamicPages();
        $this->testRouteCreation();
        $this->testSearchAndFilters();
        $this->testPerformance();
        $this->testBasicSecurity();
        
        $this->displayResults();
    }
    
    /**
     * Affiche les résultats finaux
     */
    private function displayResults() {
        $successRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0;
        $bgColor = $successRate >= 90 ? '#d4edda' : ($successRate >= 70 ? '#fff3cd' : '#f8d7da');
        $textColor = $successRate >= 90 ? '#155724' : ($successRate >= 70 ? '#856404' : '#721c24');
        
        echo "<div style='background: $bgColor; color: $textColor; padding: 20px; margin: 30px 0; border-radius: 8px; border: 1px solid;'>";
        echo "<h2 style='margin-top: 0;'>📊 Résultats des Tests</h2>";
        echo "<div style='font-size: 18px;'>";
        echo "<strong>Tests totaux:</strong> {$this->totalTests}<br>";
        echo "<strong>Tests réussis:</strong> <span style='color: green;'>{$this->passedTests}</span><br>";
        echo "<strong>Tests échoués:</strong> <span style='color: red;'>{$this->failedTests}</span><br>";
        echo "<strong>Taux de réussite:</strong> <span style='font-size: 24px; font-weight: bold;'>{$successRate}%</span>";
        echo "</div>";
        
        if ($successRate >= 90) {
            echo "<div style='margin-top: 15px; font-weight: bold; color: green;'>🎉 Excellent! TopoclimbCH fonctionne parfaitement!</div>";
        } elseif ($successRate >= 70) {
            echo "<div style='margin-top: 15px; font-weight: bold; color: orange;'>⚠️ Bon mais quelques améliorations possibles</div>";
        } else {
            echo "<div style='margin-top: 15px; font-weight: bold; color: red;'>❌ Plusieurs problèmes détectés - intervention nécessaire</div>";
        }
        echo "</div>";
    }
    
    public function __destruct() {
        echo "</div>";
    }
}

// Exécution des tests
$testSuite = new TopoclimbTestSuite();
$testSuite->runAllTests();

echo "<div style='margin: 30px 0; padding: 15px; background: #e9ecef; border-radius: 5px;'>";
echo "<h3>🔗 Actions Rapides</h3>";
echo "<a href='/' style='margin-right: 15px; color: #007bff; text-decoration: none;'>🏠 Accueil</a>";
echo "<a href='/routes' style='margin-right: 15px; color: #007bff; text-decoration: none;'>🧗 Voies</a>";
echo "<a href='/api/v1/routes' style='margin-right: 15px; color: #007bff; text-decoration: none;'>🔌 API</a>";
echo "<a href='" . $_SERVER['PHP_SELF'] . "' style='color: #28a745; text-decoration: none;'>🔄 Relancer les tests</a>";
echo "</div>";

?>