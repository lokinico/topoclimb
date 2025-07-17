<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Alert extends Model
{
    protected string $table = 'climbing_alerts';
    protected array $fillable = [
        'title', 'description', 'alert_type_id', 'region_id', 'site_id', 
        'sector_id', 'severity', 'start_date', 'end_date', 'active', 
        'created_by', 'created_at', 'updated_at'
    ];

    public static function getAll(): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   r.name as region_name,
                   s.name as site_name,
                   sec.name as sector_name,
                   u.username as created_by_username,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            WHERE a.active = 1
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ";
        
        return $db->query($sql)->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function getById(int $id): ?object
    {
        $db = static::getDatabase();
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   at.icon as alert_type_icon,
                   r.name as region_name,
                   s.name as site_name,
                   sec.name as sector_name,
                   u.username as created_by_username,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            WHERE a.id = :id
            GROUP BY a.id
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        
        return $result ?: null;
    }

    public static function getFilteredAlerts(array $filters, int $offset = 0, int $limit = 20): array
    {
        $db = static::getDatabase();
        $conditions = ['a.active = 1'];
        $params = [];

        if (!empty($filters['region_id'])) {
            $conditions[] = 'a.region_id = :region_id';
            $params['region_id'] = $filters['region_id'];
        }

        if (!empty($filters['site_id'])) {
            $conditions[] = 'a.site_id = :site_id';
            $params['site_id'] = $filters['site_id'];
        }

        if (!empty($filters['alert_type_id'])) {
            $conditions[] = 'a.alert_type_id = :alert_type_id';
            $params['alert_type_id'] = $filters['alert_type_id'];
        }

        if (!empty($filters['severity'])) {
            $conditions[] = 'a.severity = :severity';
            $params['severity'] = $filters['severity'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(a.title LIKE :search OR a.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $conditions[0] = 'a.active = :active';
            $params['active'] = (int)$filters['active'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   at.icon as alert_type_icon,
                   r.name as region_name,
                   s.name as site_name,
                   sec.name as sector_name,
                   u.username as created_by_username,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            $whereClause
            GROUP BY a.id
            ORDER BY a.severity DESC, a.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function countFilteredAlerts(array $filters): int
    {
        $db = static::getDatabase();
        $conditions = ['a.active = 1'];
        $params = [];

        if (!empty($filters['region_id'])) {
            $conditions[] = 'a.region_id = :region_id';
            $params['region_id'] = $filters['region_id'];
        }

        if (!empty($filters['site_id'])) {
            $conditions[] = 'a.site_id = :site_id';
            $params['site_id'] = $filters['site_id'];
        }

        if (!empty($filters['alert_type_id'])) {
            $conditions[] = 'a.alert_type_id = :alert_type_id';
            $params['alert_type_id'] = $filters['alert_type_id'];
        }

        if (!empty($filters['severity'])) {
            $conditions[] = 'a.severity = :severity';
            $params['severity'] = $filters['severity'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(a.title LIKE :search OR a.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $conditions[0] = 'a.active = :active';
            $params['active'] = (int)$filters['active'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        $sql = "
            SELECT COUNT(DISTINCT a.id) as total
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            $whereClause
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }

    public static function getByRegion(int $regionId): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   at.icon as alert_type_icon,
                   s.name as site_name,
                   sec.name as sector_name,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            WHERE a.region_id = :region_id 
            AND a.active = 1
            AND (a.end_date IS NULL OR a.end_date >= CURDATE())
            GROUP BY a.id
            ORDER BY a.severity DESC, a.created_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['region_id' => $regionId]);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function getBySite(int $siteId): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   at.icon as alert_type_icon,
                   sec.name as sector_name,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            WHERE a.site_id = :site_id 
            AND a.active = 1
            AND (a.end_date IS NULL OR a.end_date >= CURDATE())
            GROUP BY a.id
            ORDER BY a.severity DESC, a.created_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['site_id' => $siteId]);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function getActiveAlerts(): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT a.*, 
                   at.name as alert_type_name,
                   at.icon as alert_type_icon,
                   r.name as region_name,
                   s.name as site_name,
                   sec.name as sector_name,
                   COUNT(ac.id) as confirmation_count
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types at ON a.alert_type_id = at.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            LEFT JOIN climbing_alert_confirmations ac ON a.id = ac.alert_id
            WHERE a.active = 1
            AND a.start_date <= CURDATE()
            AND (a.end_date IS NULL OR a.end_date >= CURDATE())
            GROUP BY a.id
            ORDER BY a.severity DESC, a.created_at DESC
        ";
        
        return $db->query($sql)->fetchAll(\PDO::FETCH_OBJ);
    }

    public function save(): bool
    {
        $db = static::getDatabase();
        
        if ($this->id) {
            $sql = "
                UPDATE climbing_alerts 
                SET title = :title, description = :description, alert_type_id = :alert_type_id,
                    region_id = :region_id, site_id = :site_id, sector_id = :sector_id,
                    severity = :severity, start_date = :start_date, end_date = :end_date,
                    active = :active, updated_at = :updated_at
                WHERE id = :id
            ";
            
            $params = [
                'id' => $this->id,
                'title' => $this->title,
                'description' => $this->description,
                'alert_type_id' => $this->alert_type_id,
                'region_id' => $this->region_id,
                'site_id' => $this->site_id,
                'sector_id' => $this->sector_id,
                'severity' => $this->severity,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'active' => $this->active ? 1 : 0,
                'updated_at' => $this->updated_at
            ];
        } else {
            $sql = "
                INSERT INTO climbing_alerts 
                (title, description, alert_type_id, region_id, site_id, sector_id, 
                 severity, start_date, end_date, active, created_by, created_at, updated_at)
                VALUES (:title, :description, :alert_type_id, :region_id, :site_id, :sector_id,
                        :severity, :start_date, :end_date, :active, :created_by, :created_at, :updated_at)
            ";
            
            $params = [
                'title' => $this->title,
                'description' => $this->description,
                'alert_type_id' => $this->alert_type_id,
                'region_id' => $this->region_id,
                'site_id' => $this->site_id,
                'sector_id' => $this->sector_id,
                'severity' => $this->severity,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'active' => $this->active ? 1 : 0,
                'created_by' => $this->created_by,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at ?? $this->created_at
            ];
        }
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result && !$this->id) {
            $this->id = $db->lastInsertId();
        }
        
        return $result;
    }

    public function delete(): bool
    {
        $db = static::getDatabase();
        
        // Delete confirmations first
        $db->prepare("DELETE FROM climbing_alert_confirmations WHERE alert_id = :id")
           ->execute(['id' => $this->id]);
        
        // Delete alert
        $stmt = $db->prepare("DELETE FROM climbing_alerts WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    public function getSeverityColor(): string
    {
        switch ($this->severity) {
            case 'critical':
                return '#dc3545'; // Red
            case 'high':
                return '#fd7e14'; // Orange
            case 'medium':
                return '#ffc107'; // Yellow
            case 'low':
                return '#28a745'; // Green
            default:
                return '#6c757d'; // Gray
        }
    }

    public function getSeverityLabel(): string
    {
        switch ($this->severity) {
            case 'critical':
                return 'Critique';
            case 'high':
                return 'Élevée';
            case 'medium':
                return 'Moyenne';
            case 'low':
                return 'Faible';
            default:
                return 'Inconnue';
        }
    }

    public function isActive(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = new \DateTime();
        $startDate = new \DateTime($this->start_date);
        
        if ($startDate > $now) {
            return false;
        }

        if ($this->end_date) {
            $endDate = new \DateTime($this->end_date);
            if ($endDate < $now) {
                return false;
            }
        }

        return true;
    }
}