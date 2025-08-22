<?php
/**
 * Test Complet TopoclimbCH - Analyse de tous les chemins
 * Génère une checklist prioritaire pour une utilisation rapide
 */

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

class SiteComprehensiveTest {
    private $db;
    private $baseUrl = 'http://localhost:8000';
    private $results = [];
    private $critical_issues = [];
    private $priorities = [];
    
    public function __construct() {
        $this->db = new Database();
        echo "🧪 TEST COMPLET TOPOCLIMB-CH" . PHP_EOL;
        echo "=============================" . PHP_EOL . PHP_EOL;
    }
    
    public function runFullTest() {
        $this->testDatabaseConnectivity();
        $this->testCoreRoutes();
        $this->testAPIEndpoints();
        $this->testMediaSystem();
        $this->testAuthSystem();
        $this->testJavaScriptIntegration();
        $this->testSEOAndAccessibility();
        
        $this->generatePriorityChecklist();
        $this->displaySummary();
    }
    
    private function testDatabaseConnectivity() {
        echo "📊 TEST BASE DE DONNÉES" . PHP_EOL;
        echo "----------------------" . PHP_EOL;
        
        try {
            // Test tables principales
            $tables = [
                'climbing_regions' => 'Régions',
                'climbing_sites' => 'Sites',
                'climbing_sectors' => 'Secteurs',
                'climbing_routes' => 'Voies',
                'climbing_media' => 'Médias',
                'users' => 'Utilisateurs'
            ];
            
            foreach ($tables as $table => $name) {
                try {
                    $count = $this->db->fetchOne("SELECT COUNT(*) as count FROM $table")['count'];
                    $this->results['db'][$table] = [
                        'status' => 'OK',
                        'count' => $count,
                        'message' => "$name: $count entrées"
                    ];
                    echo "  ✅ $name: $count entrées" . PHP_EOL;
                } catch (Exception $e) {
                    $this->results['db'][$table] = [
                        'status' => 'ERROR',
                        'error' => $e->getMessage()
                    ];
                    $this->critical_issues[] = "❌ Table $name inaccessible: " . $e->getMessage();
                    echo "  ❌ $name: " . $e->getMessage() . PHP_EOL;
                }
            }
            
            // Test coordonnées GPS
            $coords_test = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_sectors WHERE coordinates_lat IS NOT NULL AND coordinates_lng IS NOT NULL"
            );
            
            if ($coords_test['count'] > 0) {
                echo "  ✅ Coordonnées GPS: {$coords_test['count']} secteurs" . PHP_EOL;
            } else {
                $this->critical_issues[] = "⚠️ Aucun secteur avec coordonnées GPS";
                echo "  ⚠️ Aucun secteur avec coordonnées GPS" . PHP_EOL;
            }
            
        } catch (Exception $e) {
            $this->critical_issues[] = "❌ Connexion base de données impossible: " . $e->getMessage();
            echo "  ❌ Connexion impossible: " . $e->getMessage() . PHP_EOL;
        }
        
        echo PHP_EOL;
    }
    
    private function testCoreRoutes() {
        echo "🌐 TEST ROUTES PRINCIPALES" . PHP_EOL;
        echo "-------------------------" . PHP_EOL;
        
        $routes = [
            '/' => 'Page d\'accueil',
            '/regions' => 'Liste régions',
            '/sites' => 'Liste sites',
            '/sectors' => 'Liste secteurs',
            '/routes' => 'Liste voies',
            '/login' => 'Connexion',
            '/register' => 'Inscription'
        ];
        
        foreach ($routes as $path => $name) {
            $result = $this->testHttpRequest($path);
            $this->results['routes'][$path] = $result;
            
            if ($result['status'] === 'OK') {
                echo "  ✅ $name ($path): {$result['code']}" . PHP_EOL;
            } else {
                if ($result['code'] >= 500) {
                    $this->critical_issues[] = "❌ $name ($path): Erreur serveur {$result['code']}";
                }
                echo "  ❌ $name ($path): {$result['code']} - {$result['error']}" . PHP_EOL;
            }
        }
        
        echo PHP_EOL;
    }
    
    private function testAPIEndpoints() {
        echo "🔗 TEST API ENDPOINTS" . PHP_EOL;
        echo "--------------------" . PHP_EOL;
        
        $apis = [
            '/api/weather/current?lat=46.2044&lng=7.15' => 'API Météo',
            '/api/sectors' => 'API Secteurs',
            '/api/routes' => 'API Voies',
            '/api/sites' => 'API Sites'
        ];
        
        foreach ($apis as $path => $name) {
            $result = $this->testHttpRequest($path);
            $this->results['api'][$path] = $result;
            
            if ($result['status'] === 'OK') {
                echo "  ✅ $name: {$result['code']}" . PHP_EOL;
                
                // Analyser réponse JSON si disponible
                if (!empty($result['content'])) {
                    $json = json_decode($result['content'], true);
                    if ($json && isset($json['success']) && $json['success']) {
                        echo "    📄 Réponse JSON valide" . PHP_EOL;
                    }
                }
            } else {
                if ($result['code'] >= 500) {
                    $this->critical_issues[] = "❌ $name: Erreur serveur {$result['code']}";
                }
                echo "  ❌ $name: {$result['code']} - {$result['error']}" . PHP_EOL;
            }
        }
        
        echo PHP_EOL;
    }
    
    private function testMediaSystem() {
        echo "📸 TEST SYSTÈME MÉDIAS" . PHP_EOL;
        echo "---------------------" . PHP_EOL;
        
        // Test structure uploads
        $upload_dirs = [
            'public/uploads' => 'Répertoire uploads',
            'public/uploads/media' => 'Médias secteurs',
            'public/uploads/routes' => 'Médias voies'
        ];
        
        foreach ($upload_dirs as $dir => $name) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                echo "  ✅ $name: " . count($files) . " fichiers" . PHP_EOL;
            } else {
                echo "  ⚠️ $name: Répertoire manquant" . PHP_EOL;
                $this->priorities[] = "Créer répertoire $dir pour uploads";
            }
        }
        
        // Test permissions
        if (is_writable('public/uploads')) {
            echo "  ✅ Permissions uploads: OK" . PHP_EOL;
        } else {
            $this->critical_issues[] = "❌ Répertoire uploads non writable";
            echo "  ❌ Permissions uploads: NOK" . PHP_EOL;
        }
        
        echo PHP_EOL;
    }
    
    private function testAuthSystem() {
        echo "🔐 TEST SYSTÈME AUTHENTIFICATION" . PHP_EOL;
        echo "--------------------------------" . PHP_EOL;
        
        // Test routes auth
        $auth_routes = [
            '/login' => 'Page login',
            '/register' => 'Page inscription',
            '/logout' => 'Déconnexion'
        ];
        
        foreach ($auth_routes as $path => $name) {
            $result = $this->testHttpRequest($path);
            
            if ($result['code'] === 200 || $result['code'] === 302) {
                echo "  ✅ $name: {$result['code']}" . PHP_EOL;
            } else {
                echo "  ❌ $name: {$result['code']}" . PHP_EOL;
                if ($result['code'] >= 500) {
                    $this->critical_issues[] = "❌ $name: Erreur serveur";
                }
            }
        }
        
        // Test table users
        try {
            $user_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
            echo "  📊 Utilisateurs: $user_count comptes" . PHP_EOL;
            
            if ($user_count === 0) {
                $this->priorities[] = "Créer compte administrateur par défaut";
            }
        } catch (Exception $e) {
            $this->critical_issues[] = "❌ Table users inaccessible";
        }
        
        echo PHP_EOL;
    }
    
    private function testJavaScriptIntegration() {
        echo "⚡ TEST INTÉGRATION JAVASCRIPT" . PHP_EOL;
        echo "-----------------------------" . PHP_EOL;
        
        $js_files = [
            'public/js/app.js' => 'App principal',
            'public/js/topoclimb.js' => 'TopoclimbCH core',
            'public/js/components/common.js' => 'Composants communs',
            'public/js/components/weather-widget.js' => 'Widget météo',
            'public/js/core/index.js' => 'Core framework'
        ];
        
        foreach ($js_files as $file => $name) {
            if (file_exists($file)) {
                $size = filesize($file);
                echo "  ✅ $name: " . round($size/1024, 1) . "KB" . PHP_EOL;
                
                // Test syntaxe basique
                $content = file_get_contents($file);
                if (strpos($content, 'syntax error') === false && strpos($content, '\\n') === false) {
                    echo "    ✅ Syntaxe OK" . PHP_EOL;
                } else {
                    echo "    ⚠️ Possible problème syntaxe" . PHP_EOL;
                }
            } else {
                echo "  ❌ $name: Fichier manquant" . PHP_EOL;
                $this->priorities[] = "Vérifier fichier JS manquant: $file";
            }
        }
        
        // Test CSS
        $css_files = [
            'public/css/app.css' => 'Styles principaux',
            'public/css/components/common.css' => 'Styles composants'
        ];
        
        foreach ($css_files as $file => $name) {
            if (file_exists($file)) {
                echo "  ✅ $name: " . round(filesize($file)/1024, 1) . "KB" . PHP_EOL;
            } else {
                echo "  ❌ $name: Fichier manquant" . PHP_EOL;
            }
        }
        
        echo PHP_EOL;
    }
    
    private function testSEOAndAccessibility() {
        echo "🎯 TEST SEO & ACCESSIBILITÉ" . PHP_EOL;
        echo "---------------------------" . PHP_EOL;
        
        // Test page d'accueil
        $home_result = $this->testHttpRequest('/');
        if ($home_result['status'] === 'OK' && !empty($home_result['content'])) {
            $content = $home_result['content'];
            
            // Test meta tags
            if (strpos($content, '<title>') !== false) {
                echo "  ✅ Balise title présente" . PHP_EOL;
            } else {
                echo "  ⚠️ Balise title manquante" . PHP_EOL;
                $this->priorities[] = "Ajouter balises title dans templates";
            }
            
            if (strpos($content, 'meta name="description"') !== false) {
                echo "  ✅ Meta description présente" . PHP_EOL;
            } else {
                echo "  ⚠️ Meta description manquante" . PHP_EOL;
                $this->priorities[] = "Ajouter meta descriptions";
            }
            
            // Test accessibilité basique
            if (strpos($content, 'alt=') !== false) {
                echo "  ✅ Attributs alt trouvés" . PHP_EOL;
            } else {
                echo "  ⚠️ Peu d'attributs alt" . PHP_EOL;
            }
            
            // Test responsive
            if (strpos($content, 'viewport') !== false) {
                echo "  ✅ Viewport responsive" . PHP_EOL;
            } else {
                echo "  ⚠️ Meta viewport manquant" . PHP_EOL;
            }
        }
        
        echo PHP_EOL;
    }
    
    private function testHttpRequest($path) {
        $url = $this->baseUrl . $path;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => 'ERROR',
                'code' => 0,
                'error' => $error,
                'content' => null
            ];
        }
        
        return [
            'status' => ($code >= 200 && $code < 400) ? 'OK' : 'ERROR',
            'code' => $code,
            'error' => $code >= 400 ? "HTTP $code" : null,
            'content' => $content
        ];
    }
    
    private function generatePriorityChecklist() {
        echo "📋 CHECKLIST PRIORITAIRE" . PHP_EOL;
        echo "========================" . PHP_EOL;
        
        // Priorité 1: Problèmes critiques
        if (!empty($this->critical_issues)) {
            echo "🚨 PRIORITÉ 1 - CRITIQUE (à corriger immédiatement)" . PHP_EOL;
            foreach ($this->critical_issues as $issue) {
                echo "  $issue" . PHP_EOL;
            }
            echo PHP_EOL;
        }
        
        // Priorité 2: Fonctionnalités essentielles manquantes
        echo "⚠️ PRIORITÉ 2 - ESSENTIEL (pour utilisation basique)" . PHP_EOL;
        
        // Analyser les résultats pour détecter les priorités
        $essentials = [];
        
        // Base de données
        if (isset($this->results['db'])) {
            foreach ($this->results['db'] as $table => $result) {
                if ($result['status'] === 'OK' && $result['count'] == 0) {
                    if ($table === 'climbing_regions') {
                        $essentials[] = "Créer régions d'escalade de base (Valais, Berne, etc.)";
                    }
                    if ($table === 'climbing_sites') {
                        $essentials[] = "Créer quelques sites d'escalade exemples";
                    }
                }
            }
        }
        
        // Routes
        if (isset($this->results['routes'])) {
            foreach ($this->results['routes'] as $route => $result) {
                if ($result['status'] !== 'OK' && $result['code'] >= 500) {
                    $essentials[] = "Corriger route $route (erreur {$result['code']})";
                }
            }
        }
        
        // Ajouter priorités détectées
        $essentials = array_merge($essentials, [
            "Vérifier toutes les pages principales fonctionnent",
            "S'assurer qu'au moins 1 région avec sites/secteurs existe",
            "Tester workflow complet: Region → Site → Secteur → Voie",
            "Vérifier widget météo fonctionne sur secteurs avec GPS"
        ]);
        
        foreach ($essentials as $item) {
            echo "  • $item" . PHP_EOL;
        }
        echo PHP_EOL;
        
        // Priorité 3: Améliorations
        echo "📈 PRIORITÉ 3 - AMÉLIORATION (pour expérience optimale)" . PHP_EOL;
        $improvements = [
            "Optimiser SEO (meta descriptions, titles)",
            "Améliorer accessibilité (alt texts, contrastes)",
            "Ajouter système de cache pour performance",
            "Implémenter recherche avancée",
            "Ajouter système de favoris utilisateur",
            "Créer dashboard administrateur",
            "Optimiser images et médias",
            "Ajouter tests automatisés"
        ];
        
        foreach ($improvements as $item) {
            echo "  • $item" . PHP_EOL;
        }
        echo PHP_EOL;
        
        // Priorité 4: Fonctionnalités avancées
        echo "🚀 PRIORITÉ 4 - AVANCÉ (fonctionnalités futures)" . PHP_EOL;
        $advanced = [
            "API REST complète pour mobile",
            "Système de notation et commentaires",
            "Intégration réseaux sociaux",
            "Géolocalisation avancée",
            "Cartes interactives détaillées",
            "Système de notifications",
            "Mode hors-ligne (PWA)",
            "Analytics et statistiques"
        ];
        
        foreach ($advanced as $item) {
            echo "  • $item" . PHP_EOL;
        }
        echo PHP_EOL;
    }
    
    private function displaySummary() {
        echo "📊 RÉSUMÉ GÉNÉRAL" . PHP_EOL;
        echo "=================" . PHP_EOL;
        
        $total_issues = count($this->critical_issues);
        $total_routes_ok = 0;
        $total_routes = 0;
        
        if (isset($this->results['routes'])) {
            foreach ($this->results['routes'] as $result) {
                $total_routes++;
                if ($result['status'] === 'OK') {
                    $total_routes_ok++;
                }
            }
        }
        
        echo "🌐 Routes principales: $total_routes_ok/$total_routes fonctionnelles" . PHP_EOL;
        echo "🚨 Problèmes critiques: $total_issues détectés" . PHP_EOL;
        
        if ($total_issues === 0 && $total_routes_ok >= $total_routes * 0.8) {
            echo "✅ Statut général: BON - Site utilisable" . PHP_EOL;
        } elseif ($total_issues <= 2 && $total_routes_ok >= $total_routes * 0.6) {
            echo "⚠️ Statut général: MOYEN - Corrections mineures nécessaires" . PHP_EOL;
        } else {
            echo "❌ Statut général: CRITIQUE - Corrections majeures requises" . PHP_EOL;
        }
        
        echo PHP_EOL;
        echo "💡 RECOMMANDATION: Commencer par les tâches PRIORITÉ 1, puis PRIORITÉ 2" . PHP_EOL;
        echo "⏱️ Temps estimé pour site fonctionnel: 2-4 heures (priorités 1+2)" . PHP_EOL;
    }
}

// Lancement du test
$test = new SiteComprehensiveTest();
$test->runFullTest();