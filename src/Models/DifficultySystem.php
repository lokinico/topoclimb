<?php
// src/Models/DifficultySystem.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

class DifficultySystem extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_difficulty_systems';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'name', 'description', 'is_default'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'name' => 'required|max:50',
        'is_default' => 'in:0,1'
    ];
    
    /**
     * Relation avec les grades associés à ce système
     */
    public function grades(): array
    {
        return $this->hasMany(DifficultyGrade::class, 'system_id');
    }
    
    /**
     * Récupérer le grade par sa valeur
     */
    public function getGradeByValue(string $value): ?DifficultyGrade
    {
        return DifficultyGrade::findWhere([
            'system_id' => $this->id, 
            'value' => $value
        ]);
    }
    
    /**
     * Récupérer le système de difficulté par défaut
     */
    public static function getDefaultSystem(): ?DifficultySystem
    {
        return static::findWhere(['is_default' => 1]);
    }
    
    /**
     * Récupérer tous les systèmes de difficulté
     */
    public static function getActiveSystems(): array
    {
        return static::all();
    }
    
    /**
     * Convertir une difficulté d'un système à l'autre
     */
    public function convertGrade(string $value, DifficultySystem $toSystem): ?string
    {
        $fromGrade = $this->getGradeByValue($value);
        
        if (!$fromGrade) {
            throw new ModelException("Grade '$value' not found in system '{$this->name}'");
        }
        
        $convertedGrade = $fromGrade->convertTo($toSystem);
        return $convertedGrade ? $convertedGrade->value : null;
    }
}