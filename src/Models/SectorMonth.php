<?php
// src/Models/SectorMonth.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class SectorMonth extends Model
{
    /**
     * Nom de la table en base de données
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
     */
    public static function updateSectorMonths(int $sectorId, array $monthData): void
    {
        $conn = static::getConnection();
        
        try {
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Supprimer les relations existantes
            $stmt = $conn->prepare("DELETE FROM " . static::getTable() . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Préparer l'insertion
            $stmt = $conn->prepare("INSERT INTO " . static::getTable() . " 
                                    (sector_id, month_id, quality, notes) 
                                    VALUES (?, ?, ?, ?)");
            
            // Créer les nouvelles relations
            foreach ($monthData as $monthId => $data) {
                if (empty($data['quality'])) {
                    continue; // Ignorer les mois sans qualité spécifiée
                }
                
                $stmt->execute([
                    $sectorId,
                    $monthId,
                    $data['quality'],
                    $data['notes'] ?? null
                ]);
            }
            
            // Valider la transaction
            $conn->commit();
        } catch (\PDOException $e) {
            // Annuler les changements si une erreur survient
            $conn->rollBack();
            throw new ModelException("Error updating sector months: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir la matrice des qualités pour un secteur
     */
    public static function getQualityMatrixForSector(int $sectorId): array
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT month_id, quality, notes 
                 FROM " . static::getTable() . " 
                 WHERE sector_id = ?"
            );
            $stmt->execute([$sectorId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $matrix = [];
            foreach ($results as $row) {
                $matrix[$row['month_id']] = [
                    'quality' => $row['quality'],
                    'notes' => $row['notes']
                ];
            }
            
            return $matrix;
        } catch (\PDOException $e) {
            throw new ModelException("Error getting quality matrix: " . $e->getMessage());
        }
    }
}