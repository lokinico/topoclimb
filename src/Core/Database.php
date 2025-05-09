<?php

namespace TopoclimbCH\Core;

use PDO;
use PDOException;

class Database
{
    /**
     * Instance unique de la classe Database (pattern Singleton)
     *
     * @var Database|null
     */
    private static ?Database $instance = null;

    /**
     * Instance PDO pour la connexion à la base de données
     *
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * Paramètres de connexion à la base de données
     *
     * @var array
     */
    private array $config;

    /**
     * Constructeur privé pour empêcher l'instanciation directe (pattern Singleton)
     */
    private function __construct()
    {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_DATABASE'] ?? 'sh139940_',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];
    }

    /**
     * Empêche le clonage de l'instance (pattern Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Récupère l'instance unique de la classe Database (pattern Singleton)
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Établit une connexion à la base de données
     *
     * @return PDO
     * @throws PDOException Si la connexion échoue
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            try {
                $this->connection = new PDO(
                    $dsn,
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['options']
                );
            } catch (PDOException $e) {
                throw new PDOException("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * Exécute une requête SQL et retourne le résultat
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres pour la requête préparée
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /**
     * Retourne un seul enregistrement
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres pour la requête préparée
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->query($sql, $params);
        $result = $statement->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Retourne tous les enregistrements
     *
     * @param string $sql Requête SQL
     * @param array $params Paramètres pour la requête préparée
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->query($sql, $params);
        return $statement->fetchAll();
    }

    /**
     * Insère un enregistrement et retourne l'ID généré
     *
     * @param string $table Nom de la table
     * @param array $data Données à insérer
     * @return int|null ID généré ou null en cas d'échec
     */
    public function insert(string $table, array $data): ?int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->query($sql, array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Met à jour un ou plusieurs enregistrements
     *
     * @param string $table Nom de la table
     * @param array $data Données à mettre à jour
     * @param string $where Condition WHERE
     * @param array $params Paramètres pour la condition WHERE
     * @return int Nombre de lignes affectées
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $statement = $this->query($sql, array_merge(array_values($data), $params));
        
        return $statement->rowCount();
    }

    /**
     * Supprime un ou plusieurs enregistrements
     *
     * @param string $table Nom de la table
     * @param string $where Condition WHERE
     * @param array $params Paramètres pour la condition WHERE
     * @return int Nombre de lignes affectées
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $statement = $this->query($sql, $params);
        
        return $statement->rowCount();
    }

    /**
     * Démarre une transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Valide une transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Annule une transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }
}