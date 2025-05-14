<?php
// src/Models/Exposure.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Exposure extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_exposures';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'code', 'name', 'description', 'sort_order'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'code' => 'required|max:2',
        'name' => 'required|max:20',
        'sort_order' => 'numeric'
    ];
    
    /**
     * Relation avec les secteurs (many-to-many)
     */
    public function sectors(): array
    {
        return $this->belongsToMany(
            Sector::class,
            'climbing_sector_exposures',
            'exposure_id',
            'sector_id'
        );
    }
    
    /**
     * Obtenir toutes les expositions triées par ordre
     */
    public static function getAllSorted(): array
    {
        $table = static::getTable();
        
        try {
            $sql = "SELECT * FROM {$table} ORDER BY sort_order ASC";
            $statement = static::getConnection()->prepare($sql);
            $statement->execute();
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new ModelException("Error loading sorted exposures: " . $e->getMessage());
        }
    }
    
    /**
     * Accesseur pour le label d'exposition avec son code
     */
    public function getExposureLabelAttribute(): string
    {
        return "{$this->attributes['code']} - {$this->attributes['name']}";
    }
    
    /**
     * Récupère l'icône correspondant à l'exposition
     */
    public function getIcon(): string
    {
        $icons = [
            'N' => 'north',
            'NE' => 'north_east',
            'E' => 'east',
            'SE' => 'south_east',
            'S' => 'south',
            'SO' => 'south_west',
            'O' => 'west',
            'NO' => 'north_west',
        ];
        
        return $icons[$this->attributes['code']] ?? 'explore';
    }
    
    /**
     * Récupère les exposures d'un secteur
     */
    public static function getBySector(int $sectorId, bool $primaryOnly = false): array
    {
        $table = static::getTable();
        
        try {
            $sql = "SELECT e.* FROM {$table} e
                    JOIN climbing_sector_exposures se ON e.id = se.exposure_id
                    WHERE se.sector_id = :sectorId";
                    
            if ($primaryOnly) {
                $sql .= " AND se.is_primary = 1";
            }
            
            $sql .= " ORDER BY e.sort_order ASC";
            
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([':sectorId' => $sectorId]);
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new ModelException("Error loading sector exposures: " . $e->getMessage());
        }
    }
}