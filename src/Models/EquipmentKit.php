<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Kit d'équipement d'escalade
 */
class EquipmentKit extends Model
{
    protected static string $table = 'climbing_equipment_kits';
    
    protected array $fillable = [
        'name',
        'description',
        'is_public',
        'created_by'
    ];
    
    protected array $casts = [
        'is_public' => 'boolean',
        'created_by' => 'integer'
    ];
    
    /**
     * Obtient tous les kits d'équipement
     */
    public static function getAll(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ek.*, u.username as created_by_name
            FROM climbing_equipment_kits ek
            LEFT JOIN users u ON ek.created_by = u.id
            ORDER BY ek.name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les kits publics
     */
    public static function getPublicKits(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ek.*, u.username as created_by_name
            FROM climbing_equipment_kits ek
            LEFT JOIN users u ON ek.created_by = u.id
            WHERE ek.is_public = 1
            ORDER BY ek.name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les kits d'un utilisateur
     */
    public static function getByUser(int $userId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ek.*, u.username as created_by_name
            FROM climbing_equipment_kits ek
            LEFT JOIN users u ON ek.created_by = u.id
            WHERE ek.created_by = ? OR ek.is_public = 1
            ORDER BY ek.created_by = ? DESC, ek.name ASC
        ");
        $stmt->execute([$userId, $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les items du kit
     */
    public function getItems(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT eki.*, et.name as equipment_name, et.icon as equipment_icon,
                   ec.name as category_name
            FROM climbing_equipment_kit_items eki
            JOIN climbing_equipment_types et ON eki.equipment_type_id = et.id
            JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE eki.kit_id = ?
            ORDER BY ec.sort_order ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les items du kit groupés par catégorie
     */
    public function getItemsByCategory(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT eki.*, et.name as equipment_name, et.icon as equipment_icon,
                   ec.id as category_id, ec.name as category_name, ec.sort_order as category_sort_order
            FROM climbing_equipment_kit_items eki
            JOIN climbing_equipment_types et ON eki.equipment_type_id = et.id
            JOIN climbing_equipment_categories ec ON et.category_id = ec.id
            WHERE eki.kit_id = ?
            ORDER BY ec.sort_order ASC, et.sort_order ASC, et.name ASC
        ");
        $stmt->execute([$this->id]);
        
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Grouper par catégorie
        $categories = [];
        foreach ($items as $item) {
            $categoryId = $item['category_id'];
            
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $item['category_id'],
                    'name' => $item['category_name'],
                    'sort_order' => $item['category_sort_order'],
                    'items' => []
                ];
            }
            
            $categories[$categoryId]['items'][] = $item;
        }
        
        return array_values($categories);
    }
    
    /**
     * Obtient le nombre d'items dans le kit
     */
    public function getItemsCount(): int
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM climbing_equipment_kit_items 
            WHERE kit_id = ?
        ");
        $stmt->execute([$this->id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
    
    /**
     * Obtient le créateur du kit
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
     * Crée un nouveau kit d'équipement
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO climbing_equipment_kits (name, description, is_public, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['is_public'] ?? 0,
            $data['created_by']
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Met à jour un kit d'équipement
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            UPDATE climbing_equipment_kits 
            SET name = ?, description = ?, is_public = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'] ?? $this->name,
            $data['description'] ?? $this->description,
            $data['is_public'] ?? $this->is_public,
            $this->id
        ]);
        
        if ($success) {
            // Recharger les données
            $this->load($this->id);
        }
        
        return $success;
    }
    
    /**
     * Supprime un kit d'équipement
     */
    public function delete(): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Supprimer les items du kit
            $stmt = $db->prepare("DELETE FROM climbing_equipment_kit_items WHERE kit_id = ?");
            $stmt->execute([$this->id]);
            
            // Supprimer le kit
            $stmt = $db->prepare("DELETE FROM climbing_equipment_kits WHERE id = ?");
            $stmt->execute([$this->id]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Ajoute un item au kit
     */
    public function addItem(int $equipmentTypeId, string $quantity, ?string $notes = null): bool
    {
        $db = static::getConnection();
        
        // Vérifier si l'item existe déjà
        $stmt = $db->prepare("
            SELECT id FROM climbing_equipment_kit_items 
            WHERE kit_id = ? AND equipment_type_id = ?
        ");
        $stmt->execute([$this->id, $equipmentTypeId]);
        
        if ($stmt->fetch()) {
            // Mettre à jour l'item existant
            $stmt = $db->prepare("
                UPDATE climbing_equipment_kit_items 
                SET quantity = ?, notes = ?
                WHERE kit_id = ? AND equipment_type_id = ?
            ");
            return $stmt->execute([$quantity, $notes, $this->id, $equipmentTypeId]);
        } else {
            // Créer un nouvel item
            $stmt = $db->prepare("
                INSERT INTO climbing_equipment_kit_items (kit_id, equipment_type_id, quantity, notes)
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([$this->id, $equipmentTypeId, $quantity, $notes]);
        }
    }
    
    /**
     * Supprime un item du kit
     */
    public function removeItem(int $equipmentTypeId): bool
    {
        $db = static::getConnection();
        
        $stmt = $db->prepare("
            DELETE FROM climbing_equipment_kit_items 
            WHERE kit_id = ? AND equipment_type_id = ?
        ");
        return $stmt->execute([$this->id, $equipmentTypeId]);
    }
    
    /**
     * Duplique un kit pour un utilisateur
     */
    public function duplicate(int $userId, string $newName): ?self
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Créer le nouveau kit
            $stmt = $db->prepare("
                INSERT INTO climbing_equipment_kits (name, description, is_public, created_by, created_at, updated_at)
                VALUES (?, ?, 0, ?, datetime('now'), datetime('now'))
            ");
            $stmt->execute([$newName, $this->description, $userId]);
            
            $newKitId = $db->lastInsertId();
            
            // Copier les items
            $stmt = $db->prepare("
                INSERT INTO climbing_equipment_kit_items (kit_id, equipment_type_id, quantity, notes)
                SELECT ?, equipment_type_id, quantity, notes
                FROM climbing_equipment_kit_items
                WHERE kit_id = ?
            ");
            $stmt->execute([$newKitId, $this->id]);
            
            $db->commit();
            return static::find($newKitId);
        } catch (\Exception $e) {
            $db->rollBack();
            return null;
        }
    }
    
    /**
     * Recherche les kits d'équipement
     */
    public static function search(string $query, ?int $userId = null): array
    {
        $db = static::getConnection();
        
        $sql = "
            SELECT ek.*, u.username as created_by_name
            FROM climbing_equipment_kits ek
            LEFT JOIN users u ON ek.created_by = u.id
            WHERE (ek.name LIKE ? OR ek.description LIKE ?)
        ";
        
        $params = ["%{$query}%", "%{$query}%"];
        
        if ($userId) {
            $sql .= " AND (ek.created_by = ? OR ek.is_public = 1)";
            $params[] = $userId;
        } else {
            $sql .= " AND ek.is_public = 1";
        }
        
        $sql .= " ORDER BY ek.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Vérifie si un utilisateur peut modifier ce kit
     */
    public function canEdit(int $userId): bool
    {
        return $this->created_by === $userId;
    }
    
    /**
     * Obtient les kits recommandés pour un type d'escalade
     */
    public static function getRecommendedKits(string $climbingType = 'general'): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ek.*, u.username as created_by_name
            FROM climbing_equipment_kits ek
            LEFT JOIN users u ON ek.created_by = u.id
            WHERE ek.is_public = 1
            AND (ek.name LIKE ? OR ek.description LIKE ?)
            ORDER BY ek.name ASC
        ");
        $stmt->execute(["%{$climbingType}%", "%{$climbingType}%"]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}