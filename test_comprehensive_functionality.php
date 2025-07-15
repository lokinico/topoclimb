<?php
/**
 * Script de test complet pour TopoclimbCH
 * Teste toutes les fonctionnalités, formulaires, pages et APIs
 */

require_once __DIR__ . '/bootstrap.php';

class ComprehensiveFunctionalityTest
{
    private $baseUrl;
    private $testResults = [];
    private $testUser = null;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000';
        $this->testResults = [
            'forms' => [],
            'pages' => [],
            'crud_operations' => [],
            'authentication' => [],
            'weather_integration' => [],
            'media_management' => [],
            'api_endpoints' => [],
            'summary' => [
                'total_tests' => 0,
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0
            ]
        ];
    }

    public function runAllTests(): void
    {
        echo "🚀 Démarrage des tests complets TopoclimbCH\n";
        echo "===========================================\n\n";

        // Test des pages publiques
        $this->testPublicPages();
        
        // Test du système d'authentification
        $this->testAuthenticationSystem();
        
        // Test des formulaires et CRUD
        $this->testCrudOperations();
        
        // Test de l'intégration météo
        $this->testWeatherIntegration();
        
        // Test de la gestion des médias
        $this->testMediaManagement();
        
        // Test des APIs
        $this->testApiEndpoints();
        
        // Test des permissions
        $this->testPermissionSystem();
        
        // Afficher les résultats
        $this->displayResults();
    }

    private function testPublicPages(): void
    {
        echo "📄 Test des pages publiques...\n";
        
        $publicPages = [
            '/' => 'Page d\'accueil',
            '/login' => 'Page de connexion',
            '/register' => 'Page d\'inscription',
            '/forgot-password' => 'Mot de passe oublié',
            '/about' => 'À propos',
            '/contact' => 'Contact',
            '/privacy' => 'Politique de confidentialité',
            '/terms' => 'Conditions d\'utilisation'
        ];

        foreach ($publicPages as $path => $description) {
            $result = $this->testPage($path, $description);
            $this->testResults['pages'][] = $result;
        }
        
        echo "\n";
    }

    private function testAuthenticationSystem(): void
    {
        echo "🔐 Test du système d'authentification...\n";
        
        // Test des formulaires d'authentification
        $authForms = [
            '/login' => [
                'description' => 'Formulaire de connexion',
                'fields' => ['email', 'password', 'csrf_token'],
                'method' => 'POST'
            ],
            '/register' => [
                'description' => 'Formulaire d\'inscription',
                'fields' => ['email', 'password', 'password_confirmation', 'prenom', 'nom', 'username', 'csrf_token'],
                'method' => 'POST'
            ],
            '/forgot-password' => [
                'description' => 'Formulaire mot de passe oublié',
                'fields' => ['email', 'csrf_token'],
                'method' => 'POST'
            ]
        ];

        foreach ($authForms as $path => $config) {
            $result = $this->testForm($path, $config);
            $this->testResults['forms'][] = $result;
        }
        
        echo "\n";
    }

    private function testCrudOperations(): void
    {
        echo "📝 Test des opérations CRUD...\n";
        
        $crudEntities = [
            'regions' => [
                'create' => '/regions/create',
                'index' => '/regions',
                'show' => '/regions/{id}',
                'edit' => '/regions/{id}/edit',
                'fields' => ['name', 'description', 'latitude', 'longitude', 'canton']
            ],
            'sites' => [
                'create' => '/sites/create',
                'index' => '/sites',
                'show' => '/sites/{id}',
                'edit' => '/sites/{id}/edit',
                'fields' => ['name', 'description', 'region_id', 'latitude', 'longitude']
            ],
            'sectors' => [
                'create' => '/sectors/create',
                'index' => '/sectors',
                'show' => '/sectors/{id}',
                'edit' => '/sectors/{id}/edit',
                'fields' => ['name', 'description', 'site_id', 'difficulty_min', 'difficulty_max']
            ],
            'routes' => [
                'create' => '/routes/create',
                'index' => '/routes',
                'show' => '/routes/{id}',
                'edit' => '/routes/{id}/edit',
                'fields' => ['name', 'description', 'sector_id', 'difficulty_grade_id', 'length']
            ],
            'books' => [
                'create' => '/books/create',
                'index' => '/books',
                'show' => '/books/{id}',
                'edit' => '/books/{id}/edit',
                'fields' => ['title', 'description', 'author', 'publication_year']
            ]
        ];

        foreach ($crudEntities as $entity => $config) {
            $this->testCrudEntity($entity, $config);
        }
        
        echo "\n";
    }

    private function testWeatherIntegration(): void
    {
        echo "🌤️ Test de l'intégration météo...\n";
        
        try {
            // Test du WeatherService
            $weatherService = new \TopoclimbCH\Services\WeatherService(
                \TopoclimbCH\Core\Database::getInstance()
            );
            
            // Test coordinates (Bern)
            $lat = 46.9481;
            $lng = 7.4474;
            
            $tests = [
                'health_check' => function() use ($weatherService) {
                    return $weatherService->healthCheck();
                },
                'current_weather' => function() use ($weatherService, $lat, $lng) {
                    return $weatherService->getCurrentWeather($lat, $lng);
                },
                'detailed_weather' => function() use ($weatherService, $lat, $lng) {
                    return $weatherService->getDetailedWeather($lat, $lng);
                },
                'forecast' => function() use ($weatherService, $lat, $lng) {
                    return $weatherService->getForecast($lat, $lng);
                }
            ];
            
            foreach ($tests as $testName => $testFunction) {
                try {
                    $result = $testFunction();
                    $this->testResults['weather_integration'][] = [
                        'test' => $testName,
                        'status' => 'success',
                        'message' => 'Test météo réussi',
                        'data' => is_array($result) ? array_keys($result) : $result
                    ];
                    echo "  ✅ {$testName}: OK\n";
                } catch (Exception $e) {
                    $this->testResults['weather_integration'][] = [
                        'test' => $testName,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    echo "  ❌ {$testName}: {$e->getMessage()}\n";
                }
            }
            
        } catch (Exception $e) {
            $this->testResults['weather_integration'][] = [
                'test' => 'weather_service_init',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            echo "  ❌ Initialisation WeatherService: {$e->getMessage()}\n";
        }
        
        echo "\n";
    }

    private function testMediaManagement(): void
    {
        echo "📸 Test de la gestion des médias...\n";
        
        $mediaTests = [
            'media_index' => '/media',
            'media_upload_form' => '/regions/1/media',
            'media_service_availability' => 'MediaService'
        ];

        foreach ($mediaTests as $test => $path) {
            if ($test === 'media_service_availability') {
                $this->testMediaService();
            } else {
                $result = $this->testPage($path, "Test {$test}");
                $this->testResults['media_management'][] = $result;
            }
        }
        
        echo "\n";
    }

    private function testApiEndpoints(): void
    {
        echo "🔌 Test des endpoints API...\n";
        
        $apiEndpoints = [
            '/api/regions' => 'Liste des régions',
            '/api/regions/search' => 'Recherche de régions',
            '/api/sites' => 'Liste des sites',
            '/api/sites/search' => 'Recherche de sites',
            '/api/map/sites' => 'Sites pour la carte',
            '/api/map/search' => 'Recherche géographique',
            '/api/books/search' => 'Recherche de guides'
        ];

        foreach ($apiEndpoints as $endpoint => $description) {
            $result = $this->testApiEndpoint($endpoint, $description);
            $this->testResults['api_endpoints'][] = $result;
        }
        
        echo "\n";
    }

    private function testPermissionSystem(): void
    {
        echo "🛡️ Test du système de permissions...\n";
        
        $permissionTests = [
            'admin_access' => '/admin',
            'user_profile' => '/profile',
            'user_settings' => '/settings',
            'admin_users' => '/admin/users'
        ];

        foreach ($permissionTests as $test => $path) {
            $result = $this->testProtectedPage($path, $test);
            $this->testResults['authentication'][] = $result;
        }
        
        echo "\n";
    }

    private function testPage(string $path, string $description): array
    {
        $this->testResults['summary']['total_tests']++;
        
        try {
            $url = $this->baseUrl . $path;
            $response = $this->makeHttpRequest($url);
            
            if ($response['status_code'] >= 200 && $response['status_code'] < 400) {
                echo "  ✅ {$description}: OK (HTTP {$response['status_code']})\n";
                $this->testResults['summary']['passed']++;
                return [
                    'path' => $path,
                    'description' => $description,
                    'status' => 'success',
                    'status_code' => $response['status_code']
                ];
            } else {
                echo "  ❌ {$description}: Erreur HTTP {$response['status_code']}\n";
                $this->testResults['summary']['failed']++;
                return [
                    'path' => $path,
                    'description' => $description,
                    'status' => 'error',
                    'status_code' => $response['status_code'],
                    'error' => $response['error'] ?? 'HTTP Error'
                ];
            }
        } catch (Exception $e) {
            echo "  ❌ {$description}: {$e->getMessage()}\n";
            $this->testResults['summary']['failed']++;
            return [
                'path' => $path,
                'description' => $description,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function testForm(string $path, array $config): array
    {
        $this->testResults['summary']['total_tests']++;
        
        try {
            $url = $this->baseUrl . $path;
            $response = $this->makeHttpRequest($url);
            
            if ($response['status_code'] === 200) {
                $html = $response['body'];
                $missingFields = [];
                
                foreach ($config['fields'] as $field) {
                    if (strpos($html, "name=\"{$field}\"") === false) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    echo "  ✅ {$config['description']}: Tous les champs présents\n";
                    $this->testResults['summary']['passed']++;
                    return [
                        'path' => $path,
                        'description' => $config['description'],
                        'status' => 'success',
                        'fields_found' => $config['fields']
                    ];
                } else {
                    echo "  ⚠️ {$config['description']}: Champs manquants: " . implode(', ', $missingFields) . "\n";
                    $this->testResults['summary']['warnings']++;
                    return [
                        'path' => $path,
                        'description' => $config['description'],
                        'status' => 'warning',
                        'missing_fields' => $missingFields
                    ];
                }
            } else {
                echo "  ❌ {$config['description']}: Erreur HTTP {$response['status_code']}\n";
                $this->testResults['summary']['failed']++;
                return [
                    'path' => $path,
                    'description' => $config['description'],
                    'status' => 'error',
                    'status_code' => $response['status_code']
                ];
            }
        } catch (Exception $e) {
            echo "  ❌ {$config['description']}: {$e->getMessage()}\n";
            $this->testResults['summary']['failed']++;
            return [
                'path' => $path,
                'description' => $config['description'],
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function testCrudEntity(string $entity, array $config): void
    {
        echo "  📁 Test CRUD {$entity}:\n";
        
        // Test Index (Read)
        $indexResult = $this->testPage($config['index'], "Liste {$entity}");
        $this->testResults['crud_operations'][] = $indexResult;
        
        // Test Create form
        $createResult = $this->testPage($config['create'], "Formulaire création {$entity}");
        $this->testResults['crud_operations'][] = $createResult;
        
        // Test form fields if create page is accessible
        if ($createResult['status'] === 'success') {
            $formConfig = [
                'description' => "Champs formulaire {$entity}",
                'fields' => array_merge($config['fields'], ['_token']),
                'method' => 'POST'
            ];
            $fieldsResult = $this->testForm($config['create'], $formConfig);
            $this->testResults['crud_operations'][] = $fieldsResult;
        }
    }

    private function testApiEndpoint(string $endpoint, string $description): array
    {
        $this->testResults['summary']['total_tests']++;
        
        try {
            $url = $this->baseUrl . $endpoint;
            $response = $this->makeHttpRequest($url, ['Accept: application/json']);
            
            if ($response['status_code'] === 200) {
                $data = json_decode($response['body'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "  ✅ {$description}: JSON valide\n";
                    $this->testResults['summary']['passed']++;
                    return [
                        'endpoint' => $endpoint,
                        'description' => $description,
                        'status' => 'success',
                        'response_type' => 'json'
                    ];
                } else {
                    echo "  ⚠️ {$description}: Réponse non-JSON\n";
                    $this->testResults['summary']['warnings']++;
                    return [
                        'endpoint' => $endpoint,
                        'description' => $description,
                        'status' => 'warning',
                        'error' => 'Invalid JSON response'
                    ];
                }
            } else {
                echo "  ❌ {$description}: HTTP {$response['status_code']}\n";
                $this->testResults['summary']['failed']++;
                return [
                    'endpoint' => $endpoint,
                    'description' => $description,
                    'status' => 'error',
                    'status_code' => $response['status_code']
                ];
            }
        } catch (Exception $e) {
            echo "  ❌ {$description}: {$e->getMessage()}\n";
            $this->testResults['summary']['failed']++;
            return [
                'endpoint' => $endpoint,
                'description' => $description,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function testProtectedPage(string $path, string $test): array
    {
        $this->testResults['summary']['total_tests']++;
        
        try {
            $url = $this->baseUrl . $path;
            $response = $this->makeHttpRequest($url);
            
            // Protected pages should redirect to login (302) or show 403
            if ($response['status_code'] === 302 || $response['status_code'] === 403) {
                echo "  ✅ {$test}: Protection active (HTTP {$response['status_code']})\n";
                $this->testResults['summary']['passed']++;
                return [
                    'path' => $path,
                    'test' => $test,
                    'status' => 'success',
                    'status_code' => $response['status_code'],
                    'message' => 'Page correctement protégée'
                ];
            } else {
                echo "  ❌ {$test}: Page non protégée (HTTP {$response['status_code']})\n";
                $this->testResults['summary']['failed']++;
                return [
                    'path' => $path,
                    'test' => $test,
                    'status' => 'error',
                    'status_code' => $response['status_code'],
                    'message' => 'Page devrait être protégée'
                ];
            }
        } catch (Exception $e) {
            echo "  ❌ {$test}: {$e->getMessage()}\n";
            $this->testResults['summary']['failed']++;
            return [
                'path' => $path,
                'test' => $test,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function testMediaService(): void
    {
        try {
            $mediaService = new \TopoclimbCH\Services\MediaService(
                \TopoclimbCH\Core\Database::getInstance()
            );
            
            echo "  ✅ MediaService: Disponible\n";
            $this->testResults['media_management'][] = [
                'test' => 'media_service_init',
                'status' => 'success',
                'message' => 'MediaService initialisé correctement'
            ];
        } catch (Exception $e) {
            echo "  ❌ MediaService: {$e->getMessage()}\n";
            $this->testResults['media_management'][] = [
                'test' => 'media_service_init',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function makeHttpRequest(string $url, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", array_merge([
                    'User-Agent: TopoclimbCH-Test/1.0'
                ], $headers)),
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Impossible de se connecter à {$url}");
        }
        
        $statusCode = 200;
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }
        
        return [
            'status_code' => $statusCode,
            'body' => $response,
            'headers' => $http_response_header ?? []
        ];
    }

    private function displayResults(): void
    {
        echo "\n";
        echo "📊 RÉSULTATS DES TESTS\n";
        echo "=====================\n\n";
        
        $summary = $this->testResults['summary'];
        echo "Total des tests: {$summary['total_tests']}\n";
        echo "Réussis: {$summary['passed']} ✅\n";
        echo "Avertissements: {$summary['warnings']} ⚠️\n";
        echo "Échecs: {$summary['failed']} ❌\n\n";
        
        $successRate = $summary['total_tests'] > 0 ? 
            round(($summary['passed'] / $summary['total_tests']) * 100, 1) : 0;
        echo "Taux de réussite: {$successRate}%\n\n";
        
        // Analyse détaillée
        $this->displayDetailedAnalysis();
        
        // Recommandations
        $this->displayRecommendations();
        
        // Sauvegarde des résultats
        $this->saveResults();
    }

    private function displayDetailedAnalysis(): void
    {
        echo "🔍 ANALYSE DÉTAILLÉE\n";
        echo "===================\n\n";
        
        // Analyse par catégorie
        $categories = [
            'pages' => 'Pages publiques',
            'forms' => 'Formulaires',
            'crud_operations' => 'Opérations CRUD',
            'authentication' => 'Authentification',
            'weather_integration' => 'Intégration météo',
            'media_management' => 'Gestion médias',
            'api_endpoints' => 'Endpoints API'
        ];
        
        foreach ($categories as $key => $title) {
            $tests = $this->testResults[$key];
            if (!empty($tests)) {
                $passed = count(array_filter($tests, function($t) { return $t['status'] === 'success'; }));
                $total = count($tests);
                echo "{$title}: {$passed}/{$total} ✅\n";
                
                // Afficher les erreurs
                $errors = array_filter($tests, function($t) { return $t['status'] === 'error'; });
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $desc = isset($error['description']) ? $error['description'] : (isset($error['test']) ? $error['test'] : 'Test');
                        $err = isset($error['error']) ? $error['error'] : 'Erreur inconnue';
                        echo "  ❌ {$desc}: {$err}\n";
                    }
                }
                echo "\n";
            }
        }
    }

    private function displayRecommendations(): void
    {
        echo "💡 RECOMMANDATIONS\n";
        echo "=================\n\n";
        
        $recommendations = [];
        
        // Analyser les résultats pour générer des recommandations
        if ($this->testResults['summary']['failed'] > 0) {
            $recommendations[] = "Corriger les {$this->testResults['summary']['failed']} tests en échec avant la mise en production.";
        }
        
        if ($this->testResults['summary']['warnings'] > 0) {
            $recommendations[] = "Vérifier les {$this->testResults['summary']['warnings']} avertissements pour améliorer la qualité.";
        }
        
        // Recommandations spécifiques
        $weatherErrors = array_filter($this->testResults['weather_integration'], function($t) { return $t['status'] === 'error'; });
        if (!empty($weatherErrors)) {
            $recommendations[] = "Vérifier la configuration de l'API météo MeteoSwiss.";
        }
        
        $apiErrors = array_filter($this->testResults['api_endpoints'], function($t) { return $t['status'] === 'error'; });
        if (!empty($apiErrors)) {
            $recommendations[] = "Implémenter les endpoints API manquants pour l'application mobile.";
        }
        
        $authErrors = array_filter($this->testResults['authentication'], function($t) { return $t['status'] === 'error'; });
        if (!empty($authErrors)) {
            $recommendations[] = "Renforcer le système d'authentification et de permissions.";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Excellent ! Le système semble bien configuré. Continuer les tests réguliers.";
        }
        
        foreach ($recommendations as $i => $recommendation) {
            echo ($i + 1) . ". {$recommendation}\n";
        }
        
        echo "\n";
    }

    private function saveResults(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "test_results_{$timestamp}.json";
        
        $fullResults = [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'summary' => $this->testResults['summary'],
            'details' => $this->testResults
        ];
        
        file_put_contents($filename, json_encode($fullResults, JSON_PRETTY_PRINT));
        echo "💾 Résultats sauvegardés dans: {$filename}\n\n";
    }
}

// Exécution des tests
try {
    $tester = new ComprehensiveFunctionalityTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "💥 Erreur critique: {$e->getMessage()}\n";
    exit(1);
}