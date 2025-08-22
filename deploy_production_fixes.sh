#!/bin/bash

echo "🔧 DÉPLOIEMENT CORRECTIONS PRODUCTION"
echo "====================================="

# 1. Sauvegarde
echo "📦 Sauvegarde base de données..."
mysqldump -u root -pnibaechl123. topoclimb_prod > backup_before_fixes_$(date +%Y%m%d_%H%M%S).sql

# 2. Corrections DB sur production
echo "🔍 Corrections base de données production..."
mysql -u root -pnibaechl123. topoclimb_prod << 'EOF'
-- Ajouter colonne 'code' si manquante
ALTER TABLE climbing_difficulty_systems 
ADD COLUMN IF NOT EXISTS code VARCHAR(20) AFTER name;

-- Ajouter colonnes médias si manquantes
ALTER TABLE climbing_media 
ADD COLUMN IF NOT EXISTS entity_type VARCHAR(50) DEFAULT 'unknown';

ALTER TABLE climbing_media 
ADD COLUMN IF NOT EXISTS file_type VARCHAR(50) DEFAULT 'image';

-- Remplir les codes manquants
UPDATE climbing_difficulty_systems 
SET code = CASE 
    WHEN name LIKE '%Française%' THEN 'FR'
    WHEN name LIKE '%Font%' THEN 'FONT' 
    WHEN name LIKE '%British%' THEN 'UK'
    WHEN name LIKE '%Yosemite%' THEN 'YDS'
    ELSE LEFT(UPPER(name), 5)
END
WHERE code IS NULL OR code = '';

-- Migrer données médias depuis relations
UPDATE climbing_media m
JOIN climbing_media_relationships mr ON m.id = mr.media_id
SET m.entity_type = mr.entity_type,
    m.file_type = COALESCE(m.media_type, 'image')
WHERE m.entity_type IS NULL OR m.entity_type = 'unknown';

EOF

echo "✅ Corrections DB terminées"

# 3. Déployer fichiers de routes
echo "📄 Déploiement routes..."
cp config/routes.php /home/httpd/vhosts/topoclimb.ch/topoclimb/config/routes.php

# 4. Test rapide
echo "🧪 Test des corrections..."
curl -s "https://topoclimb.ch/routes/create" | grep -q "500" && echo "❌ Routes/create KO" || echo "✅ Routes/create OK"
curl -s "https://topoclimb.ch/sites/21" | grep -q "médias" && echo "✅ Médias sites OK" || echo "⚠️ Médias sites à vérifier"

echo "🎉 DÉPLOIEMENT TERMINÉ"
echo "Vérifiez manuellement:"
echo "- /routes/create"
echo "- /sites/{id}/edit + formulaire POST"
echo "- /regions/{id}/edit + formulaire POST"