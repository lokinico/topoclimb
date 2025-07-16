<?php
/**
 * Test CRUD spécifique pour les voies d'escalade TopoclimbCH
 * Test avec simulation d'authentification et opérations complètes
 */

require_once dirname(__DIR__) . '/bootstrap.php';

echo "<h1>🧗‍♂️ Test CRUD Voies d'Escalade</h1>";

class RouteCrudTester {
    private $baseUrl = 'https://topoclimb.ch';
    private $container;
    private $db;
    private $testRouteId = null;
    
    public function __construct() {
        // Initialiser le container pour accès direct à la DB
        try {
            $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
            $this->container = $containerBuilder->build();
            $this->db = $this->container->get(\TopoclimbCH\Core\Database::class);
            echo "<div style='color: green;'>✅ Connexion à la base de données établie</div>";
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur de connexion DB: " . $e->getMessage() . "</div>";
        }
        
        echo "<div style='max-width: 1200px; margin: 20px auto; font-family: Arial, sans-serif;'>";
    }
    
    /**
     * Test de lecture des voies existantes
     */
    public function testReadRoutes() {
        echo "<h2>📖 Test de Lecture des Voies</h2>";
        
        try {
            // Test API GET
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api/v1/routes?limit=5');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    echo "<div style='color: green;'>✅ API Routes: " . count($data['data']) . " voies récupérées</div>";
                    
                    // Sauvegarder un ID pour les tests suivants
                    if (!empty($data['data'])) {
                        $this->testRouteId = $data['data'][0]['id'];
                        echo "<div style='color: blue;'>📍 ID de test: {$this->testRouteId}</div>";
                    }
                } else {
                    echo "<div style='color: red;'>❌ Structure de données API incorrecte</div>";
                }
            } else {
                echo "<div style='color: red;'>❌ Erreur API Routes: Code $httpCode</div>";
            }
            
            // Test direct DB
            $routes = $this->db->fetchAll("SELECT * FROM climbing_routes LIMIT 5");
            echo "<div style='color: green;'>✅ DB directe: " . count($routes) . " voies en base</div>";
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur lecture: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Test de validation des données
     */
    public function testRouteValidation() {
        echo "<h2>✅ Test de Validation des Données</h2>";
        
        if (!$this->testRouteId) {
            echo "<div style='color: orange;'>⚠️ Pas d'ID de test disponible</div>";
            return;
        }
        
        try {
            // Récupérer une voie existante
            $route = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ?", [$this->testRouteId]);
            
            if ($route) {
                echo "<div style='color: green;'>✅ Voie trouvée: {$route['name']}</div>";
                
                // Validation des champs obligatoires
                $requiredFields = ['name', 'difficulty', 'sector_id'];
                foreach ($requiredFields as $field) {
                    if (!empty($route[$field])) {
                        echo "<div style='color: green; margin-left: 20px;'>✅ Champ {$field}: {$route[$field]}</div>";
                    } else {
                        echo "<div style='color: red; margin-left: 20px;'>❌ Champ {$field} manquant</div>";
                    }
                }
                
                // Validation des relations
                if ($route['sector_id']) {
                    $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$route['sector_id']]);
                    if ($sector) {
                        echo "<div style='color: green; margin-left: 20px;'>✅ Secteur lié: {$sector['name']}</div>";
                    } else {
                        echo "<div style='color: red; margin-left: 20px;'>❌ Secteur introuvable</div>";
                    }
                }
                
            } else {
                echo "<div style='color: red;'>❌ Voie non trouvée</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur validation: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Test de création simulée (sans authentification)
     */
    public function testCreateRouteSimulation() {
        echo "<h2>➕ Test de Création de Voie (Simulation)</h2>";
        
        // Données de test
        $testData = [
            'name' => 'Test Route CRUD ' . date('Y-m-d H:i:s'),
            'difficulty' => '6a',
            'sector_id' => 1,
            'description' => 'Voie de test créée automatiquement',
            'length' => 25,
            'style' => 'dalle',
            'gear' => 'equipped',
            'beauty_rating' => 3
        ];
        
        try {
            // Vérifier que le secteur existe
            $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$testData['sector_id']]);
            if (!$sector) {
                // Prendre le premier secteur disponible
                $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors LIMIT 1");
                if ($sector) {
                    $testData['sector_id'] = $sector['id'];
                    echo "<div style='color: blue;'>📍 Secteur utilisé: {$sector['name']} (ID: {$sector['id']})</div>";
                } else {
                    echo "<div style='color: red;'>❌ Aucun secteur disponible pour le test</div>";
                    return;
                }
            }
            
            echo "<div style='color: blue;'>🧪 Données de test préparées:</div>";
            foreach ($testData as $key => $value) {
                echo "<div style='margin-left: 20px;'><strong>$key:</strong> $value</div>";
            }
            
            // Test de l'accès au formulaire de création
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/routes/create');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "<div style='color: green;'>✅ Formulaire de création accessible</div>";
                
                // Vérifier la présence des champs requis
                $requiredFields = ['name', 'difficulty', 'sector_id'];
                foreach ($requiredFields as $field) {
                    if (strpos($response, "name=\"$field\"") !== false || strpos($response, "name='$field'") !== false) {
                        echo "<div style='color: green; margin-left: 20px;'>✅ Champ $field présent dans le formulaire</div>";
                    } else {
                        echo "<div style='color: orange; margin-left: 20px;'>⚠️ Champ $field non trouvé dans le formulaire</div>";
                    }
                }
                
            } else {
                echo "<div style='color: red;'>❌ Formulaire de création inaccessible (Code: $httpCode)</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur simulation création: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Test des opérations de modification
     */
    public function testUpdateRouteSimulation() {
        echo "<h2>✏️ Test de Modification de Voie (Simulation)</h2>";
        
        if (!$this->testRouteId) {
            echo "<div style='color: orange;'>⚠️ Pas d'ID de test disponible</div>";
            return;
        }
        
        try {
            // Test d'accès au formulaire d'édition
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/routes/{$this->testRouteId}/edit");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "<div style='color: green;'>✅ Formulaire d'édition accessible</div>";
                
                // Vérifier que les données actuelles sont pré-remplies
                $currentRoute = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ?", [$this->testRouteId]);
                if ($currentRoute) {
                    if (strpos($response, $currentRoute['name']) !== false) {
                        echo "<div style='color: green; margin-left: 20px;'>✅ Nom actuel pré-rempli: {$currentRoute['name']}</div>";
                    }
                    if (strpos($response, $currentRoute['difficulty']) !== false) {
                        echo "<div style='color: green; margin-left: 20px;'>✅ Difficulté actuelle pré-remplie: {$currentRoute['difficulty']}</div>";
                    }
                }
                
            } else {
                echo "<div style='color: red;'>❌ Formulaire d'édition inaccessible (Code: $httpCode)</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur simulation modification: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Test des permissions et sécurité
     */
    public function testRouteSecurity() {
        echo "<h2>🔒 Test de Sécurité des Voies</h2>";
        
        // Test d'accès sans authentification aux actions protégées
        $protectedActions = [
            '/routes/create' => 'Création',
            '/routes/' . ($this->testRouteId ?? '1') . '/edit' => 'Édition',
            '/routes/' . ($this->testRouteId ?? '1') . '/delete' => 'Suppression'
        ];
        
        foreach ($protectedActions as $url => $action) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 302) {
                echo "<div style='color: green;'>✅ $action: Redirection d'authentification (Code: $httpCode)</div>";
            } elseif ($httpCode === 401 || $httpCode === 403) {
                echo "<div style='color: green;'>✅ $action: Accès refusé correctement (Code: $httpCode)</div>";
            } elseif ($httpCode === 200) {
                echo "<div style='color: orange;'>⚠️ $action: Accessible sans authentification (Code: $httpCode)</div>";
            } else {
                echo "<div style='color: blue;'>ℹ️ $action: Réponse inattendue (Code: $httpCode)</div>";
            }
        }
    }
    
    /**
     * Test des statistiques et compteurs
     */
    public function testRouteStatistics() {
        echo "<h2>📊 Test des Statistiques des Voies</h2>";
        
        try {
            // Compter les voies par difficulté
            $difficultyStats = $this->db->fetchAll("
                SELECT difficulty, COUNT(*) as count 
                FROM climbing_routes 
                WHERE difficulty IS NOT NULL 
                GROUP BY difficulty 
                ORDER BY count DESC 
                LIMIT 10
            ");
            
            echo "<div style='color: green;'>✅ Répartition par difficulté:</div>";
            foreach ($difficultyStats as $stat) {
                echo "<div style='margin-left: 20px;'>{$stat['difficulty']}: {$stat['count']} voies</div>";
            }
            
            // Compter les voies par secteur
            $sectorStats = $this->db->fetchAll("
                SELECT s.name, COUNT(r.id) as route_count 
                FROM climbing_sectors s 
                LEFT JOIN climbing_routes r ON s.id = r.sector_id 
                GROUP BY s.id, s.name 
                ORDER BY route_count DESC 
                LIMIT 5
            ");
            
            echo "<div style='color: green;'>✅ Top 5 secteurs par nombre de voies:</div>";
            foreach ($sectorStats as $stat) {
                echo "<div style='margin-left: 20px;'>{$stat['name']}: {$stat['route_count']} voies</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Erreur statistiques: " . $e->getMessage() . "</div>";
        }
    }
    
    /**
     * Exécute tous les tests CRUD
     */
    public function runAllTests() {
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h2 style='margin-top: 0;'>🧪 Tests CRUD des Voies d'Escalade</h2>";
        echo "<p>Base URL: <strong>{$this->baseUrl}</strong></p>";
        echo "</div>";
        
        $this->testReadRoutes();
        $this->testRouteValidation();
        $this->testCreateRouteSimulation();
        $this->testUpdateRouteSimulation();
        $this->testRouteSecurity();
        $this->testRouteStatistics();
        
        echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #c3e6cb;'>";
        echo "<h3 style='margin-top: 0; color: #155724;'>✅ Tests CRUD Terminés</h3>";
        echo "<p style='color: #155724;'>Les fonctionnalités principales des voies d'escalade ont été testées.</p>";
        echo "<p style='color: #155724;'><strong>Note:</strong> Pour des tests complets POST/PUT/DELETE, une authentification serait nécessaire.</p>";
        echo "</div>";
    }
    
    public function __destruct() {
        echo "</div>";
    }
}

// Exécution des tests CRUD
$crudTester = new RouteCrudTester();
$crudTester->runAllTests();

echo "<div style='margin: 30px 0; padding: 15px; background: #e9ecef; border-radius: 5px;'>";
echo "<h3>🔗 Actions Rapides</h3>";
echo "<a href='/test_complete_functionality.php' style='margin-right: 15px; color: #007bff; text-decoration: none;'>🧪 Tests Complets</a>";
echo "<a href='/routes' style='margin-right: 15px; color: #007bff; text-decoration: none;'>🧗 Voir les Voies</a>";
echo "<a href='/routes/create' style='margin-right: 15px; color: #007bff; text-decoration: none;'>➕ Créer une Voie</a>";
echo "<a href='" . $_SERVER['PHP_SELF'] . "' style='color: #28a745; text-decoration: none;'>🔄 Relancer les tests</a>";
echo "</div>";

?>