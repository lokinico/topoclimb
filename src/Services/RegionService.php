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

    public function getAllRegions(): array
    {
        return Region::where('active', 1)->all();
    }

    public function getRegionsByCountry(int $countryId): array
    {
        return Region::where('country_id', $countryId)
            ->where('active', 1)
            ->all();
    }

    public function getRegion(int $id): ?Region
    {
        return Region::find($id);
    }

    public function getRegionWithRelations(int $id): ?Region
    {
        $region = $this->getRegion($id);
        if ($region) {
            // Load relationships
            $region->country();
        }
        return $region;
    }

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

    public function createRegion(array $data): Region
    {
        $region = new Region();
        $region->fill($data);
        $region->save();
        return $region;
    }

    public function updateRegion(Region $region, array $data): Region
    {
        $region->fill($data);
        $region->save();
        return $region;
    }

    public function deleteRegion(Region $region): bool
    {
        return $region->delete();
    }
}
