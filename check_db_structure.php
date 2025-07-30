<?php
$db = new PDO('sqlite:climbing_sqlite.db');
$columns = $db->query('PRAGMA table_info(users)')->fetchAll();
echo "Colonnes de la table users:\n";
foreach ($columns as $col) {
    echo "  - " . $col['name'] . " (" . $col['type'] . ")" . ($col['notnull'] ? " NOT NULL" : "") . "\n";
}