-- Requête SQL pour exporter toute la structure de votre base de données MySQL
-- Exécutez cette requête sur votre serveur de production

-- 1. Export de la structure de toutes les tables
SELECT 
    CONCAT('-- Structure de la table: ', TABLE_NAME) as export_line
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
UNION ALL
SELECT 
    CONCAT('SHOW CREATE TABLE `', TABLE_NAME, '`;') as export_line
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY export_line;

-- 2. Alternative: Export complet avec mysqldump (à exécuter en ligne de commande)
-- mysqldump -u USERNAME -p --no-data --routines --triggers DATABASE_NAME > structure_complete.sql

-- 3. Export spécifique de la table users seulement
SHOW CREATE TABLE users;

-- 4. Description détaillée de la table users
DESCRIBE users;

-- 5. Export des colonnes de la table users avec détails
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;