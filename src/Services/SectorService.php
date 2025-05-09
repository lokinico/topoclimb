<?php
// src/Services/SectorService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

class SectorService
{
    /**
     * @var Database
     */
    private Database $db;
    
    /**
     * Constructor
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get all sectors
     *
     * @param bool $activeOnly Return only active sectors
     * @return array
     */
    public function getAllSectors(bool $activeOnly = true): array
    {
        $sql = "SELECT s.*, r.name as region_name, b.name as book_name 
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_books b ON s.book_id = b.id";
                
        if ($activeOnly) {
            $sql .= " WHERE s.active = 1";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get sector by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getSectorById(int $id): ?array
    {
        $sql = "SELECT s.*, r.name as region_name, b.name as book_name 
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_books b ON s.book_id = b.id
                WHERE s.id = ?";
                
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get sectors by region
     *
     * @param int $regionId
     * @param bool $activeOnly
     * @return array
     */
    public function getSectorsByRegion(int $regionId, bool $activeOnly = true): array
    {
        $sql = "SELECT s.*, r.name as region_name, b.name as book_name 
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_books b ON s.book_id = b.id
                WHERE s.region_id = ?";
                
        if ($activeOnly) {
            $sql .= " AND s.active = 1";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        return $this->db->fetchAll($sql, [$regionId]);
    }
    
    /**
     * Create a new sector
     *
     * @param array $data
     * @return int|null ID of the new sector or null on failure
     */
    public function createSector(array $data): ?int
    {
        // Sanitize and validate data
        $sectorData = [
            'book_id' => (int) $data['book_id'],
            'region_id' => isset($data['region_id']) ? (int) $data['region_id'] : null,
            'name' => trim($data['name']),
            'code' => trim($data['code']),
            'description' => $data['description'] ?? null,
            'access_info' => $data['access_info'] ?? null,
            'color' => $data['color'] ?? '#FF0000',
            'access_time' => isset($data['access_time']) ? (int) $data['access_time'] : null,
            'altitude' => isset($data['altitude']) ? (int) $data['altitude'] : null,
            'approach' => $data['approach'] ?? null,
            'height' => isset($data['height']) ? (float) $data['height'] : null,
            'parking_info' => $data['parking_info'] ?? null,
            'coordinates_lat' => isset($data['coordinates_lat']) ? (float) $data['coordinates_lat'] : null,
            'coordinates_lng' => isset($data['coordinates_lng']) ? (float) $data['coordinates_lng'] : null,
            'coordinates_swiss_e' => $data['coordinates_swiss_e'] ?? null,
            'coordinates_swiss_n' => $data['coordinates_swiss_n'] ?? null,
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('climbing_sectors', $sectorData);
    }
    
    /**
     * Update an existing sector
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateSector(int $id, array $data): bool
    {
        // Sanitize and validate data
        $sectorData = [
            'book_id' => (int) $data['book_id'],
            'region_id' => isset($data['region_id']) ? (int) $data['region_id'] : null,
            'name' => trim($data['name']),
            'code' => trim($data['code']),
            'description' => $data['description'] ?? null,
            'access_info' => $data['access_info'] ?? null,
            'color' => $data['color'] ?? '#FF0000',
            'access_time' => isset($data['access_time']) ? (int) $data['access_time'] : null,
            'altitude' => isset($data['altitude']) ? (int) $data['altitude'] : null,
            'approach' => $data['approach'] ?? null,
            'height' => isset($data['height']) ? (float) $data['height'] : null,
            'parking_info' => $data['parking_info'] ?? null,
            'coordinates_lat' => isset($data['coordinates_lat']) ? (float) $data['coordinates_lat'] : null,
            'coordinates_lng' => isset($data['coordinates_lng']) ? (float) $data['coordinates_lng'] : null,
            'coordinates_swiss_e' => $data['coordinates_swiss_e'] ?? null,
            'coordinates_swiss_n' => $data['coordinates_swiss_n'] ?? null,
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
            'updated_by' => isset($data['updated_by']) ? (int) $data['updated_by'] : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('climbing_sectors', $sectorData, "id = ?", [$id]) > 0;
    }
    
    /**
     * Delete a sector
     *
     * @param int $id
     * @return bool
     */
    public function deleteSector(int $id): bool
    {
        // First check if there are any routes in this sector
        $routes = $this->db->fetchAll("SELECT id FROM climbing_routes WHERE sector_id = ?", [$id]);
        
        if (!empty($routes)) {
            // Don't delete sector if it has routes, just set active to 0
            return $this->db->update('climbing_sectors', ['active' => 0], "id = ?", [$id]) > 0;
        }
        
        return $this->db->delete('climbing_sectors', "id = ?", [$id]) > 0;
    }
    
    /**
     * Get all exposures for a sector
     *
     * @param int $sectorId
     * @return array
     */
    public function getSectorExposures(int $sectorId): array
    {
        $sql = "SELECT se.*, e.name, e.code
                FROM climbing_sector_exposures se
                JOIN climbing_exposures e ON se.exposure_id = e.id
                WHERE se.sector_id = ?
                ORDER BY se.is_primary DESC, e.sort_order ASC";
                
        return $this->db->fetchAll($sql, [$sectorId]);
    }
    
    /**
     * Get all routes for a sector
     *
     * @param int $sectorId
     * @return array
     */
    public function getSectorRoutes(int $sectorId): array
    {
        $sql = "SELECT r.*, ds.name as difficulty_system_name
                FROM climbing_routes r
                LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
                WHERE r.sector_id = ? AND r.active = 1
                ORDER BY r.number ASC";
                
        return $this->db->fetchAll($sql, [$sectorId]);
    }
    
    /**
     * Get all media for a sector
     *
     * @param int $sectorId
     * @return array
     */
    public function getSectorMedia(int $sectorId): array
    {
        $sql = "SELECT m.*
                FROM climbing_media m
                JOIN climbing_media_relationships mr ON m.id = mr.media_id
                WHERE mr.entity_type = 'sector' AND mr.entity_id = ?
                ORDER BY mr.relationship_type = 'main' DESC, mr.sort_order ASC";
                
        return $this->db->fetchAll($sql, [$sectorId]);
    }
}