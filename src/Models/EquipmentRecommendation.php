<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Recommandation d'équipement pour un site, secteur ou voie
 */
class EquipmentRecommendation extends Model
{
    protected string $table = 'climbing_equipment_recommendations';
    
    protected array $fillable = [
        'entity_type',
        'entity_id',
        'equipment_type_id',
        'quantity',
        'is_mandatory',
        'description',
        'created_by'
    ];
    
    protected array $casts = [
        'entity_id' => 'integer',
        'equipment_type_id' => 'integer',
        'is_mandatory' => 'boolean',
        'created_by' => 'integer'
    ];
    
    /**
     * Types d'entités valides
     */
    public const ENTITY_TYPES = ['site', 'sector', 'route'];
    
    /**
     * Obtient les recommandations pour une entité
     */
    public static function getForEntity(string $entityType, int $entityId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT er.*, et.name as equipment_name, et.icon as equipment_icon,
                   ec.name as category_name, ec.sort_order as category_sort_order,
                   u.username as created_by_name
            FROM climbing_equipment_recommendations er
            JOIN climbing_equipment_types et ON er.equipment_type_id = et.id
            JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            LEFT JOIN users u ON er.created_by = u.id
            WHERE er.entity_type = ? AND er.entity_id = ?
            ORDER BY er.is_mandatory DESC, ec.sort_order ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute([$entityType, $entityId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les recommandations groupées par catégorie
     */
    public static function getForEntityGroupedByCategory(string $entityType, int $entityId): array
    {
        $recommendations = self::getForEntity($entityType, $entityId);
        
        $categories = [];
        foreach ($recommendations as $recommendation) {
            $categoryName = $recommendation['category_name'];
            
            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'name' => $categoryName,
                    'sort_order' => $recommendation['category_sort_order'],
                    'mandatory' => [],
                    'optional' => []
                ];
            }
            
            if ($recommendation['is_mandatory']) {
                $categories[$categoryName]['mandatory'][] = $recommendation;
            } else {
                $categories[$categoryName]['optional'][] = $recommendation;
            }
        }
        
        // Trier les catégories par ordre
        uasort($categories, function($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });
        
        return array_values($categories);
    }
    
    /**
     * Obtient les recommandations pour un type d'équipement
     */
    public static function getForEquipmentType(int $equipmentTypeId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT er.*, 
                   CASE er.entity_type
                       WHEN 'site' THEN cs.name
                       WHEN 'sector' THEN cse.name
                       WHEN 'route' THEN cr.name
                   END as entity_name,
                   CASE er.entity_type
                       WHEN 'site' THEN cr_site.name
                       WHEN 'sector' THEN cr_sector.name
                       WHEN 'route' THEN cr_route.name
                   END as region_name,
                   u.username as created_by_name
            FROM climbing_equipment_recommendations er
            LEFT JOIN climbing_sites cs ON er.entity_type = 'site' AND er.entity_id = cs.id
            LEFT JOIN climbing_sectors cse ON er.entity_type = 'sector' AND er.entity_id = cse.id
            LEFT JOIN climbing_routes cr ON er.entity_type = 'route' AND er.entity_id = cr.id
            LEFT JOIN climbing_regions cr_site ON er.entity_type = 'site' AND cs.region_id = cr_site.id
            LEFT JOIN climbing_regions cr_sector ON er.entity_type = 'sector' AND cse.region_id = cr_sector.id
            LEFT JOIN climbing_regions cr_route ON er.entity_type = 'route' AND cr.sector_id = cse.id AND cse.region_id = cr_route.id
            LEFT JOIN users u ON er.created_by = u.id
            WHERE er.equipment_type_id = ?
            ORDER BY er.created_at DESC
        ");
        $stmt->execute([$equipmentTypeId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient l'entité associée à cette recommandation
     */
    public function getEntity(): ?array
    {
        $db = static::getConnection();
        
        switch ($this->entity_type) {
            case 'site':
                $stmt = $db->prepare("SELECT * FROM climbing_sites WHERE id = ?");
                break;
            case 'sector':
                $stmt = $db->prepare("SELECT * FROM climbing_sectors WHERE id = ?");
                break;
            case 'route':
                $stmt = $db->prepare("SELECT * FROM climbing_routes WHERE id = ?");
                break;
            default:
                return null;
        }
        
        $stmt->execute([$this->entity_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtient le type d'équipement de cette recommandation
     */
    public function getEquipmentType(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT et.*, ec.name as category_name
            FROM climbing_equipment_types et
            LEFT JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE et.id = ?
        ");
        $stmt->execute([$this->equipment_type_id]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtient le créateur de cette recommandation
     */
    public function getCreator(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT id, username, nom, prenom FROM users WHERE id = ?
        ");
        $stmt->execute([$this->created_by]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Crée une nouvelle recommandation d'équipement
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Vérifier si la recommandation existe déjà
        $stmt = $db->prepare("
            SELECT id FROM climbing_equipment_recommendations 
            WHERE entity_type = ? AND entity_id = ? AND equipment_type_id = ?
        ");
        $stmt->execute([$data['entity_type'], $data['entity_id'], $data['equipment_type_id']]);
        
        if ($stmt->fetch()) {
            throw new \Exception("Equipment recommendation already exists for this entity");
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_equipment_recommendations 
            (entity_type, entity_id, equipment_type_id, quantity, is_mandatory, description, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        
        $success = $stmt->execute([
            $data['entity_type'],
            $data['entity_id'],
            $data['equipment_type_id'],
            $data['quantity'] ?? null,
            $data['is_mandatory'] ?? 0,
            $data['description'] ?? null,
            $data['created_by']
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Met à jour une recommandation d'équipement
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_recommendations 
            SET quantity = ?, is_mandatory = ?, description = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['quantity'] ?? $this->quantity,
            $data['is_mandatory'] ?? $this->is_mandatory,
            $data['description'] ?? $this->description,
            $this->id
        ]);
        
        if ($success) {
            // Recharger les données
            $this->load($this->id);
        }
        
        return $success;
    }
    
    /**
     * Supprime une recommandation d'équipement
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("DELETE FROM climbing_equipment_recommendations WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Obtient les recommandations d'équipement pour une région
     */
    public static function getForRegion(int $regionId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT er.*, et.name as equipment_name, et.icon as equipment_icon,
                   ec.name as category_name,
                   CASE er.entity_type
                       WHEN 'site' THEN cs.name
                       WHEN 'sector' THEN cse.name
                       WHEN 'route' THEN cr.name
                   END as entity_name,
                   u.username as created_by_name
            FROM climbing_equipment_recommendations er
            JOIN climbing_equipment_types et ON er.equipment_type_id = et.id
            JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            LEFT JOIN climbing_sites cs ON er.entity_type = 'site' AND er.entity_id = cs.id
            LEFT JOIN climbing_sectors cse ON er.entity_type = 'sector' AND er.entity_id = cse.id
            LEFT JOIN climbing_routes cr ON er.entity_type = 'route' AND er.entity_id = cr.id
            LEFT JOIN climbing_sectors cr_sector ON er.entity_type = 'route' AND cr.sector_id = cr_sector.id
            LEFT JOIN users u ON er.created_by = u.id
            WHERE 
                (er.entity_type = 'site' AND cs.region_id = ?) OR
                (er.entity_type = 'sector' AND cse.region_id = ?) OR
                (er.entity_type = 'route' AND cr_sector.region_id = ?)
            ORDER BY er.is_mandatory DESC, ec.sort_order ASC, et.sort_order ASC
        ");
        $stmt->execute([$regionId, $regionId, $regionId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les recommandations les plus communes pour un type d'équipement
     */
    public static function getCommonRecommendations(int $equipmentTypeId, int $limit = 10): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT quantity, is_mandatory, description, COUNT(*) as usage_count
            FROM climbing_equipment_recommendations
            WHERE equipment_type_id = ?
            GROUP BY quantity, is_mandatory, description
            ORDER BY usage_count DESC, is_mandatory DESC
            LIMIT ?
        ");
        $stmt->execute([$equipmentTypeId, $limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Duplique les recommandations d'une entité vers une autre
     */
    public static function duplicateRecommendations(
        string $fromEntityType, 
        int $fromEntityId,
        string $toEntityType,
        int $toEntityId,
        int $userId
    ): bool {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Obtenir les recommandations source
            $stmt = $db->prepare("
                SELECT equipment_type_id, quantity, is_mandatory, description
                FROM climbing_equipment_recommendations
                WHERE entity_type = ? AND entity_id = ?
            ");
            $stmt->execute([$fromEntityType, $fromEntityId]);
            $recommendations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Créer les nouvelles recommandations
            foreach ($recommendations as $recommendation) {
                $stmt = $db->prepare("
                    INSERT OR IGNORE INTO climbing_equipment_recommendations 
                    (entity_type, entity_id, equipment_type_id, quantity, is_mandatory, description, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                $stmt->execute([
                    $toEntityType,
                    $toEntityId,
                    $recommendation['equipment_type_id'],
                    $recommendation['quantity'],
                    $recommendation['is_mandatory'],
                    $recommendation['description'],
                    $userId
                ]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Valide le type d'entité
     */
    public static function validateEntityType(string $entityType): bool
    {
        return in_array($entityType, self::ENTITY_TYPES);
    }
    
    /**
     * Obtient les statistiques de recommandations
     */
    public static function getStats(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT 
                entity_type,
                COUNT(*) as total_recommendations,
                COUNT(CASE WHEN is_mandatory = 1 THEN 1 END) as mandatory_recommendations,
                COUNT(CASE WHEN is_mandatory = 0 THEN 1 END) as optional_recommendations
            FROM climbing_equipment_recommendations
            GROUP BY entity_type
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}