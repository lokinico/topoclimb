<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use PDO;

class ChecklistItem extends Model
{
    protected static string $table = 'climbing_checklist_items';
    
    public int $id;
    public int $template_id;
    public string $name;
    public ?string $description;
    public ?string $category;
    public int $sort_order;
    public bool $is_mandatory;
    public ?string $icon;
    public ?int $equipment_type_id;
    public string $created_at;
    
    // Relations
    public ?array $equipment_type = null;
    public ?array $template = null;
    
    /**
     * Obtenir tous les items d'un template
     */
    public static function getByTemplate(int $templateId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ci.*, et.name as equipment_type_name, et.icon as equipment_icon
            FROM climbing_checklist_items ci
            LEFT JOIN climbing_equipment_types et ON ci.equipment_type_id = et.id
            WHERE ci.template_id = ?
            ORDER BY ci.sort_order ASC, ci.name ASC
        ");
        $stmt->execute([$templateId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les items groupés par catégorie pour un template
     */
    public static function getByTemplateGrouped(int $templateId): array
    {
        $items = self::getByTemplate($templateId);
        $grouped = [];
        
        foreach ($items as $item) {
            $category = $item['category'] ?: 'Autres';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $item;
        }
        
        return $grouped;
    }
    
    /**
     * Créer un nouvel item
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO climbing_checklist_items (
                template_id, name, description, category, sort_order, is_mandatory, icon, equipment_type_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['template_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['category'] ?? null,
            $data['sort_order'] ?? 0,
            $data['is_mandatory'] ?? 0,
            $data['icon'] ?? null,
            $data['equipment_type_id'] ?? null
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Mettre à jour un item
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_checklist_items 
            SET name = ?, description = ?, category = ?, sort_order = ?, is_mandatory = ?, icon = ?, equipment_type_id = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['category'],
            $data['sort_order'] ?? $this->sort_order,
            $data['is_mandatory'] ?? $this->is_mandatory,
            $data['icon'],
            $data['equipment_type_id'],
            $this->id
        ]);
        
        if ($success) {
            $this->name = $data['name'];
            $this->description = $data['description'];
            $this->category = $data['category'];
            $this->sort_order = $data['sort_order'] ?? $this->sort_order;
            $this->is_mandatory = $data['is_mandatory'] ?? $this->is_mandatory;
            $this->icon = $data['icon'];
            $this->equipment_type_id = $data['equipment_type_id'];
        }
        
        return $success;
    }
    
    /**
     * Réorganiser l'ordre des items d'un template
     */
    public static function reorderItems(int $templateId, array $itemIds): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE climbing_checklist_items 
                SET sort_order = ? 
                WHERE id = ? AND template_id = ?
            ");
            
            foreach ($itemIds as $order => $itemId) {
                $stmt->execute([$order, $itemId, $templateId]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Supprimer un item
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("DELETE FROM climbing_checklist_items WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Dupliquer un item pour un nouveau template
     */
    public function duplicateForTemplate(int $newTemplateId): ?self
    {
        return self::create([
            'template_id' => $newTemplateId,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_mandatory' => $this->is_mandatory,
            'icon' => $this->icon,
            'equipment_type_id' => $this->equipment_type_id
        ]);
    }
    
    /**
     * Obtenir le type d'équipement associé
     */
    public function getEquipmentType(): ?array
    {
        if (!$this->equipment_type_id) {
            return null;
        }
        
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT et.*, ec.name as category_name
            FROM climbing_equipment_types et
            LEFT JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE et.id = ?
        ");
        $stmt->execute([$this->equipment_type_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtenir le template parent
     */
    public function getTemplate(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE ct.id = ?
        ");
        $stmt->execute([$this->template_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Rechercher des items par nom
     */
    public static function search(string $query, ?int $templateId = null): array
    {
        $db = static::getConnection();
        
        $sql = "
            SELECT ci.*, et.name as equipment_type_name, et.icon as equipment_icon,
                   ct.name as template_name
            FROM climbing_checklist_items ci
            LEFT JOIN climbing_equipment_types et ON ci.equipment_type_id = et.id
            LEFT JOIN climbing_checklist_templates ct ON ci.template_id = ct.id
            WHERE (ci.name LIKE ? OR ci.description LIKE ?)
        ";
        
        $params = ["%{$query}%", "%{$query}%"];
        
        if ($templateId) {
            $sql .= " AND ci.template_id = ?";
            $params[] = $templateId;
        }
        
        $sql .= " ORDER BY ci.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les catégories d'items les plus utilisées
     */
    public static function getPopularCategories(): array
    {
        $db = static::getConnection();
        $stmt = $db->query("
            SELECT category, COUNT(*) as count
            FROM climbing_checklist_items
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category
            ORDER BY count DESC
            LIMIT 10
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}