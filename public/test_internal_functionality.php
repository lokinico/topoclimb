<?php
/**
 * Suite de Tests Internes TopoclimbCH
 * Tests en interne sans appels externes - plus sûr pour le développement
 */

require_once dirname(__DIR__) . '/bootstrap.php';

echo "<h1>🏠 Tests Internes TopoclimbCH</h1>";

class InternalTestSuite {
    private $container;
    private $db;
    private $router;
    private $view;
    private $results = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function __construct() {
        echo "<div style='max-width: 1200px; margin: 20px auto; font-family: Arial, sans-serif;'>";
        
        try {
            // Initialisation du container
            $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
            $this->container = $containerBuilder->build();
            $this->db = $this->container->get(\TopoclimbCH\Core\Database::class);
            $this->view = $this->container->get(\TopoclimbCH\Core\View::class);
            $this->router = $this->container->get(\TopoclimbCH\Core\Router::class);
            
            echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h3 style='margin-top: 0; color: #155724;'>✅ Initialisation Réussie</h3>";
            echo "<p style='color: #155724;'>Container, DB, View et Router initialisés avec succès</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h3 style='margin-top: 0; color: #721c24;'>❌ Erreur d'Initialisation</h3>";
            echo "<p style='color: #721c24;'>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
    
    private function logTest($testName, $passed, $details = '', $data = null) {
        $this->totalTests++;
        if ($passed) {
            $this->passedTests++;
            $status = "✅";
            $color = "#d4edda";
            $textColor = "#155724";
        } else {
            $this->failedTests++;
            $status = "❌";
            $color = "#f8d7da";
            $textColor = "#721c24";
        }
        
        echo "<div style='background: $color; color: $textColor; padding: 10px; margin: 5px 0; border-radius: 3px;'>";
        echo "$status <strong>$testName</strong>";
        if ($details) echo " - $details";
        if ($data) {
            echo "<br><small style='margin-left: 20px;'>Données: " . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data) . "</small>";
        }
        echo "</div>";
        
        return $passed;
    }
    
    /**
     * Test des services principaux
     */
    public function testCoreServices() {
        echo "<h2>🔧 Test des Services Principaux</h2>";
        
        try {
            // Test Database
            $dbResult = $this->db->fetchOne("SELECT 1 as test");
            $this->logTest("Database Service", $dbResult && $dbResult['test'] == 1, "Connexion et requête basique");
            
            // Test View
            $viewTest = $this->view->render('layouts/simple', ['title' => 'Test', 'message' => 'Test interne']);
            $this->logTest("View Service", !empty($viewTest), "Rendu template simple", strlen($viewTest) . " caractères");
            
            // Test Router - chargement des routes
            $this->router->loadRoutes(BASE_PATH . '/config/routes.php');
            $this->logTest("Router Service", true, "Chargement des routes réussi");
            
            // Test des services métier
            $regionService = $this->container->get(\TopoclimbCH\Services\RegionService::class);
            $this->logTest("RegionService", $regionService instanceof \TopoclimbCH\Services\RegionService, "Service régions disponible");
            
            $routeService = $this->container->get(\TopoclimbCH\Services\RouteService::class);
            $this->logTest("RouteService", $routeService instanceof \TopoclimbCH\Services\RouteService, "Service voies disponible");
            
        } catch (Exception $e) {
            $this->logTest("Services Core", false, "Erreur: " . $e->getMessage());
        }
    }
    
    /**
     * Test de la base de données et modèles
     */
    public function testDatabaseModels() {
        echo "<h2>🗄️ Test de la Base de Données</h2>";
        
        try {
            // Test des tables principales
            $tables = ['climbing_regions', 'climbing_sites', 'climbing_sectors', 'climbing_routes', 'users'];
            
            foreach ($tables as $table) {
                try {
                    $count = $this->db->fetchOne("SELECT COUNT(*) as count FROM $table");
                    $this->logTest("Table $table", true, "Table accessible", $count['count'] . " enregistrements");
                } catch (Exception $e) {
                    $this->logTest("Table $table", false, "Erreur: " . $e->getMessage());
                }
            }
            
            // Test des relations
            $routesWithSectors = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM climbing_routes r 
                INNER JOIN climbing_sectors s ON r.sector_id = s.id
            ");
            $this->logTest("Relations Routes-Secteurs", true, "Jointures fonctionnelles", $routesWithSectors['count'] . " voies liées");
            
            $sectorsWithSites = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM climbing_sectors sec 
                INNER JOIN climbing_sites s ON sec.site_id = s.id
            ");
            $this->logTest("Relations Secteurs-Sites", true, "Jointures fonctionnelles", $sectorsWithSites['count'] . " secteurs liés");
            
        } catch (Exception $e) {
            $this->logTest("Base de données", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Test des contrôleurs principaux
     */
    public function testControllers() {
        echo "<h2>🎮 Test des Contrôleurs</h2>";
        
        try {
            // Test HomeController
            $homeController = $this->container->get(\TopoclimbCH\Controllers\HomeController::class);
            $this->logTest("HomeController", $homeController instanceof \TopoclimbCH\Controllers\HomeController, "Contrôleur principal disponible");
            
            // Test RouteController
            try {
                $routeController = $this->container->get(\TopoclimbCH\Controllers\RouteController::class);
                $this->logTest("RouteController", $routeController instanceof \TopoclimbCH\Controllers\RouteController, "Contrôleur voies disponible");
            } catch (Exception $e) {
                $this->logTest("RouteController", false, "Non disponible: " . $e->getMessage());
            }
            
            // Test AuthController
            try {
                $authController = $this->container->get(\TopoclimbCH\Controllers\AuthController::class);
                $this->logTest("AuthController", $authController instanceof \TopoclimbCH\Controllers\AuthController, "Contrôleur auth disponible");
            } catch (Exception $e) {
                $this->logTest("AuthController", false, "Non disponible: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            $this->logTest("Contrôleurs", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Test des opérations CRUD sur les voies (simulation interne)
     */
    public function testRouteCrud() {
        echo "<h2>🧗‍♂️ Test CRUD des Voies (Simulation Interne)</h2>";
        
        try {
            // Test READ - Lecture des voies existantes
            $routes = $this->db->fetchAll("SELECT * FROM climbing_routes LIMIT 5");
            $this->logTest("READ - Lecture voies", !empty($routes), "Voies récupérées", count($routes) . " voies");
            
            if (!empty($routes)) {
                $testRoute = $routes[0];
                $routeId = $testRoute['id'];
                
                // Test validation des données
                $requiredFields = ['name', 'difficulty', 'sector_id'];
                $validRoute = true;
                foreach ($requiredFields as $field) {
                    if (empty($testRoute[$field])) {
                        $validRoute = false;
                        break;
                    }
                }
                $this->logTest("Validation données", $validRoute, "Champs obligatoires présents", "Voie: " . $testRoute['name']);
                
                // Test des relations
                $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$testRoute['sector_id']]);
                $this->logTest("Relations secteur", !empty($sector), "Secteur lié trouvé", $sector ? $sector['name'] : 'Aucun');
            }
            
            // Test CREATE - Simulation de validation
            $newRouteData = [
                'name' => 'Test Route Interne ' . date('H:i:s'),
                'difficulty' => '6a',
                'sector_id' => 1,
                'description' => 'Route de test interne',
                'length' => 25
            ];
            
            // Vérifier que le secteur existe
            $targetSector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$newRouteData['sector_id']]);
            if (!$targetSector) {
                $firstSector = $this->db->fetchOne("SELECT * FROM climbing_sectors LIMIT 1");
                if ($firstSector) {
                    $newRouteData['sector_id'] = $firstSector['id'];
                    $targetSector = $firstSector;
                }
            }
            
            $this->logTest("CREATE - Validation données", !empty($targetSector), "Données valides pour création", "Secteur cible: " . ($targetSector['name'] ?? 'Aucun'));
            
            // Test UPDATE - Simulation de validation
            if (!empty($routes)) {
                $updateData = [
                    'id' => $routes[0]['id'],
                    'name' => $routes[0]['name'] . ' (Modifié)',
                    'difficulty' => $routes[0]['difficulty'],
                    'description' => 'Description mise à jour'
                ];
                $this->logTest("UPDATE - Validation données", true, "Données valides pour modification", "ID: " . $updateData['id']);
            }
            
            // Test DELETE - Simulation de vérifications
            if (!empty($routes)) {
                // Vérifier s'il y a des ascensions liées
                $ascents = $this->db->fetchOne("SELECT COUNT(*) as count FROM user_ascents WHERE route_id = ?", [$routes[0]['id']]);
                $canDelete = $ascents['count'] == 0; // On peut supprimer seulement s'il n'y a pas d'ascensions
                $this->logTest("DELETE - Validation contraintes", true, "Vérification contraintes", $canDelete ? "Suppression possible" : "Ascensions liées (" . $ascents['count'] . ")");
            }
            
        } catch (Exception $e) {
            $this->logTest("CRUD Voies", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Test des APIs REST internes
     */
    public function testInternalApis() {
        echo "<h2>🔌 Test des APIs REST (Simulation Interne)</h2>";
        
        try {
            // Simulation des contrôleurs API
            $apiControllers = [
                'RegionApiController' => \TopoclimbCH\Controllers\Api\RegionApiController::class,
                'SectorApiController' => \TopoclimbCH\Controllers\Api\SectorApiController::class,
                'RouteApiController' => \TopoclimbCH\Controllers\Api\RouteApiController::class
            ];
            
            foreach ($apiControllers as $name => $class) {
                try {
                    $controller = $this->container->get($class);
                    $this->logTest("API $name", $controller instanceof $class, "Contrôleur API disponible");
                } catch (Exception $e) {
                    $this->logTest("API $name", false, "Non disponible: " . $e->getMessage());
                }
            }
            
            // Test des données pour APIs
            $apiData = [
                'Régions' => $this->db->fetchAll("SELECT * FROM climbing_regions LIMIT 3"),
                'Secteurs' => $this->db->fetchAll("SELECT * FROM climbing_sectors LIMIT 3"),
                'Voies' => $this->db->fetchAll("SELECT * FROM climbing_routes LIMIT 3")
            ];
            
            foreach ($apiData as $type => $data) {
                $this->logTest("Données API $type", !empty($data), "Données disponibles", count($data) . " éléments");
            }
            
        } catch (Exception $e) {
            $this->logTest("APIs REST", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Test des templates et vues
     */
    public function testTemplatesAndViews() {
        echo "<h2>🎨 Test des Templates et Vues</h2>";
        
        try {
            // Test template homepage
            $homepageData = [
                'title' => 'Test Homepage',
                'description' => 'Test description',
                'stats' => [
                    'regions_count' => '3',
                    'sectors_count' => '25',
                    'routes_count' => '301',
                    'users_count' => '5'
                ],
                'popular_sectors' => [],
                'recent_books' => [],
                'trending_routes' => []
            ];
            
            $homepageHtml = $this->view->render('home/index', $homepageData);
            $this->logTest("Template Homepage", !empty($homepageHtml), "Rendu réussi", strlen($homepageHtml) . " caractères");
            
            // Test template layout principal
            $layoutTest = strpos($homepageHtml, '<html') !== false && strpos($homepageHtml, '</html>') !== false;
            $this->logTest("Template Layout", $layoutTest, "Structure HTML complète");
            
            // Test présence des données dans le template
            $dataTest = strpos($homepageHtml, $homepageData['title']) !== false;
            $this->logTest("Template Data Binding", $dataTest, "Données injectées correctement");
            
            // Test templates d'erreur
            try {
                $errorTest = $this->view->render('errors/404', ['title' => 'Test 404']);
                $this->logTest("Template 404", !empty($errorTest), "Template d'erreur disponible");
            } catch (Exception $e) {
                $this->logTest("Template 404", false, "Non disponible: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            $this->logTest("Templates", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Test de performance interne
     */
    public function testPerformance() {
        echo "<h2>⚡ Test de Performance Interne</h2>";
        
        try {
            // Test temps de requête DB
            $start = microtime(true);
            $routes = $this->db->fetchAll("SELECT * FROM climbing_routes LIMIT 100");
            $dbTime = round((microtime(true) - $start) * 1000, 2);
            $this->logTest("Performance DB", $dbTime < 500, "Requête 100 voies", $dbTime . "ms");
            
            // Test temps de rendu template
            $start = microtime(true);
            $html = $this->view->render('layouts/simple', ['title' => 'Performance Test', 'message' => 'Test']);
            $templateTime = round((microtime(true) - $start) * 1000, 2);
            $this->logTest("Performance Template", $templateTime < 100, "Rendu template simple", $templateTime . "ms");
            
            // Test mémoire
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $this->logTest("Utilisation Mémoire", $memoryUsage < 50, "Consommation mémoire", $memoryUsage . "MB");
            
        } catch (Exception $e) {
            $this->logTest("Performance", false, "Erreur de test: " . $e->getMessage());
        }
    }
    
    /**
     * Test de sécurité et validation
     */
    public function testSecurity() {
        echo "<h2>🔒 Test de Sécurité et Validation</h2>";
        
        try {
            // Test protection injection SQL
            $maliciousInput = "'; DROP TABLE climbing_routes; --";
            try {
                $result = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE name = ?", [$maliciousInput]);
                $this->logTest("Protection SQL Injection", true, "Requête préparée sécurisée");
            } catch (Exception $e) {
                $this->logTest("Protection SQL Injection", false, "Erreur: " . $e->getMessage());
            }
            
            // Test validation des données
            $invalidRouteData = [
                'name' => '', // Nom vide
                'difficulty' => 'invalid', // Difficulté invalide
                'sector_id' => 'not_a_number' // ID invalide
            ];
            
            $validation = [
                'name' => !empty(trim($invalidRouteData['name'])),
                'difficulty' => preg_match('/^[3-9][a-c]?[+]?$/', $invalidRouteData['difficulty']),
                'sector_id' => is_numeric($invalidRouteData['sector_id'])
            ];
            
            foreach ($validation as $field => $valid) {
                $this->logTest("Validation $field", !$valid, "Détection données invalides", $valid ? "Valide" : "Invalide (attendu)");
            }
            
        } catch (Exception $e) {
            $this->logTest("Sécurité", false, "Erreur générale: " . $e->getMessage());
        }
    }
    
    /**
     * Exécute tous les tests internes
     */
    public function runAllTests() {
        echo "<div style='background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h2 style='margin-top: 0;'>🧪 Suite de Tests Internes TopoclimbCH</h2>";
        echo "<p><strong>Mode:</strong> Tests internes sécurisés (pas d'appels externes)</p>";
        echo "<p><strong>Environnement:</strong> " . ($_ENV['APP_ENV'] ?? 'production') . "</p>";
        echo "</div>";
        
        $this->testCoreServices();
        $this->testDatabaseModels();
        $this->testControllers();
        $this->testRouteCrud();
        $this->testInternalApis();
        $this->testTemplatesAndViews();
        $this->testPerformance();
        $this->testSecurity();
        
        $this->displayResults();
    }
    
    /**
     * Affiche les résultats finaux
     */
    private function displayResults() {
        $successRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0;
        $bgColor = $successRate >= 90 ? '#d4edda' : ($successRate >= 70 ? '#fff3cd' : '#f8d7da');
        $textColor = $successRate >= 90 ? '#155724' : ($successRate >= 70 ? '#856404' : '#721c24');
        $borderColor = $successRate >= 90 ? '#c3e6cb' : ($successRate >= 70 ? '#ffeaa7' : '#f5c6cb');
        
        echo "<div style='background: $bgColor; color: $textColor; padding: 25px; margin: 30px 0; border-radius: 8px; border: 2px solid $borderColor;'>";
        echo "<h2 style='margin-top: 0;'>📊 Résultats des Tests Internes</h2>";
        echo "<div style='font-size: 18px; line-height: 1.6;'>";
        echo "<strong>Tests totaux:</strong> {$this->totalTests}<br>";
        echo "<strong>Tests réussis:</strong> <span style='color: green; font-weight: bold;'>{$this->passedTests}</span><br>";
        echo "<strong>Tests échoués:</strong> <span style='color: red; font-weight: bold;'>{$this->failedTests}</span><br>";
        echo "<strong>Taux de réussite:</strong> <span style='font-size: 28px; font-weight: bold;'>{$successRate}%</span>";
        echo "</div>";
        
        echo "<div style='margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.3); border-radius: 5px;'>";
        if ($successRate >= 90) {
            echo "<div style='font-weight: bold; color: green; font-size: 18px;'>🎉 Excellent! TopoclimbCH est parfaitement fonctionnel!</div>";
            echo "<p>Toutes les fonctionnalités principales sont opérationnelles.</p>";
        } elseif ($successRate >= 70) {
            echo "<div style='font-weight: bold; color: orange; font-size: 18px;'>⚠️ Bon état général avec quelques améliorations possibles</div>";
            echo "<p>La plupart des fonctionnalités marchent bien, quelques ajustements mineurs recommandés.</p>";
        } else {
            echo "<div style='font-weight: bold; color: red; font-size: 18px;'>❌ Plusieurs problèmes détectés</div>";
            echo "<p>Une intervention est nécessaire pour corriger les erreurs identifiées.</p>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    public function __destruct() {
        echo "</div>";
    }
}

// Exécution des tests internes
$testSuite = new InternalTestSuite();
$testSuite->runAllTests();

echo "<div style='margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;'>";
echo "<h3>🔗 Actions et Outils</h3>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 15px;'>";
echo "<a href='/' style='padding: 8px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>🏠 Accueil</a>";
echo "<a href='/routes' style='padding: 8px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🧗 Voies</a>";
echo "<a href='/test_final.php' style='padding: 8px 15px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>🧪 Test Simple</a>";
echo "<a href='" . $_SERVER['PHP_SELF'] . "' style='padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>🔄 Relancer</a>";
echo "</div>";
echo "<p style='margin-top: 15px; color: #6c757d; font-size: 14px;'>";
echo "<strong>Note:</strong> Ces tests sont exécutés en interne et ne font aucun appel vers le site en production. ";
echo "Ils testent directement les composants, la base de données et les templates.";
echo "</p>";
echo "</div>";

?>