<?php
// fallback_sector_service.php - Version résistante aux erreurs colonnes
namespace TopoclimbCH\Services;

class SectorServiceFallback 
{
    private $db;
    
    public function __construct($db) 
    {
        $this->db = $db;
    }
    
    /**
     * Version sécurisée de getPaginatedSectors qui s'adapte aux colonnes disponibles
     */
    public function getPaginatedSectorsSafe($filter = null) 
    {
        try {
            // Tester d'abord quelles colonnes existent
            $columns = $this->getAvailableColumns();
            
            // Construire la requête selon les colonnes disponibles
            $selectFields = $this->buildSelectFields($columns);
            
            $query = "SELECT $selectFields 
                      FROM climbing_sectors s 
                      LEFT JOIN climbing_regions r ON s.region_id = r.id 
                      WHERE s.active = 1 
                      ORDER BY s.name ASC";
            
            error_log("SectorServiceFallback: Using query - $query");
            
            return $this->db->fetchAll($query);
            
        } catch (Exception $e) {
            error_log("SectorServiceFallback Error: " . $e->getMessage());
            
            // Fallback ultra-minimal si tout échoue
            return $this->getMinimalSectors();
        }
    }
    
    /**
     * Détecte quelles colonnes sont disponibles dans climbing_sectors
     */
    private function getAvailableColumns() 
    {
        $columns = [];
        
        try {
            $result = $this->db->fetchAll("DESCRIBE climbing_sectors");
            
            foreach ($result as $column) {
                $columns[] = $column['Field'];
            }
        } catch (Exception $e) {
            error_log("Error getting columns: " . $e->getMessage());
            // Colonnes minimales garanties
            $columns = ['id', 'name', 'active'];
        }
        
        return $columns;
    }
    
    /**
     * Construit la liste des champs SELECT selon colonnes disponibles
     */
    private function buildSelectFields($availableColumns) 
    {
        $fields = [];
        
        // Champs essentiels (toujours présents)
        $fields[] = 's.id';
        $fields[] = 's.name';
        
        // Champs optionnels selon disponibilité
        if (in_array('code', $availableColumns)) {
            $fields[] = 's.code';
        } else {
            $fields[] = 'CONCAT("SEC", LPAD(s.id, 3, "0")) as code'; // Code généré
        }
        
        if (in_array('description', $availableColumns)) {
            $fields[] = 's.description';
        }
        
        if (in_array('altitude', $availableColumns)) {
            $fields[] = 's.altitude';
        }
        
        if (in_array('coordinates_lat', $availableColumns)) {
            $fields[] = 's.coordinates_lat';
        }
        
        if (in_array('coordinates_lng', $availableColumns)) {
            $fields[] = 's.coordinates_lng';
        }
        
        if (in_array('region_id', $availableColumns)) {
            $fields[] = 's.region_id';
            $fields[] = 'r.name as region_name';
        }
        
        // Sous-requête pour compter les routes (sécurisée)
        $fields[] = '(SELECT COUNT(*) FROM climbing_routes WHERE sector_id = s.id AND active = 1) as routes_count';
        
        return implode(', ', $fields);
    }
    
    /**
     * Version ultra-minimaliste en cas d'échec total
     */
    private function getMinimalSectors() 
    {
        try {
            return $this->db->fetchAll("
                SELECT 
                    id, 
                    name,
                    CONCAT('SEC', LPAD(id, 3, '0')) as code,
                    '' as description,
                    0 as routes_count
                FROM climbing_sectors 
                WHERE active = 1 
                ORDER BY name ASC 
                LIMIT 50
            ");
        } catch (Exception $e) {
            error_log("Even minimal query failed: " . $e->getMessage());
            
            // Retourner des données factices pour éviter le crash total
            return [[
                'id' => 0,
                'name' => 'Aucun secteur disponible',
                'code' => 'NONE',
                'description' => 'Problème technique - contactez l\'administrateur',
                'routes_count' => 0
            ]];
        }
    }
}