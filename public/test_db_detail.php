<?php
// test_db_detail.php - Test détaillé de la base de données

echo "<h1>🔍 Test Base de Données Détaillé</h1>";

// Chargement .env
if (file_exists('../.env')) {
    $lines = file('../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
            list($key, $value) = explode('=', trim($line), 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
    echo "<p>✅ Fichier .env chargé</p>";
} else {
    echo "<p>❌ Fichier .env non trouvé</p>";
    exit;
}

// Configuration de la base de données
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'port' => $_ENV['DB_PORT'] ?? '3306'
];

echo "<h2>📋 Configuration Base de Données</h2>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><td><strong>Host</strong></td><td>" . htmlspecialchars($config['host']) . "</td></tr>";
echo "<tr><td><strong>Port</strong></td><td>" . htmlspecialchars($config['port']) . "</td></tr>";
echo "<tr><td><strong>Database</strong></td><td>" . htmlspecialchars($config['database']) . "</td></tr>";
echo "<tr><td><strong>Username</strong></td><td>" . htmlspecialchars($config['username']) . "</td></tr>";
echo "<tr><td><strong>Password</strong></td><td>" . (empty($config['password']) ? '❌ VIDE' : '✅ Configuré (' . strlen($config['password']) . ' caractères)') . "</td></tr>";
echo "</table>";

// Tests de connexion progressifs
echo "<h2>🔬 Tests de Connexion</h2>";

// Test 1: Connexion sans base de données
echo "<h3>Test 1: Connexion au serveur MySQL</h3>";
try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    echo "<p>✅ <strong>Connexion au serveur MySQL réussie</strong></p>";
    
    // Lister les bases de données disponibles
    $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>📊 <strong>Bases de données disponibles :</strong></p>";
    echo "<ul>";
    foreach ($databases as $db) {
        $highlight = (strpos($db, 'sh139940') !== false) ? ' style="background: yellow;"' : '';
        echo "<li$highlight>" . htmlspecialchars($db) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erreur de connexion au serveur :</strong></p>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>💡 Solutions possibles :</h3>";
    echo "<ul>";
    echo "<li>Vérifiez le nom d'utilisateur et mot de passe dans Plesk → Bases de données</li>";
    echo "<li>Vérifiez que l'utilisateur a les permissions sur la base</li>";
    echo "<li>Le host pourrait être différent (parfois 'localhost' ne fonctionne pas)</li>";
    echo "</ul>";
    exit;
}

// Test 2: Connexion à la base spécifique
echo "<h3>Test 2: Connexion à la base de données spécifique</h3>";
if (empty($config['database'])) {
    echo "<p>❌ <strong>Nom de base de données vide dans .env</strong></p>";
    echo "<p>💡 Ajoutez DB_DATABASE=le_vrai_nom_de_votre_base dans .env</p>";
} else {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        echo "<p>✅ <strong>Connexion à la base '{$config['database']}' réussie</strong></p>";
        
        // Test des tables TopoclimbCH
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>📊 <strong>Tables trouvées (" . count($tables) . ") :</strong></p>";
        
        if (count($tables) > 0) {
            echo "<ul>";
            foreach (array_slice($tables, 0, 20) as $table) {
                $highlight = (strpos($table, 'climbing') !== false) ? ' style="background: lightgreen;"' : '';
                echo "<li$highlight>" . htmlspecialchars($table) . "</li>";
            }
            if (count($tables) > 20) {
                echo "<li>... et " . (count($tables) - 20) . " autres tables</li>";
            }
            echo "</ul>";
            
            // Vérifier les tables importantes TopoclimbCH
            $importantTables = ['climbing_regions', 'climbing_sectors', 'climbing_routes', 'users'];
            $foundTables = [];
            foreach ($importantTables as $table) {
                if (in_array($table, $tables)) {
                    $foundTables[] = $table;
                }
            }
            
            if (count($foundTables) > 0) {
                echo "<p>🎯 <strong>Tables TopoclimbCH trouvées :</strong> " . implode(', ', $foundTables) . "</p>";
            } else {
                echo "<p>⚠️ <strong>Aucune table TopoclimbCH trouvée</strong> - La base semble vide</p>";
            }
            
        } else {
            echo "<p>⚠️ <strong>Base de données vide</strong></p>";
        }
        
    } catch (PDOException $e) {
        echo "<p>❌ <strong>Erreur de connexion à la base :</strong></p>";
        echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            echo "<p>💡 <strong>La base de données n'existe pas.</strong> Choisissez une base existante dans la liste ci-dessus.</p>";
        }
    }
}

echo "<h2>🚀 Statut Final</h2>";
if (isset($pdo)) {
    echo "<p style='color: green; font-weight: bold;'>✅ TopoclimbCH peut se connecter à la base de données !</p>";
    echo "<p>🎯 <strong>Prochaine étape :</strong> Testez votre site principal</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Problème de configuration de base de données</p>";
    echo "<p>🔧 <strong>Action requise :</strong> Corrigez le fichier .env avec les bonnes informations</p>";
}

echo "<hr>";
echo "<p><small>Test effectué le " . date('Y-m-d H:i:s') . "</small></p>";
?>