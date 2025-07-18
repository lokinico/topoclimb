<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use PDO;

class UserChecklist extends Model
{
    protected static string $table = 'climbing_user_checklists';
    
    public int $id;
    public string $name;
    public ?string $description;
    public int $user_id;
    public ?int $based_on_template_id;
    public ?int $event_id;
    public ?string $entity_type;
    public ?int $entity_id;
    public bool $is_completed;
    public string $created_at;
    public string $updated_at;
    
    // Relations
    public array $items = [];
    public ?array $template = null;
    public ?array $user = null;
    public ?array $event = null;
    
    /**
     * Validation des types d'entités autorisées
     */
    public static function getValidEntityTypes(): array
    {
        return ['site', 'sector', 'route'];
    }
    
    /**
     * Obtenir les checklists d'un utilisateur
     */
    public static function getByUser(int $userId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uc.*, ct.name as template_name, e.name as event_name,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id) as total_items,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id AND is_checked = 1) as checked_items
            FROM climbing_user_checklists uc
            LEFT JOIN climbing_checklist_templates ct ON uc.based_on_template_id = ct.id
            LEFT JOIN climbing_events e ON uc.event_id = e.id
            WHERE uc.user_id = ?
            ORDER BY uc.created_at DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les checklists par événement
     */
    public static function getByEvent(int $eventId): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uc.*, u.nom as user_name, ct.name as template_name,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id) as total_items,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id AND is_checked = 1) as checked_items
            FROM climbing_user_checklists uc
            LEFT JOIN users u ON uc.user_id = u.id
            LEFT JOIN climbing_checklist_templates ct ON uc.based_on_template_id = ct.id
            WHERE uc.event_id = ?
            ORDER BY uc.created_at DESC
        ");
        $stmt->execute([$eventId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les checklists pour une entité (site/secteur/voie)
     */
    public static function getByEntity(string $entityType, int $entityId): array
    {
        if (!in_array($entityType, self::getValidEntityTypes())) {
            throw new \InvalidArgumentException('Type d\'entité invalide');
        }
        
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT uc.*, u.nom as user_name, ct.name as template_name,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id) as total_items,
                   (SELECT COUNT(*) FROM climbing_user_checklist_items WHERE checklist_id = uc.id AND is_checked = 1) as checked_items
            FROM climbing_user_checklists uc
            LEFT JOIN users u ON uc.user_id = u.id
            LEFT JOIN climbing_checklist_templates ct ON uc.based_on_template_id = ct.id
            WHERE uc.entity_type = ? AND uc.entity_id = ?
            ORDER BY uc.created_at DESC
        ");
        $stmt->execute([$entityType, $entityId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Créer une nouvelle checklist utilisateur
     */
    public static function create(array $data): ?self
    {
        $db = static::getConnection();
        
        // Validation du type d'entité
        if (isset($data['entity_type']) && !in_array($data['entity_type'], self::getValidEntityTypes())) {
            throw new \InvalidArgumentException('Type d\'entité invalide');
        }
        
        $stmt = $db->prepare("
            INSERT INTO climbing_user_checklists (
                name, description, user_id, based_on_template_id, event_id, entity_type, entity_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['user_id'],
            $data['based_on_template_id'] ?? null,
            $data['event_id'] ?? null,
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null
        ]);
        
        if ($success) {
            return static::find($db->lastInsertId());
        }
        
        return null;
    }
    
    /**
     * Créer une checklist à partir d'un template
     */
    public static function createFromTemplate(int $templateId, int $userId, array $options = []): ?self
    {
        $template = ChecklistTemplate::find($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template non trouvé');
        }
        
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Créer la checklist
            $checklistData = [
                'name' => $options['name'] ?? $template->name,
                'description' => $options['description'] ?? $template->description,
                'user_id' => $userId,
                'based_on_template_id' => $templateId,
                'event_id' => $options['event_id'] ?? null,
                'entity_type' => $options['entity_type'] ?? null,
                'entity_id' => $options['entity_id'] ?? null
            ];
            
            $checklist = self::create($checklistData);
            if (!$checklist) {
                throw new \Exception('Impossible de créer la checklist');
            }
            
            // Copier les items du template
            $templateItems = ChecklistItem::getByTemplate($templateId);
            foreach ($templateItems as $item) {
                UserChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'sort_order' => $item['sort_order'],
                    'is_mandatory' => $item['is_mandatory'],
                    'original_item_id' => $item['id'],
                    'equipment_type_id' => $item['equipment_type_id']
                ]);
            }
            
            // Incrémenter le compteur de copies du template
            $template->incrementCopyCount();
            
            $db->commit();
            return $checklist;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Obtenir les items de la checklist avec détails
     */
    public function getItems(): array
    {
        return UserChecklistItem::getByChecklist($this->id);
    }
    
    /**
     * Obtenir les items groupés par catégorie
     */
    public function getItemsByCategory(): array
    {
        return UserChecklistItem::getByChecklistGrouped($this->id);
    }
    
    /**
     * Calculer le progrès de la checklist
     */
    public function getProgress(): array
    {
        $db = static::getConnection();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) as checked_items,
                SUM(CASE WHEN is_mandatory = 1 THEN 1 ELSE 0 END) as mandatory_items,
                SUM(CASE WHEN is_mandatory = 1 AND is_checked = 1 THEN 1 ELSE 0 END) as checked_mandatory
            FROM climbing_user_checklist_items
            WHERE checklist_id = ?
        ");
        $stmt->execute([$this->id]);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalItems = (int)$stats['total_items'];
        $checkedItems = (int)$stats['checked_items'];
        $mandatoryItems = (int)$stats['mandatory_items'];
        $checkedMandatory = (int)$stats['checked_mandatory'];
        
        return [
            'total_items' => $totalItems,
            'checked_items' => $checkedItems,
            'mandatory_items' => $mandatoryItems,
            'checked_mandatory' => $checkedMandatory,
            'percentage' => $totalItems > 0 ? round(($checkedItems / $totalItems) * 100, 1) : 0,
            'mandatory_percentage' => $mandatoryItems > 0 ? round(($checkedMandatory / $mandatoryItems) * 100, 1) : 100,
            'is_ready' => $mandatoryItems === $checkedMandatory
        ];
    }
    
    /**
     * Marquer la checklist comme complète
     */
    public function markAsCompleted(): bool
    {
        $progress = $this->getProgress();
        if (!$progress['is_ready']) {
            throw new \Exception('Tous les éléments obligatoires doivent être cochés');
        }
        
        $db = static::getConnection();
        $stmt = $db->prepare("
            UPDATE climbing_user_checklists 
            SET is_completed = 1, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$this->id]);
        if ($success) {
            $this->is_completed = true;
        }
        
        return $success;
    }
    
    /**
     * Réinitialiser la checklist
     */
    public function reset(): bool
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Réinitialiser tous les items
            $stmt = $db->prepare("
                UPDATE climbing_user_checklist_items 
                SET is_checked = 0, checked_at = NULL, notes = NULL
                WHERE checklist_id = ?
            ");
            $stmt->execute([$this->id]);
            
            // Marquer la checklist comme non complète
            $stmt = $db->prepare("
                UPDATE climbing_user_checklists 
                SET is_completed = 0, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$this->id]);
            
            $db->commit();
            $this->is_completed = false;
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * Dupliquer la checklist
     */
    public function duplicate(int $userId, array $options = []): ?self
    {
        $db = static::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Créer la nouvelle checklist
            $newChecklistData = [
                'name' => $options['name'] ?? $this->name . ' (copie)',
                'description' => $options['description'] ?? $this->description,
                'user_id' => $userId,
                'based_on_template_id' => $this->based_on_template_id,
                'event_id' => $options['event_id'] ?? $this->event_id,
                'entity_type' => $options['entity_type'] ?? $this->entity_type,
                'entity_id' => $options['entity_id'] ?? $this->entity_id
            ];
            
            $newChecklist = self::create($newChecklistData);
            if (!$newChecklist) {
                throw new \Exception('Impossible de créer la checklist');
            }
            
            // Copier les items
            $items = $this->getItems();
            foreach ($items as $item) {
                UserChecklistItem::create([
                    'checklist_id' => $newChecklist->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'sort_order' => $item['sort_order'],
                    'is_mandatory' => $item['is_mandatory'],
                    'original_item_id' => $item['original_item_id'],
                    'equipment_type_id' => $item['equipment_type_id']
                ]);
            }
            
            $db->commit();
            return $newChecklist;
        } catch (\Exception $e) {
            $db->rollBack();
            return null;
        }
    }
    
    /**
     * Vérifier si l'utilisateur peut modifier cette checklist
     */
    public function canEdit(int $userId): bool
    {
        return $this->user_id === $userId;
    }
    
    /**
     * Mettre à jour la checklist
     */
    public function update(array $data): bool
    {
        $db = static::getConnection();
        
        // Validation du type d'entité
        if (isset($data['entity_type']) && $data['entity_type'] && !in_array($data['entity_type'], self::getValidEntityTypes())) {
            throw new \InvalidArgumentException('Type d\'entité invalide');
        }
        
        $stmt = $db->prepare("
            UPDATE climbing_user_checklists 
            SET name = ?, description = ?, event_id = ?, entity_type = ?, entity_id = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['event_id'],
            $data['entity_type'],
            $data['entity_id'],
            $this->id
        ]);
        
        if ($success) {
            $this->name = $data['name'];
            $this->description = $data['description'];
            $this->event_id = $data['event_id'];
            $this->entity_type = $data['entity_type'];
            $this->entity_id = $data['entity_id'];
        }
        
        return $success;
    }
    
    /**
     * Obtenir les statistiques des checklists utilisateur
     */
    public static function getStats(): array
    {
        $db = static::getConnection();
        
        // Statistiques générales
        $stmt = $db->query("SELECT COUNT(*) as total FROM climbing_user_checklists");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as completed FROM climbing_user_checklists WHERE is_completed = 1");
        $completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
        
        // Templates les plus utilisés
        $stmt = $db->query("
            SELECT ct.name, COUNT(*) as usage_count
            FROM climbing_user_checklists uc
            LEFT JOIN climbing_checklist_templates ct ON uc.based_on_template_id = ct.id
            WHERE uc.based_on_template_id IS NOT NULL
            GROUP BY uc.based_on_template_id
            ORDER BY usage_count DESC
            LIMIT 5
        ");
        $popularTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $total - $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'popular_templates' => $popularTemplates
        ];
    }
}