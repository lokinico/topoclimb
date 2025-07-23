<?php
// Informations serveur pour diagnostic
header('Content-Type: text/plain');

echo "🔍 DIAGNOSTIC SERVEUR WEB - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

echo "📍 Variables serveur importantes :\n";
$vars = [
    'SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_PORT', 'REQUEST_URI',
    'SCRIPT_NAME', 'PATH_INFO', 'QUERY_STRING', 'DOCUMENT_ROOT',
    'REQUEST_METHOD', 'HTTP_HOST'
];

foreach ($vars as $var) {
    echo "   $var: " . ($_SERVER[$var] ?? 'undefined') . "\n";
}

echo "\n📍 Configuration PHP :\n";
echo "   Version: " . PHP_VERSION . "\n";
echo "   SAPI: " . php_sapi_name() . "\n";

echo "\n📍 Modules Apache (si disponible) :\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $relevant = array_filter($modules, function($mod) {
        return strpos($mod, 'rewrite') !== false || strpos($mod, 'mod_') === 0;
    });
    foreach (array_slice($relevant, 0, 10) as $mod) {
        echo "   - $mod\n";
    }
} else {
    echo "   apache_get_modules() non disponible\n";
}

echo "\n📍 Test de réécriture d'URL :\n";
echo "   URL actuelle: " . ($_SERVER['REQUEST_URI'] ?? 'undefined') . "\n";
echo "   Script actuel: " . ($_SERVER['SCRIPT_NAME'] ?? 'undefined') . "\n";

if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== $_SERVER['SCRIPT_NAME']) {
    echo "   ✅ Réécriture d'URL active\n";
} else {
    echo "   ❌ Réécriture d'URL potentiellement inactive\n";
}

echo "\n📍 Test .htaccess :\n";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "   ✅ .htaccess existe\n";
    $htaccess = file_get_contents(__DIR__ . '/.htaccess');
    if (strpos($htaccess, 'RewriteEngine On') !== false) {
        echo "   ✅ RewriteEngine activé dans .htaccess\n";
    } else {
        echo "   ❌ RewriteEngine absent du .htaccess\n";
    }
} else {
    echo "   ❌ .htaccess manquant\n";
}

echo "\n📍 Test index.php :\n";
if (file_exists(__DIR__ . '/index.php')) {
    echo "   ✅ index.php existe\n";
} else {
    echo "   ❌ index.php manquant\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Diagnostic terminé\n";