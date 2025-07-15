<?php

namespace TopoclimbCH\Core;

/**
 * Classe utilitaire pour gérer la compatibilité SQL entre MySQL et SQLite
 */
class SqlCompatibility
{
    /**
     * Détecte le driver de base de données
     */
    public static function getDriver(): string
    {
        return $_ENV['DB_DRIVER'] ?? 'mysql';
    }

    /**
     * Fonction NOW() compatible
     */
    public static function now(): string
    {
        return self::getDriver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    /**
     * Fonction CURRENT_TIMESTAMP compatible
     */
    public static function currentTimestamp(): string
    {
        return self::getDriver() === 'sqlite' ? "datetime('now')" : 'CURRENT_TIMESTAMP';
    }

    /**
     * Fonction DATE_FORMAT compatible
     */
    public static function dateFormat(string $date, string $format): string
    {
        if (self::getDriver() === 'sqlite') {
            // Conversion basique pour SQLite
            switch ($format) {
                case '%Y-%m-01':
                    return "date('$date', 'start of month')";
                case '%Y-%m-%d':
                    return "date('$date')";
                case '%Y':
                    return "strftime('%Y', '$date')";
                case '%m':
                    return "strftime('%m', '$date')";
                default:
                    return "strftime('$format', '$date')";
            }
        } else {
            return "DATE_FORMAT($date, '$format')";
        }
    }

    /**
     * Fonction LIMIT compatible
     */
    public static function limit(int $limit, int $offset = 0): string
    {
        if (self::getDriver() === 'sqlite') {
            return $offset > 0 ? "LIMIT $limit OFFSET $offset" : "LIMIT $limit";
        } else {
            return $offset > 0 ? "LIMIT $offset, $limit" : "LIMIT $limit";
        }
    }

    /**
     * INSERT ON DUPLICATE KEY UPDATE compatible
     */
    public static function insertOrReplace(string $table, array $columns): string
    {
        $columnsList = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        if (self::getDriver() === 'sqlite') {
            return "INSERT OR REPLACE INTO $table ($columnsList) VALUES ($placeholders)";
        } else {
            $updates = array_map(fn($col) => "$col = VALUES($col)", $columns);
            $updateClause = implode(', ', $updates);
            return "INSERT INTO $table ($columnsList) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
        }
    }

    /**
     * Auto-increment compatible
     */
    public static function autoIncrement(): string
    {
        return self::getDriver() === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    }

    /**
     * Fonction CONCAT compatible
     */
    public static function concat(array $parts): string
    {
        if (self::getDriver() === 'sqlite') {
            return implode(' || ', $parts);
        } else {
            return 'CONCAT(' . implode(', ', $parts) . ')';
        }
    }

    /**
     * Fonction IFNULL/COALESCE compatible
     */
    public static function ifNull(string $column, string $default): string
    {
        if (self::getDriver() === 'sqlite') {
            return "COALESCE($column, $default)";
        } else {
            return "IFNULL($column, $default)";
        }
    }

    /**
     * Fonction DATEDIFF compatible
     */
    public static function dateDiff(string $date1, string $date2): string
    {
        if (self::getDriver() === 'sqlite') {
            return "julianday($date1) - julianday($date2)";
        } else {
            return "DATEDIFF($date1, $date2)";
        }
    }

    /**
     * Fonction BOOLEAN compatible
     */
    public static function boolean(bool $value): string
    {
        if (self::getDriver() === 'sqlite') {
            return $value ? '1' : '0';
        } else {
            return $value ? 'TRUE' : 'FALSE';
        }
    }

    /**
     * Renvoie le type de données approprié pour les timestamp
     */
    public static function timestampType(): string
    {
        return self::getDriver() === 'sqlite' ? 'DATETIME' : 'TIMESTAMP';
    }

    /**
     * Renvoie le type de données approprié pour les boolean
     */
    public static function booleanType(): string
    {
        return self::getDriver() === 'sqlite' ? 'INTEGER' : 'BOOLEAN';
    }

    /**
     * Fonction COUNT DISTINCT compatible
     */
    public static function countDistinct(string $column): string
    {
        return "COUNT(DISTINCT $column)";
    }

    /**
     * Fonction GROUP_CONCAT compatible
     */
    public static function groupConcat(string $column, string $separator = ','): string
    {
        if (self::getDriver() === 'sqlite') {
            return "GROUP_CONCAT($column, '$separator')";
        } else {
            return "GROUP_CONCAT($column SEPARATOR '$separator')";
        }
    }

    /**
     * Fonction pour gérer les REGEXP
     */
    public static function regexp(string $column, string $pattern): string
    {
        if (self::getDriver() === 'sqlite') {
            // SQLite utilise LIKE pour les patterns simples
            return "$column LIKE '$pattern'";
        } else {
            return "$column REGEXP '$pattern'";
        }
    }

    /**
     * Fonction pour créer des contraintes de clés étrangères
     */
    public static function foreignKey(string $column, string $refTable, string $refColumn = 'id'): string
    {
        if (self::getDriver() === 'sqlite') {
            return "FOREIGN KEY ($column) REFERENCES $refTable($refColumn)";
        } else {
            return "FOREIGN KEY ($column) REFERENCES $refTable($refColumn) ON DELETE CASCADE ON UPDATE CASCADE";
        }
    }
}