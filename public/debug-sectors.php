<?php
require_once "../bootstrap.php";
use TopoclimbCH\Core\Database;

header("Content-Type: text/plain");
echo "=== DIAGNOSTIC SECTEURS ===\n\n";

try {
    $db = new Database();
    echo "✅ Database connectée\n";
    
    $count = $db->fetchOne("SELECT COUNT(*) as c FROM climbing_sectors WHERE active = 1");
    echo "Secteurs actifs: " . $count["c"] . "\n";
    
    if ($count["c"] == 0) {
        echo "❌ AUCUN SECTEUR ACTIF\!\n";
        $total = $db->fetchOne("SELECT COUNT(*) as c FROM climbing_sectors");
        echo "Total secteurs: " . $total["c"] . "\n";
        
        $inactive = $db->fetchAll("SELECT id, name, active FROM climbing_sectors LIMIT 5");
        echo "Premiers secteurs (tous):\n";
        foreach ($inactive as $s) {
            echo "  - ID " . $s["id"] . ": " . $s["name"] . " (active=" . $s["active"] . ")\n";
        }
    } else {
        $sectors = $db->fetchAll("SELECT id, name FROM climbing_sectors WHERE active = 1 LIMIT 5");
        echo "Premiers secteurs actifs:\n";
        foreach ($sectors as $s) {
            echo "  - " . $s["name"] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
