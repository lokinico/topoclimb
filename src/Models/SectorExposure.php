<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class SectorExposure extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_sector_exposures';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'sector_id', 'exposure_id', 'is_primary', 'notes'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'sector_id' => 'required|numeric',
        'exposure_id' => 'required|numeric'
    ];

    /**
     * Relation avec le secteur
     */
    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    /**
     * Relation avec l'exposition
     */
    public function exposure()
    {
        return $this->belongsTo(Exposure::class, 'exposure_id');
    }

    /**
     * Lier un secteur à plusieurs expositions
     * 
     * @param int $sectorId ID du secteur
     * @param array $exposureIds IDs des expositions
     * @param int|null $primaryExposureId ID de l'exposition principale (optionnel)
     * @return void
     */
    public static function linkSectorToExposures(int $sectorId, array $exposureIds, ?int $primaryExposureId = null): void
    {
        $db = self::getDb();
        
        // Supprimer les relations existantes
        $db->delete('climbing_sector_exposures', ['sector_id' => $sectorId]);
        
        // Créer les nouvelles relations
        foreach ($exposureIds as $exposureId) {
            $isPrimary = ($exposureId == $primaryExposureId) ? 1 : 0;
            
            $sectorExposure = new self();
            $sectorExposure->sector_id = $sectorId;
            $sectorExposure->exposure_id = $exposureId;
            $sectorExposure->is_primary = $isPrimary;
            $sectorExposure->save();
        }
    }
}