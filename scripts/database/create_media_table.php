<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

try {
    echo "=== Création table climbing_media ===\n\n";
    
    $db = new Database();
    
    // Déterminer le type de base
    $isMySQL = $db->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
    
    if ($isMySQL) {
        // Version MySQL/MariaDB
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS climbing_media (
                id INT PRIMARY KEY AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                title VARCHAR(255),
                description TEXT,
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                file_size INT,
                mime_type VARCHAR(100),
                display_order INT DEFAULT 0,
                is_primary TINYINT(1) DEFAULT 0,
                alt_text VARCHAR(255),
                active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT,
                updated_by INT,
                
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_active (active),
                INDEX idx_display_order (display_order)
            )
        ";
    } else {
        // Version SQLite
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS climbing_media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INTEGER NOT NULL,
                title VARCHAR(255),
                description TEXT,
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                file_size INTEGER,
                mime_type VARCHAR(100),
                display_order INTEGER DEFAULT 0,
                is_primary INTEGER DEFAULT 0,
                alt_text VARCHAR(255),
                active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER,
                updated_by INTEGER
            )
        ";
    }
    
    $db->query($createTableSQL);
    echo "✅ Table climbing_media créée\n";
    
    // Créer index pour SQLite
    if (!$isMySQL) {
        $db->query("CREATE INDEX IF NOT EXISTS idx_entity ON climbing_media(entity_type, entity_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_active ON climbing_media(active)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_display_order ON climbing_media(display_order)");
        echo "✅ Index créés pour SQLite\n";
    }
    
    // Créer dossier uploads si nécessaire
    $uploadsDir = __DIR__ . '/public/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        echo "✅ Dossier uploads créé\n";
    }
    
    // Créer sous-dossiers par type
    $subDirs = ['sectors', 'regions', 'sites', 'routes', 'books', 'users'];
    foreach ($subDirs as $subDir) {
        $path = $uploadsDir . '/' . $subDir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            echo "✅ Sous-dossier uploads/{$subDir} créé\n";
        }
    }
    
    // Vérification finale
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM climbing_media")['count'];
    echo "\n✅ Table climbing_media opérationnelle\n";
    echo "📊 Médias actuels: {$count}\n";
    
    // 6. Créer table climbing_media_relationships
    echo "\n6. Création table climbing_media_relationships...\n";
    
    if ($isMySQL) {
        // Version MySQL/MariaDB
        $createRelationshipsSQL = "
            CREATE TABLE IF NOT EXISTS climbing_media_relationships (
                id INT PRIMARY KEY AUTO_INCREMENT,
                media_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                relationship_type VARCHAR(50) NOT NULL DEFAULT 'gallery',
                sort_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_media_id (media_id),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_relationship (relationship_type),
                UNIQUE KEY unique_media_entity (media_id, entity_type, entity_id, relationship_type)
            )
        ";
    } else {
        // Version SQLite
        $createRelationshipsSQL = "
            CREATE TABLE IF NOT EXISTS climbing_media_relationships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                media_id INTEGER NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INTEGER NOT NULL,
                relationship_type VARCHAR(50) NOT NULL DEFAULT 'gallery',
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE(media_id, entity_type, entity_id, relationship_type)
            )
        ";
    }
    
    $db->query($createRelationshipsSQL);
    echo "✅ Table climbing_media_relationships créée\n";
    
    // Créer index pour SQLite
    if (!$isMySQL) {
        $db->query("CREATE INDEX IF NOT EXISTS idx_media_id ON climbing_media_relationships(media_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_entity ON climbing_media_relationships(entity_type, entity_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_relationship ON climbing_media_relationships(relationship_type)");
        echo "✅ Index créés pour climbing_media_relationships\n";
    }

    echo "\n🎉 MIGRATION MÉDIAS TERMINÉE !\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}