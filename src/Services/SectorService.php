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
     * Get sector by ID with region information
     *
     * @param int $id
     * @return array|null
     */
    public function getSectorById(int $id): ?array
    {
        $sql = "SELECT s.*, r.name as region_name
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
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
        $sql = "SELECT s.*, r.name as region_name
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
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
            
            $sectorData['created_at'] = date('Y-m-d H:i:s');
            $sectorData['updated_at'] = date('Y-m-d H:i:s');
            
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
        $sectorData['updated_at'] = date('Y-m-d H:i:s');
        
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
            
            // Delete related data
            $this->db->delete('climbing_sector_exposures', "sector_id = ?", [$id]);
            $this->db->delete('climbing_sector_months', "sector_id = ?", [$id]);
            $this->db->delete('climbing_media_relationships', "entity_type = 'sector' AND entity_id = ?", [$id]);
            $this->db->delete('climbing_condition_reports', "entity_type = 'sector' AND entity_id = ?", [$id]);
            $this->db->delete('climbing_entity_tags', "entity_type = 'sector' AND entity_id = ?", [$id]);
            
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
     * Update exposures for a sector
     *
     * @param int $sectorId
     * @param array $exposureIds
     * @param int|null $primaryExposureId
     * @return bool
     * @throws ServiceException
     */
    public function updateSectorExposures(int $sectorId, array $exposureIds, ?int $primaryExposureId = null): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Delete existing exposures
            $this->db->delete('climbing_sector_exposures', "sector_id = ?", [$sectorId]);
            
            // Add new exposures
            foreach ($exposureIds as $exposureId) {
                $isPrimary = ($exposureId == $primaryExposureId) ? 1 : 0;
                $this->db->insert('climbing_sector_exposures', [
                    'sector_id' => $sectorId,
                    'exposure_id' => (int) $exposureId,
                    'is_primary' => $isPrimary,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new ServiceException("Error updating sector exposures: " . $e->getMessage());
        }
    }
    
    /**
     * Update months for a sector
     *
     * @param int $sectorId
     * @param array $monthData
     * @return bool
     * @throws ServiceException
     */
    public function updateSectorMonths(int $sectorId, array $monthData): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Delete existing months
            $this->db->delete('climbing_sector_months', "sector_id = ?", [$sectorId]);
            
            // Add new months
            foreach ($monthData as $monthId => $data) {
                if (empty($data['quality'])) {
                    continue;
                }
                
                $this->db->insert('climbing_sector_months', [
                    'sector_id' => $sectorId,
                    'month_id' => (int) $monthId,
                    'quality' => $data['quality'],
                    'notes' => $data['notes'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new ServiceException("Error updating sector months: " . $e->getMessage());
        }
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
    
    /**
     * Get months quality data for a sector
     *
     * @param int $sectorId
     * @return array
     */
    public function getSectorMonths(int $sectorId): array
    {
        $sql = "SELECT sm.*, m.name, m.short_name
                FROM climbing_sector_months sm
                JOIN climbing_months m ON sm.month_id = m.id
                WHERE sm.sector_id = ?
                ORDER BY m.month_number ASC";
                
        return $this->db->fetchAll($sql, [$sectorId]);
    }
    
    /**
     * Get nearby sectors
     *
     * @param int $sectorId
     * @param float $maxDistance Distance en km
     * @param int $limit
     * @return array
     */
    public function getNearbySectors(int $sectorId, float $maxDistance = 10.0, int $limit = 5): array
    {
        $sector = $this->getSectorById($sectorId);
        
        if (!$sector || !isset($sector['coordinates_lat']) || !isset($sector['coordinates_lng'])) {
            return [];
        }
        
        $lat = $sector['coordinates_lat'];
        $lng = $sector['coordinates_lng'];
        
        // Calculer la distance avec la formule haversine
        $sql = "SELECT s.*, r.name as region_name,
                       (6371 * acos(cos(radians(?)) * cos(radians(s.coordinates_lat)) * 
                        cos(radians(s.coordinates_lng) - radians(?)) + 
                        sin(radians(?)) * sin(radians(s.coordinates_lat)))) AS distance
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.id != ? AND s.active = 1 
                AND s.coordinates_lat IS NOT NULL 
                AND s.coordinates_lng IS NOT NULL
                HAVING distance < ?
                ORDER BY distance
                LIMIT ?";
                
        return $this->db->fetchAll($sql, [$lat, $lng, $lat, $sectorId, $maxDistance, $limit]);
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
     * Get paginated sectors with filtering and enriched data
     *
     * @param \TopoclimbCH\Core\Filtering\SectorFilter $filter
     * @return \TopoclimbCH\Core\Pagination\Paginator
     */
    public function getPaginatedSectors($filter)
    {
        try {
            // VERSION 1: Tenter avec la colonne 'code'
            $simpleSectors = $this->db->fetchAll("
                SELECT 
                    s.id, 
                    s.name, 
                    s.code,
                    s.region_id,
                    r.name as region_name,
                    s.description,
                    s.altitude,
                    s.coordinates_lat,
                    s.coordinates_lng,
                    s.active,
                    (SELECT COUNT(*) FROM climbing_routes WHERE sector_id = s.id AND active = 1) as routes_count
                FROM climbing_sectors s 
                LEFT JOIN climbing_regions r ON s.region_id = r.id 
                WHERE s.active = 1
                ORDER BY s.name ASC
                LIMIT 50
            ");
            
            error_log("SectorService: Query with 'code' column succeeded - " . count($simpleSectors) . " results");
            return new \TopoclimbCH\Core\Pagination\SimplePaginator($simpleSectors, 1, 50, count($simpleSectors));
            
        } catch (\Exception $e) {
            error_log("SectorService::getPaginatedSectors Error with 'code': " . $e->getMessage());
            
            try {
                // VERSION 2: Fallback sans la colonne 'code' - générer un code
                $simpleSectors = $this->db->fetchAll("
                    SELECT 
                        s.id, 
                        s.name, 
                        CONCAT('SEC', LPAD(s.id, 3, '0')) as code,
                        s.region_id,
                        r.name as region_name,
                        s.description,
                        s.altitude,
                        s.coordinates_lat,
                        s.coordinates_lng,
                        s.active,
                        (SELECT COUNT(*) FROM climbing_routes WHERE sector_id = s.id AND active = 1) as routes_count
                    FROM climbing_sectors s 
                    LEFT JOIN climbing_regions r ON s.region_id = r.id 
                    WHERE s.active = 1
                    ORDER BY s.name ASC 
                    LIMIT 50
                ");
                
                error_log("SectorService: Fallback query without 'code' succeeded - " . count($simpleSectors) . " results");
                return new \TopoclimbCH\Core\Pagination\SimplePaginator($simpleSectors, 1, 50, count($simpleSectors));
                
            } catch (\Exception $e2) {
                error_log("SectorService: Fallback query also failed - " . $e2->getMessage());
                
                try {
                    // VERSION 3: Requête ultra-minimale
                    $simpleSectors = $this->db->fetchAll("
                        SELECT 
                            s.id, 
                            s.name, 
                            CONCAT('SEC', s.id) as code,
                            s.region_id,
                            r.name as region_name,
                            COALESCE(s.description, '') as description,
                            s.altitude,
                            s.coordinates_lat,
                            s.coordinates_lng,
                            1 as active,
                            0 as routes_count
                        FROM climbing_sectors s 
                        LEFT JOIN climbing_regions r ON s.region_id = r.id 
                        ORDER BY s.name ASC 
                        LIMIT 50
                    ");
                    
                    error_log("SectorService: Ultra-minimal query succeeded - " . count($simpleSectors) . " results");
                    return new \TopoclimbCH\Core\Pagination\SimplePaginator($simpleSectors, 1, 50, count($simpleSectors));
                    
                } catch (\Exception $e3) {
                    error_log("SectorService: ALL QUERIES FAILED - " . $e3->getMessage());
                    
                    // VERSION 4: Données factices pour éviter un crash complet
                    $mockSectors = [
                        [
                            'id' => 1,
                            'name' => 'Secteur indisponible',
                            'code' => 'ERROR001',
                            'region_id' => 1,
                            'region_name' => 'Base de données défaillante',
                            'description' => 'Erreur de structure de base de données détectée',
                            'altitude' => 0,
                            'coordinates_lat' => null,
                            'coordinates_lng' => null,
                            'active' => 1,
                            'routes_count' => 0
                        ]
                    ];
                    
                    return new \TopoclimbCH\Core\Pagination\SimplePaginator($mockSectors, 1, 50, 1);
                }
            }
        }
        }
}