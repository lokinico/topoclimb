<?php
// src/Services/SectorService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Exceptions\ServiceException;
use PDOException;

class SectorService
{
    /**
     * @var Database
     */
    private Database $db;
    
    /**
     * @var array Types valides pour les champs
     */
    private array $validationRules = [
        'coordinates_lat' => ['min' => -90, 'max' => 90],
        'coordinates_lng' => ['min' => -180, 'max' => 180],
        'altitude' => ['min' => 0, 'max' => 9000],
        'height' => ['min' => 0, 'max' => 2000],
        'access_time' => ['min' => 0, 'max' => 1440] // Max 24h en minutes
    ];
    
    // Constructor conservé...
    
    /**
     * Create a new sector
     *
     * @param array $data
     * @return int|null ID of the new sector or null on failure
     * @throws ServiceException
     */
    public function createSector(array $data): ?int
    {
        // Validate data
        $this->validateSectorData($data);
        
        // Sanitize data
        $sectorData = $this->sanitizeSectorData($data);
        
        try {
            $this->db->beginTransaction();
            
            $sectorId = $this->db->insert('climbing_sectors', $sectorData);
            
            if (!$sectorId) {
                $this->db->rollBack();
                throw new ServiceException("Failed to insert sector");
            }
            
            $this->db->commit();
            return $sectorId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new ServiceException("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Update an existing sector
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ServiceException
     */
    public function updateSector(int $id, array $data): bool
    {
        // Check if sector exists
        $sector = $this->getSectorById($id);
        if (!$sector) {
            throw new ServiceException("Sector not found");
        }
        
        // Validate data
        $this->validateSectorData($data);
        
        // Sanitize data
        $sectorData = $this->sanitizeSectorData($data);
        
        try {
            $this->db->beginTransaction();
            
            $result = $this->db->update('climbing_sectors', $sectorData, "id = ?", [$id]) > 0;
            
            if (!$result) {
                $this->db->rollBack();
                throw new ServiceException("Failed to update sector");
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new ServiceException("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Delete a sector
     *
     * @param int $id
     * @return bool
     * @throws ServiceException
     */
    public function deleteSector(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Check if there are related items
            $routes = $this->getSectorRoutes($id);
            if (!empty($routes)) {
                // Set sector inactive instead of deleting
                $result = $this->db->update('climbing_sectors', ['active' => 0], "id = ?", [$id]) > 0;
                $this->db->commit();
                return $result;
            }
            
            // Check for other relations
            $mediaCount = $this->db->fetchOne(
                "SELECT COUNT(*) FROM climbing_media_relationships WHERE entity_type = 'sector' AND entity_id = ?", 
                [$id]
            );
            
            // Delete related data
            $this->db->delete('climbing_sector_exposures', "sector_id = ?", [$id]);
            $this->db->delete('climbing_sector_months', "sector_id = ?", [$id]);
            
            if ($mediaCount > 0) {
                $this->db->delete('climbing_media_relationships', "entity_type = 'sector' AND entity_id = ?", [$id]);
            }
            
            // Delete the sector
            $result = $this->db->delete('climbing_sectors', "id = ?", [$id]) > 0;
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new ServiceException("Database error when deleting sector: " . $e->getMessage());
        }
    }
    
    /**
     * Validate sector data
     *
     * @param array $data
     * @return bool
     * @throws ServiceException
     */
    private function validateSectorData(array $data): bool
    {
        // Required fields
        $requiredFields = ['name', 'code', 'book_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new ServiceException("Field {$field} is required");
            }
        }
        
        // Numeric ranges
        foreach ($this->validationRules as $field => $rules) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                $value = is_numeric($data[$field]) ? (float)$data[$field] : null;
                
                if ($value === null) {
                    throw new ServiceException("Field {$field} must be numeric");
                }
                
                if (isset($rules['min']) && $value < $rules['min']) {
                    throw new ServiceException("Field {$field} must be greater than or equal to {$rules['min']}");
                }
                
                if (isset($rules['max']) && $value > $rules['max']) {
                    throw new ServiceException("Field {$field} must be less than or equal to {$rules['max']}");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize sector data
     *
     * @param array $data
     * @return array
     */
    private function sanitizeSectorData(array $data): array
    {
        $sanitized = [
            'book_id' => (int) $data['book_id'],
            'region_id' => isset($data['region_id']) && $data['region_id'] !== '' ? (int) $data['region_id'] : null,
            'name' => trim($data['name']),
            'code' => trim($data['code']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'access_info' => isset($data['access_info']) ? trim($data['access_info']) : null,
            'color' => isset($data['color']) ? trim($data['color']) : '#FF0000',
            'access_time' => isset($data['access_time']) && $data['access_time'] !== '' ? (int) $data['access_time'] : null,
            'altitude' => isset($data['altitude']) && $data['altitude'] !== '' ? (int) $data['altitude'] : null,
            'approach' => isset($data['approach']) ? trim($data['approach']) : null,
            'height' => isset($data['height']) && $data['height'] !== '' ? (float) $data['height'] : null,
            'parking_info' => isset($data['parking_info']) ? trim($data['parking_info']) : null,
            'coordinates_lat' => isset($data['coordinates_lat']) && $data['coordinates_lat'] !== '' ? (float) $data['coordinates_lat'] : null,
            'coordinates_lng' => isset($data['coordinates_lng']) && $data['coordinates_lng'] !== '' ? (float) $data['coordinates_lng'] : null,
            'coordinates_swiss_e' => isset($data['coordinates_swiss_e']) ? trim($data['coordinates_swiss_e']) : null,
            'coordinates_swiss_n' => isset($data['coordinates_swiss_n']) ? trim($data['coordinates_swiss_n']) : null,
            'active' => isset($data['active']) ? (int) $data['active'] : 1
        ];
        
        if (isset($data['created_by'])) {
            $sanitized['created_by'] = (int) $data['created_by'];
        }
        
        if (isset($data['updated_by'])) {
            $sanitized['updated_by'] = (int) $data['updated_by'];
        }
        
        return $sanitized;
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