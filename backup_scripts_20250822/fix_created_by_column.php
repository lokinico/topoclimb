<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    // Ajouter la colonne created_by
    try {
        $db->query('ALTER TABLE climbing_regions ADD COLUMN created_by INTEGER');
        echo '✅ Colonne created_by ajoutée' . PHP_EOL;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate') !== false) {
            echo '⚠️ Colonne created_by déjà existante' . PHP_EOL;
        } else {
            echo '❌ Erreur: ' . $e->getMessage() . PHP_EOL;
        }
    }
    
    // Ajouter updated_by aussi pour la cohérence
    try {
        $db->query('ALTER TABLE climbing_regions ADD COLUMN updated_by INTEGER');
        echo '✅ Colonne updated_by ajoutée' . PHP_EOL;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate') !== false) {
            echo '⚠️ Colonne updated_by déjà existante' . PHP_EOL;
        } else {
            echo '❌ Erreur: ' . $e->getMessage() . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . PHP_EOL;
}