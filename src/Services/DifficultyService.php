<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

/**
 * Service for managing difficulty systems and grades
 */
class DifficultyService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get all difficulty systems
     */
    public function getAllSystems(): array
    {
        return $this->db->fetchAll("SELECT * FROM difficulty_systems ORDER BY name ASC");
    }

    /**
     * Get all grades for a specific system
     */
    public function getGradesForSystem(int $systemId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM difficulty_grades WHERE system_id = ? ORDER BY difficulty_order ASC",
            [$systemId]
        );
    }

    /**
     * Get system by ID
     */
    public function getSystemById(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM difficulty_systems WHERE id = ?", [$id]);
    }

    /**
     * Create a new difficulty system
     */
    public function createSystem(array $data): int
    {
        return $this->db->insert('difficulty_systems', $data);
    }

    /**
     * Update a difficulty system
     */
    public function updateSystem(int $id, array $data): bool
    {
        return $this->db->update('difficulty_systems', $data, 'id = ?', [$id]) > 0;
    }

    /**
     * Delete a difficulty system
     */
    public function deleteSystem(int $id): bool
    {
        return $this->db->delete('difficulty_systems', 'id = ?', [$id]) > 0;
    }

    /**
     * Convert difficulty between systems
     */
    public function convertDifficulty(int $fromSystemId, int $toSystemId, string $grade): ?string
    {
        // This would need more complex logic based on conversion tables
        // For now, return null if conversion not possible
        return null;
    }
}