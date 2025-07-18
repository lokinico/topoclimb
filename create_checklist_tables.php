<?php

// Script pour créer les tables de checklists de sécurité
try {
    $db = new PDO('sqlite:storage/climbing_sqlite.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents('migration_checklist_tables.sql');
    $db->exec($sql);
    
    echo "Tables de checklists créées avec succès\n";
} catch (Exception $e) {
    echo "Erreur lors de la création des tables de checklists: " . $e->getMessage() . "\n";
}