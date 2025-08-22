<?php
require_once 'bootstrap.php';

use TopoclimbCH\Core\Database;

$db = new Database();

echo "=== CORRECTIONS BASÉES SUR LA VRAIE STRUCTURE ===\n\n";

echo "1. TEST RouteController - requête difficulty_systems\n";
try {
    // Test requête SANS colonne 'code' 
    $systems = $db->fetchAll("SELECT id, name FROM climbing_difficulty_systems WHERE active = 1 ORDER BY name ASC");
    echo "✅ Requête difficulty_systems OK: " . count($systems) . " systèmes\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n2. TEST Médias avec relations - Site 21\n";
try {
    // Nouvelle requête avec relations
    $media = $db->fetchAll("
        SELECT m.id, m.title, m.file_path, m.media_type, m.created_at,
               mr.entity_type, mr.entity_id, mr.relationship_type
        FROM climbing_media m 
        JOIN climbing_media_relationships mr ON m.id = mr.media_id
        WHERE mr.entity_type = 'site' AND mr.entity_id = ? AND m.is_public = 1
        ORDER BY mr.relationship_type, mr.sort_order
    ", [21]);
    echo "✅ Médias site 21: " . count($media) . " fichiers\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n3. TEST Médias avec relations - Secteur 12\n";
try {
    $media = $db->fetchAll("
        SELECT m.id, m.title, m.file_path, m.media_type, m.created_at,
               mr.relationship_type
        FROM climbing_media m 
        JOIN climbing_media_relationships mr ON m.id = mr.media_id
        WHERE mr.entity_type = 'sector' AND mr.entity_id = ? AND m.is_public = 1
        ORDER BY mr.relationship_type, mr.sort_order
    ", [12]);
    echo "✅ Médias secteur 12: " . count($media) . " fichiers\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n4. VÉRIFICATION Structure climbing_difficulty_systems\n";
try {
    $sample = $db->fetchOne("SELECT id, name, description FROM climbing_difficulty_systems LIMIT 1");
    echo "✅ Colonnes disponibles: " . implode(", ", array_keys($sample)) . "\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n5. VÉRIFICATION Structure climbing_media\n";
try {
    $sample = $db->fetchOne("SELECT * FROM climbing_media LIMIT 1");
    if ($sample) {
        echo "✅ Colonnes climbing_media: " . implode(", ", array_keys($sample)) . "\n";
    } else {
        echo "⚠️ Aucun média trouvé\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== RÉSUMÉ ===\n";
echo "- RouteController: Supprimer 'code' de la requête\n";
echo "- Médias: Utiliser climbing_media_relationships\n";
echo "- Routes POST: Déjà corrigées dans routes.php\n";