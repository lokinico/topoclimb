<?php
// debug_region_api.php
// Debug spécifique pour RegionController API

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

echo "=== REGION API DEBUGGING ===\n";

// Test 1: Base de données directe
echo "\n1. Test direct base de données...\n";
try {
    $db = new Database();
    $regions = $db->fetchAll("SELECT r.id, r.name, r.coordinates_lat, r.coordinates_lng, r.description FROM climbing_regions r WHERE r.active = 1 ORDER BY r.name ASC LIMIT 10");
    echo "✅ DB Success: " . count($regions) . " regions found\n";
    if (!empty($regions)) {
        echo "Sample region: " . json_encode($regions[0]) . "\n";
    }
} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Vérification des dépendances du RegionController
echo "\n2. Vérification dépendances RegionController...\n";
try {
    // Check what RegionController needs
    $reflection = new ReflectionClass('TopoclimbCH\\Controllers\\RegionController');
    $constructor = $reflection->getConstructor();
    $params = $constructor->getParameters();
    
    echo "Constructor parameters needed:\n";
    foreach ($params as $param) {
        $type = $param->getType();
        $typeName = $type ? $type->getName() : 'mixed';
        $optional = $param->isOptional() ? ' (optional)' : ' (required)';
        echo "  - {$param->getName()}: {$typeName}{$optional}\n";
    }
} catch (Exception $e) {
    echo "❌ Reflection Error: " . $e->getMessage() . "\n";
}

// Test 3: Test HTTP direct via curl pour voir l'erreur complète
echo "\n3. Test HTTP direct API...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/regions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "HTTP Code: {$httpCode}\n";
echo "Response Headers:\n{$headers}\n";
echo "Response Body Length: " . strlen($body) . " bytes\n";

// Look for specific error patterns
if (stripos($body, 'Fatal error') !== false) {
    echo "\n🔥 FATAL ERROR DETECTED:\n";
    preg_match('/Fatal error: ([^<\n]+)/', $body, $matches);
    if ($matches) {
        echo "Error: " . $matches[1] . "\n";
    }
}

if (stripos($body, 'SQLSTATE') !== false) {
    echo "\n🔥 SQL ERROR DETECTED:\n";
    preg_match('/SQLSTATE\[[^\]]+\]: [^<]+/', $body, $matches);
    if ($matches) {
        echo "SQL Error: " . $matches[0] . "\n";
    }
}

if (stripos($body, 'TopoclimbCH\\') !== false && stripos($body, 'Exception') !== false) {
    echo "\n🔥 FRAMEWORK EXCEPTION DETECTED:\n";
    preg_match('/TopoclimbCH\\\\[^:]+: [^<\n]+/', $body, $matches);
    if ($matches) {
        echo "Exception: " . $matches[0] . "\n";
    }
}

// Show first part of error response for manual inspection
if ($httpCode >= 400 && strlen($body) > 100) {
    echo "\nFIRST 800 CHARS OF ERROR RESPONSE:\n";
    echo str_repeat("-", 50) . "\n";
    echo substr($body, 0, 800) . "\n";
    echo str_repeat("-", 50) . "\n";
}

echo "\n=== DEBUG COMPLETED ===\n";