<?php
// test-api-simple.php - Test API simple sans framework
header('Content-Type: application/json');

try {
    // Test database connection after fix
    require_once dirname(__DIR__) . '/bootstrap.php';
    $db = new \TopoclimbCH\Core\Database();
    $regions = $db->fetchAll("SELECT r.id, r.name FROM climbing_regions r WHERE r.active = 1 LIMIT 5");
    
    echo json_encode([
        'status' => 'success',
        'bootstrap' => 'ok',
        'database' => 'ok', 
        'regions_count' => count($regions),
        'regions' => $regions,
        'current_directory' => getcwd()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}