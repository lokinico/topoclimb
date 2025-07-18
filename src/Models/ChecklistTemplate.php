<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use PDO;

class ChecklistTemplate extends Model
{
    protected static string $table = 'climbing_checklist_templates';
    
    public int $id;
    public string $name;
    public ?string $description;
    public string $category;
    public string $climbing_type;
    public bool $is_public;
    public bool $is_featured;
    public int $copy_count;
    public int $created_by;
    public string $created_at;
    public string $updated_at;
    
    // Relations
    public array $items = [];
    public ?array $creator = null;
    
    /**
     * Validation des catégories autorisées
     */
    public static function getValidCategories(): array
    {
        return ['equipment', 'safety', 'preparation', 'other'];
    }
    
    /**
     * Validation des types d'escalade autorisés
     */
    public static function getValidClimbingTypes(): array
    {
        return ['sport', 'trad', 'boulder', 'multipitch', 'alpine', 'indoor', 'general'];
    }
    
    /**
     * Obtenir tous les templates triés par popularité
     */
    public static function getAllSorted(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            ORDER BY ct.is_featured DESC, ct.copy_count DESC, ct.name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les templates publics
     */
    public static function getPublicTemplates(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE ct.is_public = 1
            ORDER BY ct.is_featured DESC, ct.copy_count DESC, ct.name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les templates par utilisateur
     */
    public static function getByUser(int $userId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE ct.created_by = ?
            ORDER BY ct.created_at DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les templates par catégorie
     */
    public static function getByCategory(string $category): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE ct.category = ? AND ct.is_public = 1
            ORDER BY ct.copy_count DESC, ct.name ASC
        ");
        $stmt->execute([$category]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les templates par type d'escalade
     */
    public static function getByClimbingType(string $climbingType): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE ct.climbing_type = ? AND ct.is_public = 1
            ORDER BY ct.copy_count DESC, ct.name ASC
        ");
        $stmt->execute([$climbingType]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Rechercher des templates
     */
    public static function search(string $query): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, u.nom as creator_name
            FROM climbing_checklist_templates ct
            LEFT JOIN users u ON ct.created_by = u.id
            WHERE (ct.name LIKE ? OR ct.description LIKE ?)
            AND ct.is_public = 1
            ORDER BY ct.copy_count DESC, ct.name ASC
        ");
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Créer un nouveau template
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Validation
        if (!in_array($data['category'], self::getValidCategories())) {
            throw new \InvalidArgumentException('Catégorie invalide');
        }
        
        if (!in_array($data['climbing_type'], self::getValidClimbingTypes())) {
            throw new \InvalidArgumentException('Type d\'escalade invalide');
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_checklist_templates (
                name, description, category, climbing_type, is_public, is_featured, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category'],
            $data['climbing_type'],
            $data['is_public'] ?? 0,
            $data['is_featured'] ?? 0,
            $data['created_by']
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Obtenir les items du template avec détails
     */
    public function getItems(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT ci.*, et.name as equipment_type_name, et.icon as equipment_icon
            FROM climbing_checklist_items ci
            LEFT JOIN climbing_equipment_types et ON ci.equipment_type_id = et.id
            WHERE ci.template_id = ?
            ORDER BY ci.sort_order ASC, ci.name ASC
        ");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les items groupés par catégorie
     */
    public function getItemsByCategory(): array
    {
        $items = $this->getItems();
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
     * Ajouter un item au template
     */
    public function addItem(array $itemData): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            INSERT INTO climbing_checklist_items (
                template_id, name, description, category, sort_order, is_mandatory, icon, equipment_type_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $this->id,
            $itemData['name'],
            $itemData['description'] ?? null,
            $itemData['category'] ?? null,
            $itemData['sort_order'] ?? 0,
            $itemData['is_mandatory'] ?? 0,
            $itemData['icon'] ?? null,
            $itemData['equipment_type_id'] ?? null
        ]);
    }
    
    /**
     * Copier le template (incrémenter copy_count)
     */
    public function incrementCopyCount(): bool
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            UPDATE climbing_checklist_templates 
            SET copy_count = copy_count + 1
            WHERE id = ?
        ");
        
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Vérifier si l'utilisateur peut modifier ce template
     */
    public function canEdit(int $userId): bool
    {
        return $this->created_by === $userId;
    }
    
    /**
     * Mettre à jour le template
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        // Validation
        if (isset($data['category']) && !in_array($data['category'], self::getValidCategories())) {
            throw new \InvalidArgumentException('Catégorie invalide');
        }
        
        if (isset($data['climbing_type']) && !in_array($data['climbing_type'], self::getValidClimbingTypes())) {
            throw new \InvalidArgumentException('Type d\'escalade invalide');
        }
        
        $stmt = $db->prepare("
            UPDATE climbing_checklist_templates 
            SET name = ?, description = ?, category = ?, climbing_type = ?, is_public = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['category'],
            $data['climbing_type'],
            $data['is_public'] ?? $this->is_public,
            $this->id
        ]);
        
        if ($success) {
            // Mettre à jour les propriétés de l'objet
            $this->name = $data['name'];
            $this->description = $data['description'];
            $this->category = $data['category'];
            $this->climbing_type = $data['climbing_type'];
            $this->is_public = $data['is_public'] ?? $this->is_public;
        }
        
        return $success;
    }
    
    /**
     * Obtenir les statistiques des templates
     */
    public static function getStats(): array
    {
        $db = static::getConnection();
        
        // Statistiques générales
        $stmt = $db->query("SELECT COUNT(*) as total FROM climbing_checklist_templates");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as public FROM climbing_checklist_templates WHERE is_public = 1");
        $public = $stmt->fetch(PDO::FETCH_ASSOC)['public'];
        
        // Statistiques par catégorie
        $stmt = $db->query("
            SELECT category, COUNT(*) as count
            FROM climbing_checklist_templates
            GROUP BY category
            ORDER BY count DESC
        ");
        $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques par type d'escalade
        $stmt = $db->query("
            SELECT climbing_type, COUNT(*) as count
            FROM climbing_checklist_templates
            GROUP BY climbing_type
            ORDER BY count DESC
        ");
        $byClimbingType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'public' => $public,
            'private' => $total - $public,
            'by_category' => $byCategory,
            'by_climbing_type' => $byClimbingType
        ];
    }
}