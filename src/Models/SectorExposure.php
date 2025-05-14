<?php
// src/Models/SectorExposure.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class SectorExposure extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_sector_exposures';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'sector_id', 'exposure_id', 'is_primary', 'notes'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'sector_id' => 'required|numeric',
        'exposure_id' => 'required|numeric',
        'is_primary' => 'in:0,1'
    ];
    
    /**
     * Relation avec le secteur
     */
    public function sector(): ?Sector
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }
    
    /**
     * Relation avec l'exposition
     */
    public function exposure(): ?Exposure
    {
        return $this->belongsTo(Exposure::class, 'exposure_id');
    }
    
    /**
     * Lier un secteur à plusieurs expositions
     */
    public static function linkSectorToExposures(int $sectorId, array $exposureIds, ?int $primaryExposureId = null): void
    {
        $conn = static::getConnection();
        
        try {
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Supprimer les relations existantes
            $stmt = $conn->prepare("DELETE FROM " . static::getTable() . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Créer les nouvelles relations
            $stmt = $conn->prepare("INSERT INTO " . static::getTable() . " 
                                    (sector_id, exposure_id, is_primary) 
                                    VALUES (?, ?, ?)");
                                    
            foreach ($exposureIds as $exposureId) {
                $isPrimary = ($exposureId == $primaryExposureId) ? 1 : 0;
                $stmt->execute([$sectorId, $exposureId, $isPrimary]);
            }
            
            // Valider la transaction
            $conn->commit();
        } catch (\PDOException $e) {
            // Annuler les changements si une erreur survient
            $conn->rollBack();
            throw new ModelException("Error linking sector to exposures: " . $e->getMessage());
        }
    }
}