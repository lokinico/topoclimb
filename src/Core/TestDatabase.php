<?php

namespace TopoclimbCH\Core;

use PDO;
use PDOException;

/**
 * Classe Database modifiée pour tests avec SQLite
 */
class TestDatabase
{
    private ?PDO $connection = null;
    private array $config;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            // Configuration SQLite par défaut pour les tests
            $this->config = [
                'driver' => 'sqlite',
                'database' => 'climbing_sqlite.db', // Chemin vers SQLite
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            ];
        } else {
            $this->config = $config;
        }
    }

    /**
     * Établit la connexion à la base de données
     */
    public function connect(): PDO
    {
        if ($this->connection === null) {
            try {
                if (isset($this->config['driver']) && $this->config['driver'] === 'sqlite') {
                    // Connexion SQLite
                    $dsn = "sqlite:" . $this->config['database'];
                    $this->connection = new PDO($dsn, null, null, $this->config['options']);
                } else {
                    // Connexion MySQL (configuration originale)
                    $dsn = sprintf(
                        "mysql:host=%s;dbname=%s;charset=%s;port=%d",
                        $this->config['host'],
                        $this->config['database'],
                        $this->config['charset'] ?? 'utf8mb4',
                        $this->config['port'] ?? 3306
                    );
                    
                    $this->connection = new PDO(
                        $dsn,
                        $this->config['username'],
                        $this->config['password'],
                        $this->config['options'] ?? []
                    );
                }
                
                error_log("Database: Connexion établie avec succès");
                
            } catch (PDOException $e) {
                error_log("Database: Erreur de connexion - " . $e->getMessage());
                throw new \RuntimeException("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * Exécute une requête et retourne tous les résultats
     */
    public function fetchAll(string $query, array $params = []): array
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Exécute une requête et retourne un seul résultat
     */
    public function fetchOne(string $query, array $params = []): ?array
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Database fetchOne error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Exécute une requête sans retour
     */
    public function query(string $sql, array $params = []): \PDOStatement|bool
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insère des données et retourne l'ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->query($sql, $data);
        
        if ($stmt) {
            return (int) $this->connect()->lastInsertId();
        }
        
        return 0;
    }

    /**
     * Met à jour des données
     */
    public function update(string $table, array $data, array $conditions): bool
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        
        $whereClause = [];
        foreach (array_keys($conditions) as $column) {
            $whereClause[] = "{$column} = :{$column}_cond";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . 
               " WHERE " . implode(' AND ', $whereClause);
        
        // Fusionner les paramètres
        $params = $data;
        foreach ($conditions as $key => $value) {
            $params["{$key}_cond"] = $value;
        }
        
        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }

    /**
     * Supprime des données
     */
    public function delete(string $table, array $conditions): bool
    {
        $whereClause = [];
        foreach (array_keys($conditions) as $column) {
            $whereClause[] = "{$column} = :{$column}";
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);
        
        $stmt = $this->query($sql, $conditions);
        return $stmt !== false;
    }

    /**
     * Retourne le nombre de lignes affectées
     */
    public function getAffectedRows(): int
    {
        if ($this->connection) {
            $stmt = $this->connection->query("SELECT changes()");
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }
}
