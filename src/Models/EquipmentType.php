<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Type d'équipement d'escalade
 */
class EquipmentType extends Model
{
    protected static string $table = 'climbing_equipment_types';
    
    protected array $fillable = [
        'category_id',
        'name',
        'description',
        'icon',
        'sort_order'
    ];
    
    protected array $casts = [
        'category_id' => 'integer',
        'sort_order' => 'integer'
    ];
    
    /**
     * Obtient tous les types d'équipement triés par catégorie et ordre
     */
    public static function getAllSorted(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT et.*, ec.name as category_name
            FROM climbing_equipment_types et
            LEFT JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            ORDER BY ec.sort_order ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les types d'équipement par catégorie
     */
    public static function getByCategory(int $categoryId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM climbing_equipment_types 
            WHERE category_id = ?
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$categoryId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient la catégorie de ce type d'équipement
     */
    public function getCategory(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM climbing_equipment_categories 
            WHERE id = ?
        ");
        $stmt->execute([$this->category_id]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtient les recommandations pour ce type d'équipement
     */
    public function getRecommendations(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT er.*, 
                   CASE er.entity_type
                       WHEN 'site' THEN cs.name
                       WHEN 'sector' THEN cse.name
                       WHEN 'route' THEN cr.name
                   END as entity_name
            FROM climbing_equipment_recommendations er
            LEFT JOIN climbing_sites cs ON er.entity_type = 'site' AND er.entity_id = cs.id
            LEFT JOIN climbing_sectors cse ON er.entity_type = 'sector' AND er.entity_id = cse.id
            LEFT JOIN climbing_routes cr ON er.entity_type = 'route' AND er.entity_id = cr.id
            WHERE er.equipment_type_id = ?
            ORDER BY er.created_at DESC
        ");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les kits d'équipement qui contiennent ce type
     */
    public function getKits(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ek.*, eki.quantity, eki.notes
            FROM climbing_equipment_kits ek
            JOIN climbing_equipment_kit_items eki ON ek.id = eki.kit_id
            WHERE eki.equipment_type_id = ?
            ORDER BY ek.name ASC
        ");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Crée un nouveau type d'équipement
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Obtenir le prochain ordre de tri dans la catégorie
        if (!isset($data['sort_order'])) {
            $stmt = $db->prepare("
                SELECT MAX(sort_order) as max_order 
                FROM climbing_equipment_types 
                WHERE category_id = ?
            ");
            $stmt->execute([$data['category_id']]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $data['sort_order'] = ($result['max_order'] ?? 0) + 1;
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_equipment_types (category_id, name, description, icon, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        
        $success = $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['sort_order']
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Met à jour un type d'équipement
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_types 
            SET category_id = ?, name = ?, description = ?, icon = ?, sort_order = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['category_id'] ?? $this->category_id,
            $data['name'] ?? $this->name,
            $data['description'] ?? $this->description,
            $data['icon'] ?? $this->icon,
            $data['sort_order'] ?? $this->sort_order,
            $this->id
        ]);
        
        if ($success) {
            // Recharger les données
            $this->load($this->id);
        }
        
        return $success;
    }
    
    /**
     * Supprime un type d'équipement
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        
        // Vérifier s'il y a des recommandations ou des items de kit
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM climbing_equipment_recommendations 
            WHERE equipment_type_id = ?
        ");
        $stmt->execute([$this->id]);
        $recommendationsCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM climbing_equipment_kit_items 
            WHERE equipment_type_id = ?
        ");
        $stmt->execute([$this->id]);
        $kitItemsCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
        
        if ($recommendationsCount > 0 || $kitItemsCount > 0) {
            throw new \Exception("Cannot delete equipment type with existing recommendations or kit items");
        }
        
        $stmt = $db->prepare("DELETE FROM climbing_equipment_types WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Recherche les types d'équipement
     */
    public static function search(string $query): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT et.*, ec.name as category_name
            FROM climbing_equipment_types et
            LEFT JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE et.name LIKE ? OR et.description LIKE ?
            ORDER BY et.name ASC
        ");
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Réorganise l'ordre des types d'équipement dans une catégorie
     */
    public static function reorderInCategory(int $categoryId, array $typeIds): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            foreach ($typeIds as $index => $typeId) {
                $stmt = $db->prepare("
                    UPDATE climbing_equipment_types 
                    SET sort_order = ? 
                    WHERE id = ? AND category_id = ?
                ");
                $stmt->execute([$index + 1, $typeId, $categoryId]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Obtient les types d'équipement pour une sélection (formulaire)
     */
    public static function getForSelect(?int $categoryId = null): array
    {
        $db = static::getConnection();
        
        $sql = "
            SELECT et.id, et.name, ec.name as category_name
            FROM climbing_equipment_types et
            LEFT JOIN climbing_equipment_categories ec ON et.category_id = ec.id
        ";
        
        $params = [];
        if ($categoryId) {
            $sql .= " WHERE et.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY ec.sort_order ASC, et.sort_order ASC, et.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}