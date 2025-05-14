<?php
// src/Models/SectorMonth.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;
use PDO;

class SectorMonth extends Model
{
    // Code existant conservé...
    
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
            $stmt = $conn->prepare("DELETE FROM " . static::getTable() . " WHERE sector_id = ?");
            $stmt->execute([$sectorId]);
            
            // Préparer l'insertion
            $stmt = $conn->prepare(
                "INSERT INTO " . static::getTable() . " 
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