<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Item d'un kit d'équipement d'escalade
 */
class EquipmentKitItem extends Model
{
    protected static string $table = 'climbing_equipment_kit_items';
    
    protected array $fillable = [
        'kit_id',
        'equipment_type_id',
        'quantity',
        'notes'
    ];
    
    protected array $casts = [
        'kit_id' => 'integer',
        'equipment_type_id' => 'integer'
    ];
    
    /**
     * Obtient le kit auquel appartient cet item
     */
    public function getKit(): ?array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM climbing_equipment_kits WHERE id = ?
        ");
        $stmt->execute([$this->kit_id]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtient le type d'équipement de cet item
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
     * Obtient tous les items d'un kit
     */
    public static function getByKit(int $kitId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT eki.*, et.name as equipment_name, et.icon as equipment_icon,
                   ec.name as category_name, ec.sort_order as category_sort_order
            FROM climbing_equipment_kit_items eki
            JOIN climbing_equipment_types et ON eki.equipment_type_id = et.id
            JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE eki.kit_id = ?
            ORDER BY ec.sort_order ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute([$kitId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les items d'un kit groupés par catégorie
     */
    public static function getByKitGroupedByCategory(int $kitId): array
    {
        $items = self::getByKit($kitId);
        
        $categories = [];
        foreach ($items as $item) {
            $categoryName = $item['category_name'];
            
            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'name' => $categoryName,
                    'sort_order' => $item['category_sort_order'],
                    'items' => []
                ];
            }
            
            $categories[$categoryName]['items'][] = $item;
        }
        
        // Trier les catégories par ordre
        uasort($categories, function($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });
        
        return array_values($categories);
    }
    
    /**
     * Crée un nouvel item de kit
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Vérifier si l'item existe déjà
        $stmt = $db->prepare("
            SELECT id FROM climbing_equipment_kit_items 
            WHERE kit_id = ? AND equipment_type_id = ?
        ");
        $stmt->execute([$data['kit_id'], $data['equipment_type_id']]);
        
        if ($stmt->fetch()) {
            throw new \Exception("Equipment item already exists in this kit");
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_equipment_kit_items (kit_id, equipment_type_id, quantity, notes)
            VALUES (?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['kit_id'],
            $data['equipment_type_id'],
            $data['quantity'],
            $data['notes'] ?? null
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Met à jour un item de kit
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_kit_items 
            SET quantity = ?, notes = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['quantity'] ?? $this->quantity,
            $data['notes'] ?? $this->notes,
            $this->id
        ]);
        
        if ($success) {
            // Recharger les données
            $this->load($this->id);
        }
        
        return $success;
    }
    
    /**
     * Supprime un item de kit
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("DELETE FROM climbing_equipment_kit_items WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Obtient les items par type d'équipement
     */
    public static function getByEquipmentType(int $equipmentTypeId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT eki.*, ek.name as kit_name, ek.is_public, ek.created_by,
                   u.username as created_by_name
            FROM climbing_equipment_kit_items eki
            JOIN climbing_equipment_kits ek ON eki.kit_id = ek.id
            LEFT JOIN users u ON ek.created_by = u.id
            WHERE eki.equipment_type_id = ?
            ORDER BY ek.name ASC
        ");
        $stmt->execute([$equipmentTypeId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Duplique les items d'un kit vers un autre
     */
    public static function duplicateFromKit(int $sourceKitId, int $targetKitId): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Supprimer les items existants du kit cible
            $stmt = $db->prepare("DELETE FROM climbing_equipment_kit_items WHERE kit_id = ?");
            $stmt->execute([$targetKitId]);
            
            // Copier les items du kit source
            $stmt = $db->prepare("
                INSERT INTO climbing_equipment_kit_items (kit_id, equipment_type_id, quantity, notes)
                SELECT ?, equipment_type_id, quantity, notes
                FROM climbing_equipment_kit_items
                WHERE kit_id = ?
            ");
            $stmt->execute([$targetKitId, $sourceKitId]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Met à jour la quantité d'un item
     */
    public function updateQuantity(string $newQuantity): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_kit_items 
            SET quantity = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$newQuantity, $this->id]);
        
        if ($success) {
            $this->quantity = $newQuantity;
        }
        
        return $success;
    }
    
    /**
     * Met à jour les notes d'un item
     */
    public function updateNotes(?string $newNotes): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_kit_items 
            SET notes = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$newNotes, $this->id]);
        
        if ($success) {
            $this->notes = $newNotes;
        }
        
        return $success;
    }
    
    /**
     * Obtient les statistiques d'utilisation d'un type d'équipement
     */
    public static function getUsageStats(int $equipmentTypeId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_kits,
                COUNT(CASE WHEN ek.is_public = 1 THEN 1 END) as public_kits,
                COUNT(CASE WHEN ek.is_public = 0 THEN 1 END) as private_kits,
                GROUP_CONCAT(DISTINCT eki.quantity) as quantities_used
            FROM climbing_equipment_kit_items eki
            JOIN climbing_equipment_kits ek ON eki.kit_id = ek.id
            WHERE eki.equipment_type_id = ?
        ");
        $stmt->execute([$equipmentTypeId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_kits' => 0,
            'public_kits' => 0,
            'private_kits' => 0,
            'quantities_used' => ''
        ];
    }
    
    /**
     * Valide la quantité d'un item
     */
    public static function validateQuantity(string $quantity): bool
    {
        // Peut être un nombre, un range (ex: "2-3"), ou une description (ex: "selon la longueur")
        if (empty($quantity)) {
            return false;
        }
        
        // Accepter les nombres
        if (is_numeric($quantity)) {
            return true;
        }
        
        // Accepter les ranges (ex: "2-3", "1-2")
        if (preg_match('/^\d+\s*-\s*\d+$/', $quantity)) {
            return true;
        }
        
        // Accepter les descriptions courtes (max 20 caractères)
        if (strlen($quantity) <= 20) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtient les quantités suggérées pour un type d'équipement
     */
    public static function getSuggestedQuantities(int $equipmentTypeId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT quantity, COUNT(*) as usage_count
            FROM climbing_equipment_kit_items
            WHERE equipment_type_id = ?
            GROUP BY quantity
            ORDER BY usage_count DESC, quantity ASC
            LIMIT 5
        ");
        $stmt->execute([$equipmentTypeId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}