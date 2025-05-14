<?php
// src/Models/SectorExposure.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;
use PDO;

class SectorExposure extends Model
{
    // Code existant conservé...
    
    /**
     * Lier un secteur à plusieurs expositions
     * 
     * @param int $sectorId ID du secteur
     * @param array $exposureIds IDs des expositions
     * @param int|null $primaryExposureId ID de l'exposition principale (facultatif)
     * @return void
     * @throws ModelException Si la liaison échoue
     */
    public static function linkSectorToExposures(int $sectorId, array $exposureIds, ?int $primaryExposureId = null): void
    {
        $conn = static::getConnection();
        
        // Vérifier si le secteur existe
        $sectorExists = $conn->fetchOne(
            "SELECT 1 FROM " . Sector::getTable() . " WHERE id = ?", 
            [$sectorId]
        );
        
        if (!$sectorExists) {
            throw new ModelException("Le secteur avec l'ID {$sectorId} n'existe pas");
        }
        
        // Vérifier si toutes les expositions existent
        $exposureIdsStr = implode(',', array_map('intval', $exposureIds));
        $existingExposures = $conn->fetchAll(
            "SELECT id FROM " . Exposure::getTable() . " WHERE id IN ({$exposureIdsStr})"
        );
        
        $existingExposureIds = array_column($existingExposures, 'id');
        $invalidIds = array_diff($exposureIds, $existingExposureIds);
        
        if (!empty($invalidIds)) {
            throw new ModelException("Les expositions suivantes n'existent pas: " . implode(', ', $invalidIds));
        }
        
        try {
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Supprimer les relations existantes
            $stmt = $conn->prepare("DELETE FROM " . static::getTable() . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Préparer la requête d'insertion une seule fois
            $stmt = $conn->prepare(
                "INSERT INTO " . static::getTable() . " (sector_id, exposure_id, is_primary, created_at) 
                 VALUES (?, ?, ?, ?)"
            );
            
            $now = date('Y-m-d H:i:s');
            
            // Créer les nouvelles relations
            foreach ($exposureIds as $exposureId) {
                $isPrimary = ($exposureId == $primaryExposureId) ? 1 : 0;
                $stmt->execute([$sectorId, $exposureId, $isPrimary, $now]);
            }
            
            // Valider la transaction
            $conn->commit();
        } catch (\PDOException $e) {
            // Annuler les changements si une erreur survient
            $conn->rollBack();
            throw new ModelException("Erreur lors de la liaison du secteur aux expositions: " . $e->getMessage());
        }
    }
}