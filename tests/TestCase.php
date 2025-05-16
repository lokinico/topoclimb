<?php

namespace TopoclimbCH\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use TopoclimbCH\Core\Container;
use TopoclimbCH\Core\Database;

class TestCase extends BaseTestCase
{
    protected $container;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialiser le conteneur pour les tests
        $containerBuilder = new \TopoclimbCH\Core\ContainerBuilder();
        $this->container = $containerBuilder->build();
        Container::getInstance($this->container);

        // Initialiser la base de données de test (sqlite en mémoire)
        $this->db = $this->container->get(Database::class);

        // Exécuter les migrations pour configurer la base de données de test
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test
        $this->db = null;
        $this->container = null;
        Container::resetInstance();

        parent::tearDown();
    }

    /**
     * Exécute les migrations pour configurer la base de données de test
     */
    protected function runMigrations(): void
    {
        // Adaptez ceci à votre système de migration
        // Exemple: $this->db->query(file_get_contents(BASE_PATH . '/tests/database/schema.sql'));
    }

    /**
     * Crée une session de test
     */
    protected function createTestSession(): array
    {
        return [
            'auth_user_id' => 1,
            'is_authenticated' => true
        ];
    }
}
