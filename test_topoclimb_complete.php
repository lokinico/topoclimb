<?php
/**
 * SCRIPT DE TEST COMPLET TOPOCLIMB
 * Test exhaustif de toutes les fonctionnalités, pages, et problèmes
 */

echo "🧪 DÉMARRAGE TESTS COMPLETS TOPOCLIMB\n";
echo "=====================================\n\n";

// Configuration
define('TEST_URL', 'http://localhost:8000');
define('TIMEOUT', 10);

/**
 * Fonction de test HTTP avec curl
 */
function testUrl($url, $description = '', $expectedStatus = 200) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $status = ($httpCode === $expectedStatus) ? '✅' : '❌';
    $desc = $description ?: $url;
    
    echo "{$status} [{$httpCode}] {$desc}\n";
    
    if ($error) {
        echo "   🚨 Erreur: {$error}\n";
    }
    
    if ($httpCode !== $expectedStatus) {
        echo "   ⚠️  Attendu: {$expectedStatus}, Reçu: {$httpCode}\n";
    }
    
    return [
        'success' => ($httpCode === $expectedStatus),
        'code' => $httpCode,
        'content' => $response,
        'error' => $error
    ];
}

/**
 * Test des ressources statiques (CSS, JS)
 */
function testStaticResources() {
    echo "\n📁 TEST RESSOURCES STATIQUES\n";
    echo "============================\n";
    
    $resources = [
        '/css/view-modes.css' => 'CSS système de vues',
        '/js/view-manager.js' => 'JavaScript ViewManager', 
        '/js/pages-common.js' => 'JavaScript pages communes',
        '/css/pages-common.css' => 'CSS pages communes',
        '/js/topoclimb.js' => 'JavaScript principal',
        '/css/app.css' => 'CSS principal'
    ];
    
    foreach ($resources as $path => $desc) {
        testUrl(TEST_URL . $path, $desc);
    }
}

/**
 * Test des pages principales
 */
function testMainPages() {
    echo "\n🏠 TEST PAGES PRINCIPALES\n";
    echo "========================\n";
    
    $pages = [
        '/' => 'Page d\'accueil',
        '/routes' => 'Index des routes',
        '/sectors' => 'Index des secteurs', 
        '/regions' => 'Index des régions',
        '/sites' => 'Index des sites',
        '/books' => 'Index des guides'
    ];
    
    foreach ($pages as $path => $desc) {
        $result = testUrl(TEST_URL . $path, $desc);
        
        // Vérifier la présence des éléments critiques
        if ($result['success'] && strpos($path, '/') === 0 && $path !== '/') {
            checkViewSystemElements($result['content'], $path);
        }
    }
}

/**
 * Vérifier les éléments du système de vues dans le HTML
 */
function checkViewSystemElements($html, $page) {
    $checks = [
        'entities-container' => 'Conteneur principal',
        'view-grid' => 'Vue grille',
        'view-list' => 'Vue liste', 
        'view-compact' => 'Vue compacte',
        'data-view="grid"' => 'Bouton vue grille',
        'data-view="list"' => 'Bouton vue liste',
        'data-view="compact"' => 'Bouton vue compacte',
        'view-modes.css' => 'CSS système vues',
        'view-manager.js' => 'JS ViewManager'
    ];
    
    foreach ($checks as $element => $desc) {
        $found = strpos($html, $element) !== false;
        $status = $found ? '   ✅' : '   ❌';
        echo "{$status} {$desc} dans {$page}\n";
    }
}

/**
 * Test des routes spécialisées
 */
function testRoutePages() {
    echo "\n🗻 TEST PAGES ROUTES DÉTAILLÉES\n";
    echo "==============================\n";
    
    // Test avec différents IDs (on ne sait pas lesquels existent)
    $routeTests = [
        '/routes/1' => 'Route ID 1',
        '/routes/2' => 'Route ID 2', 
        '/routes/create' => 'Création route',
        '/routes/1/edit' => 'Édition route',
        '/routes/search' => 'Recherche routes'
    ];
    
    foreach ($routeTests as $path => $desc) {
        testUrl(TEST_URL . $path, $desc, null); // Accepter tout code
    }
}

/**
 * Test des API endpoints
 */
function testApiEndpoints() {
    echo "\n🔌 TEST API ENDPOINTS\n";
    echo "====================\n";
    
    $apis = [
        '/api/routes' => 'API Routes',
        '/api/sectors' => 'API Secteurs',
        '/api/regions' => 'API Régions', 
        '/api/sites' => 'API Sites',
        '/api/books' => 'API Guides',
        '/api/weather/current' => 'API Météo'
    ];
    
    foreach ($apis as $path => $desc) {
        testUrl(TEST_URL . $path, $desc, null);
    }
}

/**
 * Test de la base de données
 */
function testDatabase() {
    echo "\n🗄️  TEST BASE DE DONNÉES\n";
    echo "=======================\n";
    
    try {
        // Essayer de se connecter à SQLite
        if (file_exists('database.sqlite')) {
            $pdo = new PDO('sqlite:database.sqlite');
            echo "✅ Connexion SQLite réussie\n";
            
            // Vérifier les tables principales
            $tables = ['routes', 'sectors', 'regions', 'sites', 'users'];
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                if ($stmt) {
                    $count = $stmt->fetchColumn();
                    echo "✅ Table {$table}: {$count} enregistrements\n";
                } else {
                    echo "❌ Table {$table}: Non accessible\n";
                }
            }
            
        } else {
            echo "❌ Fichier database.sqlite non trouvé\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur base de données: " . $e->getMessage() . "\n";
    }
}

/**
 * Test du serveur de développement
 */
function testDevServer() {
    echo "\n🖥️  TEST SERVEUR DÉVELOPPEMENT\n";
    echo "=============================\n";
    
    // Vérifier si le serveur répond
    $result = testUrl(TEST_URL, 'Serveur de base');
    
    if (!$result['success']) {
        echo "\n❌ SERVEUR NON ACCESSIBLE!\n";
        echo "💡 Lancez: php -S localhost:8000 -t public/\n\n";
        return false;
    }
    return true;
}

/**
 * Analyse des logs d'erreurs
 */
function checkErrorLogs() {
    echo "\n📋 ANALYSE LOGS D'ERREURS\n";
    echo "========================\n";
    
    $logPaths = [
        'storage/logs/error.log',
        'storage/logs/debug.log', 
        'error.log',
        '/var/log/php_errors.log'
    ];
    
    foreach ($logPaths as $logPath) {
        if (file_exists($logPath)) {
            $size = filesize($logPath);
            echo "📄 {$logPath}: {$size} bytes\n";
            
            if ($size > 0) {
                $lastLines = tail($logPath, 5);
                echo "   Dernières lignes:\n";
                foreach ($lastLines as $line) {
                    echo "   > " . trim($line) . "\n";
                }
            }
        }
    }
}

/**
 * Fonction helper pour lire les dernières lignes d'un fichier
 */
function tail($filename, $lines = 10) {
    $file = file($filename);
    return array_slice($file, -$lines);
}

/**
 * Test des permissions fichiers
 */
function testFilePermissions() {
    echo "\n🔐 TEST PERMISSIONS FICHIERS\n";
    echo "===========================\n";
    
    $criticalPaths = [
        'public/' => 'Dossier public',
        'public/css/view-modes.css' => 'CSS vues',
        'public/js/view-manager.js' => 'JS ViewManager',
        'storage/' => 'Dossier storage',
        'database.sqlite' => 'Base de données'
    ];
    
    foreach ($criticalPaths as $path => $desc) {
        if (file_exists($path)) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $readable = is_readable($path) ? 'R' : '-';
            $writable = is_writable($path) ? 'W' : '-';
            echo "✅ {$desc}: {$perms} ({$readable}{$writable})\n";
        } else {
            echo "❌ {$desc}: MANQUANT\n";
        }
    }
}

// EXÉCUTION DES TESTS
echo "🚀 Démarrage de la suite de tests...\n";

// 1. Test serveur
if (!testDevServer()) {
    exit(1);
}

// 2. Permissions
testFilePermissions();

// 3. Base de données  
testDatabase();

// 4. Ressources statiques
testStaticResources();

// 5. Pages principales
testMainPages();

// 6. Pages routes détaillées
testRoutePages();

// 7. API endpoints
testApiEndpoints();

// 8. Logs d'erreurs
checkErrorLogs();

echo "\n🏁 TESTS TERMINÉS\n";
echo "=================\n";
echo "📊 Consultez les résultats ci-dessus pour identifier les problèmes\n";
echo "💡 Lancez le serveur avec: php -S localhost:8000 -t public/\n";