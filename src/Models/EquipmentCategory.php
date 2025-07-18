<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Catégorie d'équipement d'escalade
 */
class EquipmentCategory extends Model
{
    protected string $table = 'climbing_equipment_categories';
    
    protected array $fillable = [
        'name',
        'description',
        'sort_order'
    ];
    
    protected array $casts = [
        'sort_order' => 'integer'
    ];
    
    /**
     * Obtient toutes les catégories d'équipement triées par ordre
     */
    public static function getAllSorted(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM climbing_equipment_categories 
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les types d'équipement dans cette catégorie
     */
    public function getEquipmentTypes(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM climbing_equipment_types 
            WHERE category_id = ?
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient le nombre de types d'équipement dans cette catégorie
     */
    public function getEquipmentTypesCount(): int
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM climbing_equipment_types 
            WHERE category_id = ?
        ");
        $stmt->execute([$this->id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
    
    /**
     * Crée une nouvelle catégorie d'équipement
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Obtenir le prochain ordre de tri
        if (!isset($data['sort_order'])) {
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM climbing_equipment_categories");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $data['sort_order'] = ($result['max_order'] ?? 0) + 1;
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_equipment_categories (name, description, sort_order, created_at)
            VALUES (?, ?, ?, datetime('now'))
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['sort_order']
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Met à jour une catégorie d'équipement
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_categories 
            SET name = ?, description = ?, sort_order = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'] ?? $this->name,
            $data['description'] ?? $this->description,
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
     * Supprime une catégorie d'équipement
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        
        // Vérifier s'il y a des types d'équipement dans cette catégorie
        $typesCount = $this->getEquipmentTypesCount();
        if ($typesCount > 0) {
            throw new \Exception("Cannot delete category with equipment types");
        }
        
        $stmt = $db->prepare("DELETE FROM climbing_equipment_categories WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Réorganise l'ordre des catégories
     */
    public static function reorderCategories(array $categoryIds): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            foreach ($categoryIds as $index => $categoryId) {
                $stmt = $db->prepare("
                    UPDATE climbing_equipment_categories 
                    SET sort_order = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$index + 1, $categoryId]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Obtient les catégories avec leurs types d'équipement
     */
    public static function getCategoriesWithTypes(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT 
                c.*,
                et.id as equipment_type_id,
                et.name as equipment_type_name,
                et.description as equipment_type_description,
                et.icon as equipment_type_icon,
                et.sort_order as equipment_type_sort_order
            FROM climbing_equipment_categories c
            LEFT JOIN climbing_equipment_types et ON c.id = et.category_id
            ORDER BY c.sort_order ASC, c.name ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute();
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Grouper par catégorie
        $categories = [];
        foreach ($results as $row) {
            $categoryId = $row['id'];
            
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'sort_order' => $row['sort_order'],
                    'created_at' => $row['created_at'],
                    'equipment_types' => []
                ];
            }
            
            if ($row['equipment_type_id']) {
                $categories[$categoryId]['equipment_types'][] = [
                    'id' => $row['equipment_type_id'],
                    'name' => $row['equipment_type_name'],
                    'description' => $row['equipment_type_description'],
                    'icon' => $row['equipment_type_icon'],
                    'sort_order' => $row['equipment_type_sort_order']
                ];
            }
        }
        
        return array_values($categories);
    }
}