<?php

// Script pour créer les tables d'équipement
try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('migration_equipment_tables.sql');
    $db->exec($sql);
    
    echo "Tables d'équipement créées avec succès\n";
} catch (Exception $e) {
    echo "Erreur lors de la création des tables d'équipement: " . $e->getMessage() . "\n";
}