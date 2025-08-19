<?php

namespace TopoclimbCH\Core;

/**
 * Gestionnaire de compatibilité base de données dev/prod
 * Gère les différences de colonnes entre SQLite (dev) et MySQL (prod)
 */
class DatabaseCompatibility
{
    private Database $db;
    private static $columnCache = [];
    private static $isProductionEnvironment = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Vérifie si on est en environnement de production
     */
    public function isProduction(): bool
    {
        if (self::$isProductionEnvironment === null) {
            // Détection basée sur le driver PDO
            $driver = $this->db->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            self::$isProductionEnvironment = $driver === 'mysql';
        }
        
        return self::$isProductionEnvironment;
    }

    /**
     * Vérifie si une colonne existe dans une table
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = "{$tableName}.{$columnName}";
        
        if (isset(self::$columnCache[$cacheKey])) {
            return self::$columnCache[$cacheKey];
        }

        try {
            if ($this->isProduction()) {
                // MySQL - utilise INFORMATION_SCHEMA
                $result = $this->db->fetchOne(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ? 
                     AND COLUMN_NAME = ?",
                    [$tableName, $columnName]
                );
                $exists = !empty($result);
            } else {
                // SQLite - utilise PRAGMA
                $columns = $this->db->fetchAll("PRAGMA table_info({$tableName})");
                $exists = false;
                foreach ($columns as $col) {
                    if ($col['name'] === $columnName) {
                        $exists = true;
                        break;
                    }
                }
            }
            
            self::$columnCache[$cacheKey] = $exists;
            return $exists;
            
        } catch (\Exception $e) {
            error_log("DatabaseCompatibility: Erreur vérification colonne {$tableName}.{$columnName}: " . $e->getMessage());
            self::$columnCache[$cacheKey] = false;
            return false;
        }
    }

    /**
     * Construit une requête SELECT compatible avec les colonnes disponibles
     */
    public function buildCompatibleSelect(string $baseQuery, array $conditionalColumns = []): string
    {
        $finalQuery = $baseQuery;
        
        foreach ($conditionalColumns as $table => $columns) {
            foreach ($columns as $column => $fallback) {
                $columnRef = "{$table}.{$column}";
                
                if (strpos($finalQuery, $columnRef) !== false) {
                    if (!$this->columnExists($table, $column)) {
                        // Remplacer par fallback ou NULL
                        if (is_string($fallback)) {
                            $finalQuery = str_replace($columnRef, $fallback, $finalQuery);
                        } else {
                            $finalQuery = str_replace($columnRef, 'NULL as ' . $column, $finalQuery);
                        }
                        error_log("DatabaseCompatibility: Colonne {$columnRef} manquante, utilisation fallback");
                    }
                }
            }
        }
        
        return $finalQuery;
    }

    /**
     * Exécute une requête avec gestion des colonnes manquantes
     */
    public function safeQuery(string $query, array $params = [], array $conditionalColumns = []): array
    {
        try {
            // Première tentative avec la requête originale
            return $this->db->fetchAll($query, $params);
        } catch (\Exception $e) {
            // Si erreur de colonne manquante, essayer avec fallback
            if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), 'no such column') !== false) {
                error_log("DatabaseCompatibility: Colonne manquante détectée, utilisation fallback");
                
                $compatibleQuery = $this->buildCompatibleSelect($query, $conditionalColumns);
                if ($compatibleQuery !== $query) {
                    return $this->db->fetchAll($compatibleQuery, $params);
                }
            }
            
            throw $e;
        }
    }

    /**
     * Version pour une seule ligne
     */
    public function safeQueryOne(string $query, array $params = [], array $conditionalColumns = []): ?array
    {
        try {
            return $this->db->fetchOne($query, $params);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), 'no such column') !== false) {
                error_log("DatabaseCompatibility: Colonne manquante détectée, utilisation fallback");
                
                $compatibleQuery = $this->buildCompatibleSelect($query, $conditionalColumns);
                if ($compatibleQuery !== $query) {
                    return $this->db->fetchOne($compatibleQuery, $params);
                }
            }
            
            throw $e;
        }
    }

    /**
     * Préparation des fallbacks pour climbing_media
     */
    public function getMediaQueryFallbacks(): array
    {
        return [
            'climbing_media' => [
                'entity_type' => "'unknown'", // Valeur par défaut
                'file_type' => "'image'"     // Supposer que c'est une image
            ],
            'm' => [ // Alias couramment utilisé
                'entity_type' => "'unknown'",
                'file_type' => "'image'"
            ]
        ];
    }

    /**
     * Construction requête média compatible
     */
    public function buildMediaQuery(string $baseSelect, string $whereClause = '', array $params = []): array
    {
        $fallbacks = $this->getMediaQueryFallbacks();
        
        $query = $baseSelect;
        if ($whereClause) {
            $query .= " WHERE " . $whereClause;
        }
        
        return $this->safeQuery($query, $params, $fallbacks);
    }

    /**
     * Détecte et répare automatiquement les requêtes courantes
     */
    public function autoFixQuery(string $query): string
    {
        // Patterns courants à corriger
        $fixes = [
            // m.file_type -> 'image' as file_type si colonne manquante
            '/m\.file_type/' => $this->columnExists('climbing_media', 'file_type') ? 'm.file_type' : "'image' as file_type",
            
            // m.entity_type -> 'unknown' as entity_type si colonne manquante  
            '/m\.entity_type/' => $this->columnExists('climbing_media', 'entity_type') ? 'm.entity_type' : "'unknown' as entity_type"
        ];
        
        $fixedQuery = $query;
        foreach ($fixes as $pattern => $replacement) {
            if ($pattern !== $replacement) { // Seulement si différent (colonne manquante)
                $fixedQuery = preg_replace($pattern, $replacement, $fixedQuery);
            }
        }
        
        if ($fixedQuery !== $query) {
            error_log("DatabaseCompatibility: Requête automatiquement corrigée");
        }
        
        return $fixedQuery;
    }
}