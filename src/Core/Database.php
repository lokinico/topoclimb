<?php

namespace TopoclimbCH\Core;

use PDO;
use PDOException;

class Database
{
    /**
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * @var string
     */
    private string $host;

    /**
     * @var string
     */
    private string $dbName;

    /**
     * @var string
     */
    private string $username;

    /**
     * @var string
     */
    private string $password;

    /**
     * Database constructor.
     *
     * @param string $host
     * @param string $dbName
     * @param string $username
     * @param string $password
     */
    public function __construct(string $host, string $dbName, string $username, string $password)
    {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Get the database connection.
     *
     * @return PDO
     * @throws PDOException
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new PDOException("Connection failed: " . $e->getMessage(), $e->getCode());
            }
        }

        return $this->connection;
    }
}