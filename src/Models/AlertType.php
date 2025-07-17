<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class AlertType extends Model
{
    protected static string $table = 'climbing_alert_types';
    protected bool $timestamps = false;
    
    protected array $fillable = [
        'name',
        'description', 
        'icon',
        'color'
    ];

    public static function getAll(): array
    {
        $db = static::getConnection();
        $sql = "SELECT * FROM climbing_alert_types ORDER BY name";
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $objects = [];
        foreach ($results as $row) {
            $obj = new static();
            $obj->fill($row);
            $objects[] = $obj;
        }
        
        return $objects;
    }

    public static function getById(int $id): ?self
    {
        $db = static::getConnection();
        $stmt = $db->prepare("SELECT * FROM climbing_alert_types WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $obj = new static();
            $obj->fill($result);
            return $obj;
        }
        
        return null;
    }

    public static function getByName(string $name): ?self
    {
        $db = static::getConnection();
        $stmt = $db->prepare("SELECT * FROM climbing_alert_types WHERE name = :name");
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $obj = new static();
            $obj->fill($result);
            return $obj;
        }
        
        return null;
    }

    public function save(): bool
    {
        $db = static::getConnection();
        
        if ($this->getAttribute('id')) {
            $sql = "
                UPDATE climbing_alert_types 
                SET name = :name, description = :description, icon = :icon, color = :color
                WHERE id = :id
            ";
            
            $params = [
                'id' => $this->getAttribute('id'),
                'name' => $this->getAttribute('name'),
                'description' => $this->getAttribute('description'),
                'icon' => $this->getAttribute('icon'),
                'color' => $this->getAttribute('color')
            ];
        } else {
            $sql = "
                INSERT INTO climbing_alert_types (name, description, icon, color)
                VALUES (:name, :description, :icon, :color)
            ";
            
            $params = [
                'name' => $this->getAttribute('name'),
                'description' => $this->getAttribute('description'),
                'icon' => $this->getAttribute('icon'),
                'color' => $this->getAttribute('color')
            ];
        }
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result && !$this->getAttribute('id')) {
            $this->setAttribute('id', $db->lastInsertId());
        }
        
        return $result;
    }

    public function delete(): bool
    {
        $db = static::getConnection();
        
        // Check if there are alerts using this type
        $stmt = $db->prepare("SELECT COUNT(*) FROM climbing_alerts WHERE alert_type_id = :id");
        $stmt->execute(['id' => $this->getAttribute('id')]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return false; // Cannot delete if in use
        }
        
        $stmt = $db->prepare("DELETE FROM climbing_alert_types WHERE id = :id");
        return $stmt->execute(['id' => $this->getAttribute('id')]);
    }

    // Getter magique pour accéder aux propriétés
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    // Setter magique pour définir les propriétés
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }
}