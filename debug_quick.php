<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "=== DEBUG SECTORS RAPIDE ===\n\n";

// Test connexion DB directe
$pdo = new PDO("mysql:host=localhost;dbname=topoclimb;charset=utf8mb4", "root", "Yb23qrI8F\!", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo "✅ DB connectée\n";

// Compter secteurs
$total = $pdo->query("SELECT COUNT(*) as c FROM climbing_sectors")->fetch()["c"];
echo "Total secteurs: $total\n";

$active = $pdo->query("SELECT COUNT(*) as c FROM climbing_sectors WHERE active = 1")->fetch()["c"];
echo "Secteurs actifs: $active\n";

if ($active == 0) {
    echo "❌ AUCUN SECTEUR ACTIF\!\n";
    exit;
}

// Test requête exacte
$sectors = $pdo->query("
    SELECT s.id, s.name, s.region_id, r.name as region_name
    FROM climbing_sectors s 
    LEFT JOIN climbing_regions r ON s.region_id = r.id 
    WHERE s.active = 1
    ORDER BY s.name ASC
    LIMIT 5
")->fetchAll();

echo "Secteurs trouvés: " . count($sectors) . "\n";
foreach ($sectors as $s) {
    echo "- {$s[\"name\"]} (région: {$s[\"region_name\"]})\n";
}
?>
