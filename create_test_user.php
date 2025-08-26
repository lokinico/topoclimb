<?php
/**
 * Créer un utilisateur de test dans la base de données
 */

echo "👤 CRÉATION UTILISATEUR DE TEST\n";
echo str_repeat("=", 30) . "\n\n";

require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    $db = new Database();
    
    echo "📊 STRUCTURE TABLE USERS:\n";
    $columns = $db->fetchAll("PRAGMA table_info(users)");
    foreach ($columns as $col) {
        echo "   - {$col['name']} ({$col['type']})\n";
    }
    
    // Vérifier si l'utilisateur de test existe déjà
    $existing = $db->fetchOne("SELECT * FROM users WHERE id = 1");
    
    if ($existing) {
        echo "\n✅ Utilisateur ID 1 existe déjà:\n";
        echo "   Username: " . ($existing['username'] ?? 'N/A') . "\n";
        if (isset($existing['email'])) {
            echo "   Email: {$existing['email']}\n";
        }
        echo "   Role: " . ($existing['role'] ?? $existing['access_level'] ?? 'N/A') . "\n";
    } else {
        echo "\n🔧 Création d'un utilisateur de test...\n";
        
        // Créer l'utilisateur de test avec seulement les colonnes qui existent
        $columnsStr = implode(', ', array_column($columns, 'name'));
        echo "Colonnes disponibles: $columnsStr\n";
        
        // Adapter l'insertion selon les colonnes disponibles
        $insertColumns = ['id', 'username', 'password'];
        $insertValues = [1, 'test-user', 'test-password'];
        
        // Ajouter les colonnes requises selon le schéma de cette DB
        $availableColumns = array_column($columns, 'name');
        
        // Colonnes probablement requises (NOT NULL)
        if (in_array('nom', $availableColumns)) {
            $insertColumns[] = 'nom';
            $insertValues[] = 'Test';
        }
        if (in_array('prenom', $availableColumns)) {
            $insertColumns[] = 'prenom';
            $insertValues[] = 'User';
        }
        if (in_array('mail', $availableColumns)) {
            $insertColumns[] = 'mail';
            $insertValues[] = 'test@localhost';
        }
        if (in_array('ville', $availableColumns)) {
            $insertColumns[] = 'ville';
            $insertValues[] = 'Test City';
        }
        if (in_array('autorisation', $availableColumns)) {
            $insertColumns[] = 'autorisation';
            $insertValues[] = 'admin'; // ou 'user', selon ce qui est attendu
        }
        if (in_array('date_registered', $availableColumns)) {
            $insertColumns[] = 'date_registered';
            $insertValues[] = date('Y-m-d H:i:s');
        }
        
        $placeholders = str_repeat('?,', count($insertValues) - 1) . '?';
        $sql = "INSERT INTO users (" . implode(', ', $insertColumns) . ") VALUES ($placeholders)";
        
        echo "SQL: $sql\n";
        echo "Valeurs: " . implode(', ', $insertValues) . "\n";
        
        $result = $db->query($sql, $insertValues);
        
        if ($result) {
            echo "✅ Utilisateur de test créé!\n";
        } else {
            echo "❌ Échec création utilisateur\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 30) . "\n";
echo "Terminé - " . date('H:i:s') . "\n";
?>