<?php
// debug_api_errors.php
// Specific debugging for API endpoint SQL errors

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

function testApiEndpoint($endpoint) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TESTING: $endpoint\n";
    echo str_repeat("=", 60) . "\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:8000$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Debug-Script/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response length: " . strlen($body) . " bytes\n\n";
    
    // Look for specific error patterns
    if (stripos($body, 'SQLSTATE') !== false) {
        echo "🔥 SQL STATE ERROR DETECTED:\n";
        preg_match('/SQLSTATE\[[^\]]+\]: [^<]+/', $body, $matches);
        if ($matches) {
            echo "   " . trim($matches[0]) . "\n";
        }
    }
    
    if (stripos($body, 'Unknown column') !== false) {
        echo "🔥 UNKNOWN COLUMN ERROR:\n";
        preg_match("/Unknown column '[^']+'/", $body, $matches);
        if ($matches) {
            echo "   " . $matches[0] . "\n";
        }
    }
    
    if (stripos($body, 'TopoclimbCH\\') !== false && stripos($body, 'Exception') !== false) {
        echo "🔥 FRAMEWORK EXCEPTION:\n";
        preg_match('/TopoclimbCH\\\\[^:]+: [^<\n]+/', $body, $matches);
        if ($matches) {
            echo "   " . trim($matches[0]) . "\n";
        }
    }
    
    if (stripos($body, 'Fatal error') !== false) {
        echo "🔥 PHP FATAL ERROR:\n";
        preg_match('/Fatal error: [^<\n]+/', $body, $matches);
        if ($matches) {
            echo "   " . trim($matches[0]) . "\n";
        }
    }
    
    // Check for valid JSON response
    if ($httpCode == 200) {
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Valid JSON response\n";
            if (isset($json['data'])) {
                echo "   Contains " . count($json['data']) . " data items\n";
            }
        } else {
            echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
        }
    }
    
    // Show first 500 chars of error output
    if ($httpCode >= 400) {
        echo "\nFIRST 500 CHARS OF ERROR OUTPUT:\n";
        echo str_repeat("-", 40) . "\n";
        echo substr($body, 0, 500) . "\n";
        echo str_repeat("-", 40) . "\n";
    }
}

function testDatabaseQueries() {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TESTING DATABASE QUERIES DIRECTLY\n";
    echo str_repeat("=", 60) . "\n";
    
    try {
        $db = new Database();
        
        // Test queries that might be used in API controllers
        $testQueries = [
            'regions_basic' => "SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC LIMIT 5",
            'sites_basic' => "SELECT * FROM climbing_sites WHERE active = 1 ORDER BY name ASC LIMIT 5", 
            'routes_basic' => "SELECT * FROM climbing_routes LIMIT 5",
            'books_basic' => "SELECT * FROM climbing_books LIMIT 5",
            'sectors_with_join' => "SELECT s.*, r.name as region_name FROM climbing_sectors s LEFT JOIN climbing_regions r ON s.region_id = r.id LIMIT 5"
        ];
        
        foreach ($testQueries as $name => $query) {
            echo "\nTesting $name:\n";
            echo "Query: $query\n";
            try {
                $result = $db->fetchAll($query);
                echo "✅ Success - " . count($result) . " rows returned\n";
                
                if (!empty($result)) {
                    echo "Sample columns: " . implode(', ', array_keys($result[0])) . "\n";
                }
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Database connection error: " . $e->getMessage() . "\n";
    }
}

function checkRouteControllerMethods() {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "CHECKING CONTROLLER METHODS EXIST\n";
    echo str_repeat("=", 60) . "\n";
    
    $controllers = [
        'RegionController' => 'TopoclimbCH\\Controllers\\RegionController',
        'SiteController' => 'TopoclimbCH\\Controllers\\SiteController', 
        'RouteController' => 'TopoclimbCH\\Controllers\\RouteController',
        'BookController' => 'TopoclimbCH\\Controllers\\BookController',
        'SectorController' => 'TopoclimbCH\\Controllers\\SectorController'
    ];
    
    foreach ($controllers as $name => $class) {
        echo "\n$name:\n";
        if (class_exists($class)) {
            echo "✅ Class exists\n";
            
            $apiMethods = ['apiIndex', 'apiShow'];
            foreach ($apiMethods as $method) {
                if (method_exists($class, $method)) {
                    echo "  ✅ $method() method exists\n";
                } else {
                    echo "  ❌ $method() method missing\n";
                }
            }
        } else {
            echo "❌ Class not found\n";
        }
    }
}

// Main execution
echo "TopoclimbCH API Error Debugging\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

// Test database queries first
testDatabaseQueries();

// Check controller methods exist  
checkRouteControllerMethods();

// Test each failing API endpoint
$failingEndpoints = [
    '/api/regions',
    '/api/regions/1', 
    '/api/sites',
    '/api/sites/1',
    '/api/routes',
    '/api/routes/1',
    '/api/books',
    '/api/books/1',
    '/api/sectors',
    '/api/sectors/1'
];

foreach ($failingEndpoints as $endpoint) {
    testApiEndpoint($endpoint);
    sleep(1); // Brief pause between tests
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "API DEBUGGING COMPLETED\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";