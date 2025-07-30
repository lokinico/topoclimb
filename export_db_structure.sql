-- Requêtes SQL pour identifier votre structure exacte
-- Exécutez dans l'ordre pour trouver vos tables

-- 1. LISTER TOUTES VOS TABLES (peu importe le préfixe)
SHOW TABLES;

-- 2. Chercher table users avec différents préfixes possibles
SHOW TABLES LIKE '%users%';

-- 3. Si table users sans préfixe
DESCRIBE users;

-- 4. Si table sh139940_users existe  
DESCRIBE sh139940_users;

-- 5. Export détaillé si table users sans préfixe
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

-- 6. Export détaillé si table avec préfixe sh139940_
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sh139940_users'
ORDER BY ORDINAL_POSITION;

-- 7. LISTE COMPLÈTE de toutes vos tables
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;