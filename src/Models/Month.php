<?php
// src/Models/Month.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Month extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_months';
    
    /**
     * Liste des attributs remplissables en masse
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
    public function sectors(): array
    {
        return $this->belongsToMany(
            Sector::class,
            'climbing_sector_months',
            'month_id',
            'sector_id'
        );
    }
    
    /**
     * Obtenir tous les mois triés par numéro
     */
    public static function getAllSorted(): array
    {
        $table = static::getTable();
        
        try {
            $sql = "SELECT * FROM {$table} ORDER BY month_number ASC";
            $statement = static::getConnection()->prepare($sql);
            $statement->execute();
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new ModelException("Error loading sorted months: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir la qualité des conditions pour un secteur et ce mois
     */
    public function getQualityForSector(int $sectorId): ?string
    {
        try {
            $sql = "SELECT quality FROM climbing_sector_months 
                    WHERE sector_id = :sectorId AND month_id = :monthId";
                    
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([
                ':sectorId' => $sectorId,
                ':monthId' => $this->id
            ]);
            
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
            return $result ? $result['quality'] : null;
        } catch (\PDOException $e) {
            throw new ModelException("Error getting quality for sector: " . $e->getMessage());
        }
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
        $table = static::getTable();
        
        try {
            $sql = "SELECT m.*, sm.quality, sm.notes 
                    FROM {$table} m
                    JOIN climbing_sector_months sm ON m.id = sm.month_id
                    WHERE sm.sector_id = :sectorId AND sm.quality IN ('excellent', 'good')
                    ORDER BY m.month_number ASC";
                    
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([':sectorId' => $sectorId]);
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $month = new static($item);
                // Ajouter les propriétés pivots manuellement
                $month->quality = $item['quality'];
                $month->notes = $item['notes'];
                $models[] = $month;
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new ModelException("Error loading recommended months: " . $e->getMessage());
        }
    }
}