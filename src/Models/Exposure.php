<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Exposure extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_exposures';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'code', 'name', 'description', 'sort_order'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'code' => 'required|max:2',
        'name' => 'required|max:20'
    ];

    /**
     * Relation avec les secteurs (many-to-many)
     */
    public function sectors()
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
        return self::all(['sort_order' => 'ASC']);
    }

    /**
     * Obtenir le label d'exposition avec son code
     */
    public function getExposureLabel(): string
    {
        return "{$this->code} - {$this->name}";
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
        
        return $icons[$this->code] ?? 'explore';
    }
    
    /**
     * Récupère les exposures d'un secteur
     */
    public static function getBySector(int $sectorId, bool $primaryOnly = false): array
    {
        $query = "SELECT e.* FROM climbing_exposures e
                 JOIN climbing_sector_exposures se ON e.id = se.exposure_id
                 WHERE se.sector_id = ?";
                 
        if ($primaryOnly) {
            $query .= " AND se.is_primary = 1";
        }
        
        $query .= " ORDER BY e.sort_order ASC";
        
        return array_map(
            fn($data) => self::hydrate($data),
            self::getDb()->fetchAll($query, [$sectorId])
        );
    }
}