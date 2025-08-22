<?php

// Script pour correction production basée sur la VRAIE structure MySQL

echo "=== CORRECTIONS PRODUCTION MYSQL ===\n\n";

echo "1. ROUTECONTROLLER - Supprimer 'code'\n";
echo "Fichier: src/Controllers/RouteController.php ligne 476\n";
echo "AVANT: SELECT id, name, code FROM climbing_difficulty_systems\n";
echo "APRÈS: SELECT id, name FROM climbing_difficulty_systems\n";

echo "\n2. MÉDIAS - Corriger les requêtes\n";
echo "Structure production:\n";
echo "- climbing_media.media_type (pas file_type)\n";
echo "- climbing_media_relationships.entity_type\n";

echo "\nRequêtes à corriger:\n";

echo "\nSiteController - show():\n";
echo "AVANT: m.entity_type = 'site' AND m.entity_id = ?\n";
echo "APRÈS: mr.entity_type = 'site' AND mr.entity_id = ?\n";

echo "\nSectorController - show():\n"; 
echo "AVANT: m.entity_type = 'sector' AND m.entity_id = ?\n";
echo "APRÈS: mr.entity_type = 'sector' AND mr.entity_id = ?\n";

echo "\nRequête corrigée:\n";
echo "SELECT m.id, m.title, m.file_path, m.media_type, m.created_at\n";
echo "FROM climbing_media m \n";
echo "JOIN climbing_media_relationships mr ON m.id = mr.media_id\n";
echo "WHERE mr.entity_type = ? AND mr.entity_id = ? AND m.is_public = 1\n";
echo "ORDER BY mr.relationship_type, mr.sort_order\n";

echo "\n3. DÉPLOIEMENT\n";
echo "Actions nécessaires:\n";
echo "- Modifier RouteController.php\n";
echo "- Modifier SiteController.php\n"; 
echo "- Modifier SectorController.php\n";
echo "- Tester /routes/create\n";
echo "- Tester /sites/21 (médias)\n";
echo "- Tester /sectors/12 (médias)\n";

?>