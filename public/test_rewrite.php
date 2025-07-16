<?php
echo "<h1>🔍 Test de réécriture d'URL</h1>";

echo "<h2>📋 Informations de la requête</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><td><strong>REQUEST_URI</strong></td><td>" . ($_SERVER['REQUEST_URI'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td><strong>QUERY_STRING</strong></td><td>" . ($_SERVER['QUERY_STRING'] ?? 'Vide') . "</td></tr>";
echo "<tr><td><strong>SCRIPT_NAME</strong></td><td>" . ($_SERVER['SCRIPT_NAME'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td><strong>PATH_INFO</strong></td><td>" . ($_SERVER['PATH_INFO'] ?? 'Non défini') . "</td></tr>";
echo "<tr><td><strong>DOCUMENT_ROOT</strong></td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Non défini') . "</td></tr>";
echo "</table>";

echo "<h2>🧪 Tests d'accès</h2>";

echo "<h3>Test 1: Fichier index.php</h3>";
if (file_exists('index.php')) {
    echo "✅ index.php existe<br>";
    echo "📊 Taille: " . filesize('index.php') . " bytes<br>";
} else {
    echo "❌ index.php manquant<br>";
}

echo "<h3>Test 2: Module de réécriture</h3>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "✅ mod_rewrite activé<br>";
    } else {
        echo "❌ mod_rewrite non trouvé<br>";
    }
    echo "📋 Modules Apache: " . implode(', ', array_slice($modules, 0, 10)) . "...<br>";
} else {
    echo "⚠️ apache_get_modules() non disponible<br>";
}

echo "<h3>Test 3: Fichier .htaccess</h3>";
if (file_exists('.htaccess')) {
    echo "✅ .htaccess existe<br>";
    echo "📊 Taille: " . filesize('.htaccess') . " bytes<br>";
    
    $htaccess_content = file_get_contents('.htaccess');
    if (strpos($htaccess_content, 'RewriteEngine On') !== false) {
        echo "✅ RewriteEngine On trouvé<br>";
    } else {
        echo "❌ RewriteEngine On manquant<br>";
    }
    
    if (strpos($htaccess_content, 'index.php') !== false) {
        echo "✅ Règle index.php trouvée<br>";
    } else {
        echo "❌ Règle index.php manquante<br>";
    }
} else {
    echo "❌ .htaccess manquant<br>";
}

echo "<h2>🧭 Tests de navigation</h2>";
echo "<a href='/'>Tester: / (racine)</a><br>";
echo "<a href='/index.php'>Tester: /index.php (direct)</a><br>";
echo "<a href='/login'>Tester: /login (route interne)</a><br>";
echo "<a href='/non-existant'>Tester: /non-existant (devrait aller vers index.php)</a><br>";

echo "<h2>🔧 Variables d'environnement Apache</h2>";
$apache_vars = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0 || strpos($key, 'REDIRECT_') === 0) {
        $apache_vars[$key] = $value;
    }
}

if (!empty($apache_vars)) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    foreach ($apache_vars as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Aucune variable Apache spécifique trouvée<br>";
}

echo "<hr>";
echo "<p><small>Test effectué le " . date('Y-m-d H:i:s') . "</small></p>";
?>