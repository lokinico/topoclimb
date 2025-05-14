<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class SectorMonth extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_sector_months';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'sector_id', 'month_id', 'quality', 'notes'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'sector_id' => 'required|numeric',
        'month_id' => 'required|numeric',
        'quality' => 'required|in:excellent,good,average,poor,avoid'
    ];

    /**
     * Relation avec le secteur
     */
    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    /**
     * Relation avec le mois
     */
    public function month()
    {
        return $this->belongsTo(Month::class, 'month_id');
    }

    /**
     * Définir la qualité des conditions pour tous les mois d'un secteur
     * 
     * @param int $sectorId ID du secteur
     * @param array $monthData Tableau associatif [month_id => quality]
     * @return void
     */
    public static function updateSectorMonths(int $sectorId, array $monthData): void
    {
        $db = self::getDb();
        
        // Supprimer les relations existantes
        $db->delete('climbing_sector_months', ['sector_id' => $sectorId]);
        
        // Créer les nouvelles relations
        foreach ($monthData as $monthId => $data) {
            if (empty($data['quality'])) {
                continue; // Ignorer les mois sans qualité spécifiée
            }
            
            $sectorMonth = new self();
            $sectorMonth->sector_id = $sectorId;
            $sectorMonth->month_id = $monthId;
            $sectorMonth->quality = $data['quality'];
            $sectorMonth->notes = $data['notes'] ?? null;
            $sectorMonth->save();
        }
    }
    
    /**
     * Obtenir la matrice des qualités pour un secteur
     * 
     * @param int $sectorId ID du secteur
     * @return array Tableau associatif [month_id => ['quality' => '...', 'notes' => '...']]
     */
    public static function getQualityMatrixForSector(int $sectorId): array
    {
        $results = self::getDb()->fetchAll(
            "SELECT month_id, quality, notes 
             FROM climbing_sector_months 
             WHERE sector_id = ?",
            [$sectorId]
        );
        
        $matrix = [];
        foreach ($results as $row) {
            $matrix[$row['month_id']] = [
                'quality' => $row['quality'],
                'notes' => $row['notes']
            ];
        }
        
        return $matrix;
    }
}