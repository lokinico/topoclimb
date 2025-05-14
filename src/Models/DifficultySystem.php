<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

class DifficultySystem extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_difficulty_systems';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'name', 'description', 'is_default'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'name' => 'required|max:50',
        'is_default' => 'numeric|in:0,1'
    ];

    /**
     * Récupérer tous les grades associés à ce système
     */
    public function grades()
    {
        return $this->hasMany(DifficultyGrade::class, 'system_id');
    }

    /**
     * Récupérer le grade par sa valeur
     */
    public function getGradeByValue(string $value)
    {
        return $this->grades()->where('value', $value)->first();
    }

    /**
     * Récupérer le système de difficulté par défaut
     */
    public static function getDefaultSystem()
    {
        return static::where('is_default', 1)->first();
    }

    /**
     * Récupérer tous les systèmes de difficulté actifs
     */
    public static function getActiveSystems()
    {
        return static::all();
    }
    
    /**
     * Convertir une difficulté d'un système à l'autre
     */
    public function convertGrade(string $value, DifficultySystem $toSystem)
    {
        $fromGrade = $this->getGradeByValue($value);
        
        if (!$fromGrade) {
            throw new ModelException("Grade '$value' not found in system '{$this->name}'");
        }
        
        return $fromGrade->convertTo($toSystem);
    }
}