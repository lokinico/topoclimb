<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Month extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_months';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'month_number', 'name', 'short_name'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'month_number' => 'required|numeric|min:1|max:12',
        'name' => 'required|max:20',
        'short_name' => 'required|max:3'
    ];

    /**
     * Relation avec les secteurs (many-to-many)
     */
    public function sectors()
    {
        return $this->belongsToMany(
            Sector::class,
            'climbing_sector_months',
            'month_id',
            'sector_id'
        )->withPivot(['quality', 'notes']);
    }

    /**
     * Obtenir tous les mois triés par numéro
     */
    public static function getAllSorted(): array
    {
        return self::all(['month_number' => 'ASC']);
    }

    /**
     * Obtenir la qualité des conditions pour un secteur et ce mois
     */
    public function getQualityForSector(int $sectorId): ?string
    {
        $relation = $this->db()->fetchOne(
            "SELECT quality FROM climbing_sector_months 
             WHERE sector_id = ? AND month_id = ?",
            [$sectorId, $this->id]
        );
        
        return $relation['quality'] ?? null;
    }

    /**
     * Récupère la classe CSS correspondant à la qualité
     */
    public static function getQualityCssClass(string $quality): string
    {
        return [
            'excellent' => 'bg-success',
            'good' => 'bg-info',
            'average' => 'bg-warning',
            'poor' => 'bg-secondary',
            'avoid' => 'bg-danger'
        ][$quality] ?? 'bg-light';
    }
    
    /**
     * Récupère le label de qualité
     */
    public static function getQualityLabel(string $quality): string
    {
        return [
            'excellent' => 'Excellent',
            'good' => 'Bon',
            'average' => 'Moyen',
            'poor' => 'Mauvais',
            'avoid' => 'À éviter'
        ][$quality] ?? 'Inconnu';
    }
    
    /**
     * Récupère les mois recommandés pour un secteur
     */
    public static function getRecommendedMonthsForSector(int $sectorId): array
    {
        $query = "SELECT m.*, sm.quality, sm.notes 
                 FROM climbing_months m
                 JOIN climbing_sector_months sm ON m.id = sm.month_id
                 WHERE sm.sector_id = ? AND sm.quality IN ('excellent', 'good')
                 ORDER BY m.month_number ASC";
                 
        return array_map(
            function($data) {
                $month = self::hydrate($data);
                $month->quality = $data['quality'];
                $month->notes = $data['notes'];
                return $month;
            },
            self::getDb()->fetchAll($query, [$sectorId])
        );
    }
}