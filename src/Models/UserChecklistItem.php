<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use PDO;

class UserChecklistItem extends Model
{
    protected static string $table = 'climbing_user_checklist_items';
    
    public int $id;
    public int $checklist_id;
    public string $name;
    public ?string $description;
    public ?string $category;
    public int $sort_order;
    public bool $is_mandatory;
    public bool $is_checked;
    public ?string $notes;
    public ?string $checked_at;
    public ?int $original_item_id;
    public ?int $equipment_type_id;
    public string $created_at;
    public string $updated_at;
    
    // Relations
    public ?array $equipment_type = null;
    public ?array $original_item = null;
    public ?array $checklist = null;
    
    /**
     * Obtenir tous les items d'une checklist
     */
    public static function getByChecklist(int $checklistId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uci.*, et.name as equipment_type_name, et.icon as equipment_icon,
                   ci.name as original_item_name
            FROM climbing_user_checklist_items uci
            LEFT JOIN climbing_equipment_types et ON uci.equipment_type_id = et.id
            LEFT JOIN climbing_checklist_items ci ON uci.original_item_id = ci.id
            WHERE uci.checklist_id = ?
            ORDER BY uci.sort_order ASC, uci.name ASC
        ");
        $stmt->execute([$checklistId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les items groupés par catégorie pour une checklist
     */
    public static function getByChecklistGrouped(int $checklistId): array
    {
        $items = self::getByChecklist($checklistId);
        $grouped = [];
        
        foreach ($items as $item) {
            $category = $item['category'] ?: 'Autres';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'name' => $category,
                    'items' => [],
                    'total_count' => 0,
                    'checked_count' => 0,
                    'mandatory_count' => 0,
                    'checked_mandatory' => 0
                ];
            }
            
            $grouped[$category]['items'][] = $item;
            $grouped[$category]['total_count']++;
            
            if ($item['is_checked']) {
                $grouped[$category]['checked_count']++;
            }
            
            if ($item['is_mandatory']) {
                $grouped[$category]['mandatory_count']++;
                if ($item['is_checked']) {
                    $grouped[$category]['checked_mandatory']++;
                }
            }
        }
        
        // Calculer les pourcentages pour chaque catégorie
        foreach ($grouped as &$category) {
            $category['percentage'] = $category['total_count'] > 0 
                ? round(($category['checked_count'] / $category['total_count']) * 100, 1) 
                : 0;
            
            $category['mandatory_percentage'] = $category['mandatory_count'] > 0 
                ? round(($category['checked_mandatory'] / $category['mandatory_count']) * 100, 1) 
                : 100;
            
            $category['is_ready'] = $category['mandatory_count'] === $category['checked_mandatory'];
        }
        
        return $grouped;
    }
    
    /**
     * Créer un nouvel item de checklist utilisateur
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO climbing_user_checklist_items (
                checklist_id, name, description, category, sort_order, is_mandatory, 
                original_item_id, equipment_type_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['checklist_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['category'] ?? null,
            $data['sort_order'] ?? 0,
            $data['is_mandatory'] ?? 0,
            $data['original_item_id'] ?? null,
            $data['equipment_type_id'] ?? null
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Cocher/décocher un item
     */
    public function toggleCheck(?string $notes = null): bool
    {
        $db = static::getConnection();
        
        $newCheckedState = !$this->is_checked;
        $checkedAt = $newCheckedState ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("
            UPDATE climbing_user_checklist_items 
            SET is_checked = ?, checked_at = ?, notes = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $newCheckedState ? 1 : 0,
            $checkedAt,
            $notes,
            $this->id
        ]);
        
        if ($success) {
            $this->is_checked = $newCheckedState;
            $this->checked_at = $checkedAt;
            $this->notes = $notes;
        }
        
        return $success;
    }
    
    /**
     * Mettre à jour les notes d'un item
     */
    public function updateNotes(string $notes): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            UPDATE climbing_user_checklist_items 
            SET notes = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$notes, $this->id]);
        
        if ($success) {
            $this->notes = $notes;
        }
        
        return $success;
    }
    
    /**
     * Mettre à jour un item
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_user_checklist_items 
            SET name = ?, description = ?, category = ?, sort_order = ?, is_mandatory = ?, equipment_type_id = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['category'],
            $data['sort_order'] ?? $this->sort_order,
            $data['is_mandatory'] ?? $this->is_mandatory,
            $data['equipment_type_id'],
            $this->id
        ]);
        
        if ($success) {
            $this->name = $data['name'];
            $this->description = $data['description'];
            $this->category = $data['category'];
            $this->sort_order = $data['sort_order'] ?? $this->sort_order;
            $this->is_mandatory = $data['is_mandatory'] ?? $this->is_mandatory;
            $this->equipment_type_id = $data['equipment_type_id'];
        }
        
        return $success;
    }
    
    /**
     * Réorganiser l'ordre des items d'une checklist
     */
    public static function reorderItems(int $checklistId, array $itemIds): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE climbing_user_checklist_items 
                SET sort_order = ? 
                WHERE id = ? AND checklist_id = ?
            ");
            
            foreach ($itemIds as $order => $itemId) {
                $stmt->execute([$order, $itemId, $checklistId]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Ajouter un nouvel item à une checklist existante
     */
    public static function addToChecklist(int $checklistId, array $itemData): ?self
    {
        // Déterminer le prochain sort_order
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order
            FROM climbing_user_checklist_items
            WHERE checklist_id = ?
        ");
        $stmt->execute([$checklistId]);
        $nextOrder = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
        
        $itemData['checklist_id'] = $checklistId;
        $itemData['sort_order'] = $itemData['sort_order'] ?? $nextOrder;
        
        return self::create($itemData);
    }
    
    /**
     * Supprimer un item
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("DELETE FROM climbing_user_checklist_items WHERE id = ?");
        return $stmt->execute([$this->id]);
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
     * Obtenir l'item original du template
     */
    public function getOriginalItem(): ?array
    {
        if (!$this->original_item_id) {
            return null;
        }
        
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ci.*, ct.name as template_name
            FROM climbing_checklist_items ci
            LEFT JOIN climbing_checklist_templates ct ON ci.template_id = ct.id
            WHERE ci.id = ?
        ");
        $stmt->execute([$this->original_item_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtenir la checklist parente
     */
    public function getChecklist(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uc.*, u.nom as user_name
            FROM climbing_user_checklists uc
            LEFT JOIN users u ON uc.user_id = u.id
            WHERE uc.id = ?
        ");
        $stmt->execute([$this->checklist_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtenir les items obligatoires non cochés d'une checklist
     */
    public static function getMandatoryUnchecked(int $checklistId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uci.*, et.name as equipment_type_name
            FROM climbing_user_checklist_items uci
            LEFT JOIN climbing_equipment_types et ON uci.equipment_type_id = et.id
            WHERE uci.checklist_id = ? AND uci.is_mandatory = 1 AND uci.is_checked = 0
            ORDER BY uci.sort_order ASC
        ");
        $stmt->execute([$checklistId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marquer tous les items non obligatoires comme cochés
     */
    public static function checkAllOptional(int $checklistId): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            UPDATE climbing_user_checklist_items 
            SET is_checked = 1, checked_at = datetime('now'), updated_at = datetime('now')
            WHERE checklist_id = ? AND is_mandatory = 0 AND is_checked = 0
        ");
        
        return $stmt->execute([$checklistId]);
    }
    
    /**
     * Réinitialiser tous les items d'une checklist
     */
    public static function resetAll(int $checklistId): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            UPDATE climbing_user_checklist_items 
            SET is_checked = 0, checked_at = NULL, notes = NULL, updated_at = datetime('now')
            WHERE checklist_id = ?
        ");
        
        return $stmt->execute([$checklistId]);
    }
    
    /**
     * Obtenir les statistiques d'utilisation des items
     */
    public static function getUsageStats(): array
    {
        $db = static::getConnection();
        
        // Items les plus cochés
        $stmt = $db->query("
            SELECT name, COUNT(*) as usage_count,
                   SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) as checked_count
            FROM climbing_user_checklist_items
            GROUP BY name
            HAVING usage_count > 1
            ORDER BY checked_count DESC
            LIMIT 10
        ");
        $popularItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Catégories les plus utilisées
        $stmt = $db->query("
            SELECT category, COUNT(*) as usage_count
            FROM climbing_user_checklist_items
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category
            ORDER BY usage_count DESC
            LIMIT 5
        ");
        $popularCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'popular_items' => $popularItems,
            'popular_categories' => $popularCategories
        ];
    }
}