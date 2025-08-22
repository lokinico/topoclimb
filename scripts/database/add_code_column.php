<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    try {
        $db->query('ALTER TABLE climbing_sites ADD COLUMN code VARCHAR(50)');
        echo '✅ Colonne code ajoutée (sans contrainte UNIQUE)' . PHP_EOL;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate') !== false) {
            echo '⚠️ Colonne code déjà existante' . PHP_EOL;
        } else {
            echo '❌ Erreur: ' . $e->getMessage() . PHP_EOL;
        }
    }
    
    // Générer un code pour le site existant s'il n'en a pas
    $siteWithoutCode = $db->fetchAll("SELECT id, name FROM climbing_sites WHERE code IS NULL OR code = ''");
    foreach ($siteWithoutCode as $site) {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $site['name']), 0, 5)) . sprintf('%02d', $site['id']);
        $db->query("UPDATE climbing_sites SET code = ? WHERE id = ?", [$code, $site['id']]);
        echo "✅ Code généré pour site ID {$site['id']}: {$code}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . PHP_EOL;
}