<?php

// Script pour créer les tables de monitoring
try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('create_monitoring_tables.sql');
    $db->exec($sql);
    
    echo "Tables de monitoring créées avec succès\n";
} catch (Exception $e) {
    echo "Erreur lors de la création des tables: " . $e->getMessage() . "\n";
}