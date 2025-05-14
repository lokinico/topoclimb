<?php
// src/Models/SectorExposure.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

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
     * Obtenir l'exposition principale d'un secteur
     *
     * @param int $sectorId
     * @return array|null
     */
    public static function getPrimarySectorExposure(int $sectorId): ?array
    {
        $sql = "SELECT se.*, e.name, e.code
                FROM " . static::$table . " se
                JOIN climbing_exposures e ON se.exposure_id = e.id
                WHERE se.sector_id = ? AND se.is_primary = 1
                LIMIT 1";
                
        return self::getConnection()->fetchOne($sql, [$sectorId]);
    }
    
    /**
     * Récupérer toutes les expositions d'un secteur
     *
     * @param int $sectorId
     * @return array
     */
    public static function getAllBySector(int $sectorId): array
    {
        $sql = "SELECT se.*, e.name, e.code
                FROM " . static::$table . " se
                JOIN climbing_exposures e ON se.exposure_id = e.id
                WHERE se.sector_id = ?
                ORDER BY se.is_primary DESC, e.sort_order ASC";
                
        return self::getConnection()->fetchAll($sql, [$sectorId]);
    }
    
    /**
     * Lier un secteur à plusieurs expositions
     *
     * @param int $sectorId ID du secteur
     * @param array $exposureIds IDs des expositions
     * @param int|null $primaryExposureId ID de l'exposition principale (optionnel)
     * @return void
     * @throws ModelException
     */
    public static function linkSectorToExposures(int $sectorId, array $exposureIds, ?int $primaryExposureId = null): void
    {
        $conn = static::getConnection();
        
        try {
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Supprimer les relations existantes
            $stmt = $conn->prepare("DELETE FROM " . static::$table . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Créer les nouvelles relations
            $stmt = $conn->prepare(
                "INSERT INTO " . static::$table . " 
                (sector_id, exposure_id, is_primary, created_at) 
                VALUES (?, ?, ?, ?)"
            );
            
            $now = date('Y-m-d H:i:s');
                                    
            foreach ($exposureIds as $exposureId) {
                $isPrimary = ($exposureId == $primaryExposureId) ? 1 : 0;
                $stmt->execute([$sectorId, $exposureId, $isPrimary, $now]);
            }
            
            // Valider la transaction
            $conn->commit();
        } catch (\PDOException $e) {
            // Annuler les changements si une erreur survient
            $conn->rollBack();
            throw new ModelException("Error linking sector to exposures: " . $e->getMessage());
        }
    }
    
    /**
     * Formater les expositions d'un secteur sous forme de texte
     *
     * @param int $sectorId
     * @param bool $includePrimaryOnly N'inclure que l'exposition principale
     * @return string
     */
    public static function formatSectorExposures(int $sectorId, bool $includePrimaryOnly = false): string
    {
        $exposures = [];
        
        if ($includePrimaryOnly) {
            $exposure = self::getPrimarySectorExposure($sectorId);
            if ($exposure) {
                $exposures[] = $exposure['name'] . ' (' . $exposure['code'] . ')';
            }
        } else {
            $allExposures = self::getAllBySector($sectorId);
            foreach ($allExposures as $exposure) {
                $label = $exposure['name'] . ' (' . $exposure['code'] . ')';
                if ($exposure['is_primary']) {
                    $label .= ' *';
                }
                $exposures[] = $label;
            }
        }
        
        return implode(', ', $exposures);
    }
}