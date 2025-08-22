<?php
// quick_fix_active.php - Correction rapide colonnes active
require_once __DIR__ . '/bootstrap.php';

$db = TopoclimbCH\Core\Database::getInstance();

$queries = [
    "ALTER TABLE climbing_regions ADD COLUMN active INTEGER DEFAULT 1",
    "ALTER TABLE climbing_sites ADD COLUMN active INTEGER DEFAULT 1", 
    "ALTER TABLE climbing_routes ADD COLUMN active INTEGER DEFAULT 1",
    "UPDATE climbing_regions SET active = 1",
    "UPDATE climbing_sites SET active = 1",
    "UPDATE climbing_routes SET active = 1", 
    "UPDATE climbing_sectors SET active = 1"
];

foreach ($queries as $query) {
    try {
        $db->query($query);
        echo "✅ " . substr($query, 0, 50) . "...\n";
    } catch (\Exception $e) {
        echo "⚠️  " . substr($query, 0, 30) . "... - " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Toutes les tables ont maintenant la colonne 'active' = 1\n";