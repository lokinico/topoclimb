<?php
// src/Models/DifficultyGrade.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

class DifficultyGrade extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_difficulty_grades';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'system_id', 'value', 'numerical_value', 'sort_order'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'system_id' => 'required|numeric',
        'value' => 'required|max:10',
        'numerical_value' => 'required|numeric',
        'sort_order' => 'required|numeric'
    ];
    
    /**
     * Relation avec le système de difficulté
     */
    public function system(): ?DifficultySystem
    {
        return $this->belongsTo(DifficultySystem::class, 'system_id');
    }
    
    /**
     * Conversions où ce grade est la source
     */
    public function fromConversions(): array
    {
        return $this->hasMany(DifficultyConversion::class, 'from_grade_id');
    }
    
    /**
     * Conversions où ce grade est la cible
     */
    public function toConversions(): array
    {
        return $this->hasMany(DifficultyConversion::class, 'to_grade_id');
    }
    
    /**
     * Convertir ce grade vers un autre système
     */
    public function convertTo(DifficultySystem $targetSystem): ?DifficultyGrade
    {
        // Vérifier s'il s'agit du même système
        if ($this->system_id === $targetSystem->id) {
            return $this;
        }
        
        // Chercher une conversion directe
        $table = static::getTable();
        $sql = "SELECT * FROM climbing_difficulty_conversions 
                WHERE from_grade_id = :fromId AND to_grade_id IN 
                (SELECT id FROM climbing_difficulty_grades WHERE system_id = :systemId)";
                
        try {
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([
                ':fromId' => $this->id,
                ':systemId' => $targetSystem->id
            ]);
            
            $conversion = $statement->fetch(\PDO::FETCH_ASSOC);
            
            if ($conversion) {
                return DifficultyGrade::find($conversion['to_grade_id']);
            }
            
            // Si pas de conversion directe, utiliser la valeur numérique approximative
            $sql = "SELECT * FROM climbing_difficulty_grades 
                    WHERE system_id = :systemId 
                    ORDER BY ABS(numerical_value - :numValue) ASC 
                    LIMIT 1";
                    
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([
                ':systemId' => $targetSystem->id,
                ':numValue' => $this->numerical_value
            ]);
            
            $targetGrade = $statement->fetch(\PDO::FETCH_ASSOC);
            
            if ($targetGrade) {
                return DifficultyGrade::find($targetGrade['id']);
            }
            
            return null;
        } catch (\PDOException $e) {
            throw new ModelException("Error converting grade: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Comparer deux grades
     */
    public function compareTo(DifficultyGrade $otherGrade): int
    {
        // Si même système, comparer directement les valeurs numériques
        if ($this->system_id === $otherGrade->system_id) {
            return $this->numerical_value <=> $otherGrade->numerical_value;
        }
        
        // Sinon, convertir l'autre grade vers ce système et comparer
        $convertedGrade = $otherGrade->convertTo($this->system());
        if (!$convertedGrade) {
            throw new ModelException("Cannot compare grades from different systems without conversion");
        }
        
        return $this->numerical_value <=> $convertedGrade->numerical_value;
    }
}