<?php
// src/Models/SectorMonth.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;
use PDO;

class SectorMonth extends Model
{
    /**
     * Nom de la table
     */
    protected static string $table = 'climbing_sector_months';
    
    /**
     * Liste des attributs remplissables en masse
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
    public function sector(): ?Sector
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }
    
    /**
     * Relation avec le mois
     */
    public function month(): ?Month
    {
        return $this->belongsTo(Month::class, 'month_id');
    }
    
    /**
     * Définir la qualité des conditions pour tous les mois d'un secteur
     * 
     * @param int $sectorId ID du secteur
     * @param array $monthData Données de qualité par mois
     * @return void
     * @throws ModelException Si la mise à jour échoue
     */
    public static function updateSectorMonths(int $sectorId, array $monthData): void
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
        
        // Vérifier les mois valides
        $validMonths = $conn->fetchAll("SELECT id FROM " . Month::getTable());
        $validMonthIds = array_column($validMonths, 'id');
        
        try {
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Supprimer les relations existantes
            $stmt = $conn->prepare("DELETE FROM " . static::$table . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Préparer l'insertion
            $stmt = $conn->prepare(
                "INSERT INTO " . static::$table . " 
                (sector_id, month_id, quality, notes, created_at) 
                VALUES (?, ?, ?, ?, ?)"
            );
            
            $now = date('Y-m-d H:i:s');
            
            // Créer les nouvelles relations
            foreach ($monthData as $monthId => $data) {
                if (empty($data['quality'])) {
                    continue; // Ignorer les mois sans qualité spécifiée
                }
                
                // Vérifier si le mois est valide
                if (!in_array($monthId, $validMonthIds)) {
                    $conn->rollBack();
                    throw new ModelException("Le mois avec l'ID {$monthId} n'existe pas");
                }
                
                $stmt->execute([
                    $sectorId,
                    $monthId,
                    $data['quality'],
                    $data['notes'] ?? null,
                    $now
                ]);
            }
            
            // Valider la transaction
            $conn->commit();
        } catch (\PDOException $e) {
            // Annuler les changements si une erreur survient
            $conn->rollBack();
            throw new ModelException("Erreur lors de la mise à jour des mois du secteur: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir la matrice des qualités pour un secteur
     */
    public static function getQualityMatrixForSector(int $sectorId): array
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT sm.month_id, sm.quality, sm.notes, m.name, m.short_name, m.month_number 
                 FROM " . static::$table . " sm
                 JOIN climbing_months m ON sm.month_id = m.id 
                 WHERE sm.sector_id = ?
                 ORDER BY m.month_number ASC"
            );
            $stmt->execute([$sectorId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $matrix = [];
            foreach ($results as $row) {
                $matrix[$row['month_id']] = [
                    'quality' => $row['quality'],
                    'notes' => $row['notes'],
                    'name' => $row['name'],
                    'short_name' => $row['short_name'],
                    'month_number' => $row['month_number']
                ];
            }
            
            return $matrix;
        } catch (\PDOException $e) {
            throw new ModelException("Error getting quality matrix: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir les meilleurs mois pour un secteur
     *
     * @param int $sectorId
     * @param string $minQuality Qualité minimum (excellent, good, average)
     * @param int $limit Nombre maximum de mois à retourner
     * @return array
     */
    public static function getBestMonthsForSector(int $sectorId, string $minQuality = 'good', int $limit = 0): array
    {
        $validQualities = ['excellent', 'good', 'average'];
        $quality = in_array($minQuality, $validQualities) ? $minQuality : 'good';
        
        // Construire la condition de qualité
        $qualityCondition = "";
        if ($quality === 'excellent') {
            $qualityCondition = "sm.quality = 'excellent'";
        } elseif ($quality === 'good') {
            $qualityCondition = "sm.quality IN ('excellent', 'good')";
        } else {
            $qualityCondition = "sm.quality IN ('excellent', 'good', 'average')";
        }
        
        $sql = "SELECT sm.*, m.name, m.short_name, m.month_number
                FROM " . static::$table . " sm
                JOIN climbing_months m ON sm.month_id = m.id
                WHERE sm.sector_id = ? AND {$qualityCondition}
                ORDER BY FIELD(sm.quality, 'excellent', 'good', 'average'), m.month_number ASC";
                
        if ($limit > 0) {
            $sql .= " LIMIT ?";
            return self::getConnection()->fetchAll($sql, [$sectorId, $limit]);
        }
        
        return self::getConnection()->fetchAll($sql, [$sectorId]);
    }
    
    /**
     * Formater les meilleurs mois d'un secteur sous forme de texte
     *
     * @param int $sectorId
     * @param string $minQuality Qualité minimum
     * @return string
     */
    public static function formatBestMonths(int $sectorId, string $minQuality = 'good'): string
    {
        $bestMonths = self::getBestMonthsForSector($sectorId, $minQuality);
        
        if (empty($bestMonths)) {
            return 'Non spécifié';
        }
        
        $monthNames = array_map(function($month) {
            return $month['short_name'];
        }, $bestMonths);
        
        return implode(', ', $monthNames);
    }
}