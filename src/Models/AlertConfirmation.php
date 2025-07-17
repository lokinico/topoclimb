<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class AlertConfirmation extends Model
{
    protected string $table = 'climbing_alert_confirmations';
    protected array $fillable = ['alert_id', 'user_id', 'confirmed_at'];

    public static function getByAlertId(int $alertId): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT ac.*, u.username, u.email
            FROM climbing_alert_confirmations ac
            JOIN users u ON ac.user_id = u.id
            WHERE ac.alert_id = :alert_id
            ORDER BY ac.confirmed_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['alert_id' => $alertId]);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function getByUserId(int $userId): array
    {
        $db = static::getDatabase();
        $sql = "
            SELECT ac.*, a.title as alert_title, a.severity
            FROM climbing_alert_confirmations ac
            JOIN climbing_alerts a ON ac.alert_id = a.id
            WHERE ac.user_id = :user_id
            ORDER BY ac.confirmed_at DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function hasUserConfirmed(int $alertId, int $userId): bool
    {
        $db = static::getDatabase();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM climbing_alert_confirmations 
            WHERE alert_id = :alert_id AND user_id = :user_id
        ");
        
        $stmt->execute([
            'alert_id' => $alertId,
            'user_id' => $userId
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    public static function countByAlertId(int $alertId): int
    {
        $db = static::getDatabase();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM climbing_alert_confirmations 
            WHERE alert_id = :alert_id
        ");
        
        $stmt->execute(['alert_id' => $alertId]);
        
        return (int)$stmt->fetchColumn();
    }

    public function save(): bool
    {
        $db = static::getDatabase();
        
        if ($this->id) {
            $sql = "
                UPDATE climbing_alert_confirmations 
                SET alert_id = :alert_id, user_id = :user_id, confirmed_at = :confirmed_at
                WHERE id = :id
            ";
            
            $params = [
                'id' => $this->id,
                'alert_id' => $this->alert_id,
                'user_id' => $this->user_id,
                'confirmed_at' => $this->confirmed_at
            ];
        } else {
            $sql = "
                INSERT INTO climbing_alert_confirmations (alert_id, user_id, confirmed_at)
                VALUES (:alert_id, :user_id, :confirmed_at)
            ";
            
            $params = [
                'alert_id' => $this->alert_id,
                'user_id' => $this->user_id,
                'confirmed_at' => $this->confirmed_at
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
        $stmt = $db->prepare("DELETE FROM climbing_alert_confirmations WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }
}