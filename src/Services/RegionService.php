<?php
// src/Services/RegionService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;

class RegionService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère toutes les régions actives
     */
    public function getAllRegions(): array
    {
        // Correction: Region::where() retourne déjà un tableau, pas besoin de all()
        return Region::where(['active' => 1]);
    }

    /**
     * Récupère les régions par pays
     */
    public function getRegionsByCountry(int $countryId): array
    {
        // Correction: utilisation de la syntaxe tableau pour les conditions
        return Region::where([
            'country_id' => $countryId,
            'active' => 1
        ]);
    }

    /**
     * Récupère une région par son ID
     */
    public function getRegion(int $id): ?Region
    {
        return Region::find($id);
    }

    /**
     * Récupère une région avec ses relations
     */
    public function getRegionWithRelations(int $id): ?Region
    {
        $region = $this->getRegion($id);
        if ($region) {
            // Load relationships
            $region->country();
        }
        return $region;
    }

    /**
     * Récupère les secteurs d'une région
     */
    public function getRegionSectors(int $regionId): array
    {
        return $this->db->query(
            "SELECT s.* 
             FROM climbing_sectors s 
             WHERE s.region_id = ? AND s.active = 1
             ORDER BY s.name",
            [$regionId]
        );
    }

    /**
     * Obtient des statistiques pour une région
     */
    public function getRegionStatistics(int $regionId): array
    {
        $stats = [
            'sectors_count' => 0,
            'routes_count' => 0,
            'avg_difficulty' => null
        ];

        // Get sectors count
        $sectors = $this->db->query(
            "SELECT COUNT(*) as count 
             FROM climbing_sectors 
             WHERE region_id = ? AND active = 1",
            [$regionId]
        );
        if (!empty($sectors)) {
            $stats['sectors_count'] = $sectors[0]['count'] ?? 0;
        }

        // Get routes count and average difficulty
        $routes = $this->db->query(
            "SELECT COUNT(*) as count, AVG(numerical_value) as avg_diff
             FROM climbing_routes r
             JOIN climbing_sectors s ON r.sector_id = s.id
             LEFT JOIN climbing_difficulty_grades g ON r.difficulty = g.value
             WHERE s.region_id = ? AND r.active = 1",
            [$regionId]
        );
        if (!empty($routes)) {
            $stats['routes_count'] = $routes[0]['count'] ?? 0;
            $stats['avg_difficulty'] = $routes[0]['avg_diff'] ?? null;
        }

        return $stats;
    }

    /**
     * Crée une nouvelle région
     */
    public function createRegion(array $data): Region
    {
        $region = new Region();
        $region->fill($data);
        $region->save();
        return $region;
    }

    /**
     * Met à jour une région existante
     */
    public function updateRegion(Region $region, array $data): Region
    {
        $region->fill($data);
        $region->save();
        return $region;
    }

    /**
     * Supprime une région
     */
    public function deleteRegion(Region $region): bool
    {
        return $region->delete();
    }
}
