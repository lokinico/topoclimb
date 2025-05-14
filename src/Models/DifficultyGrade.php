<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

class DifficultyGrade extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_difficulty_grades';

    /**
     * Champs remplissables en masse
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
    public function system()
    {
        return $this->belongsTo(DifficultySystem::class, 'system_id');
    }

    /**
     * Conversions où ce grade est la source
     */
    public function fromConversions()
    {
        return $this->hasMany(DifficultyConversion::class, 'from_grade_id');
    }

    /**
     * Conversions où ce grade est la cible
     */
    public function toConversions()
    {
        return $this->hasMany(DifficultyConversion::class, 'to_grade_id');
    }

    /**
     * Convertir ce grade vers un autre système
     */
    public function convertTo(DifficultySystem $targetSystem)
    {
        // Vérifier s'il s'agit du même système
        if ($this->system_id === $targetSystem->id) {
            return $this;
        }

        // Chercher une conversion directe
        $conversion = $this->db()->fetchOne(
            "SELECT * FROM climbing_difficulty_conversions 
             WHERE from_grade_id = ? AND to_grade_id IN 
             (SELECT id FROM climbing_difficulty_grades WHERE system_id = ?)",
            [$this->id, $targetSystem->id]
        );

        if ($conversion) {
            return DifficultyGrade::find($conversion['to_grade_id']);
        }

        // Si pas de conversion directe, utiliser la valeur numérique approximative
        $targetGrade = $this->db()->fetchOne(
            "SELECT * FROM climbing_difficulty_grades 
             WHERE system_id = ? 
             ORDER BY ABS(numerical_value - ?) ASC 
             LIMIT 1",
            [$targetSystem->id, $this->numerical_value]
        );

        if ($targetGrade) {
            return DifficultyGrade::find($targetGrade['id']);
        }

        throw new ModelException("No conversion found from '{$this->value}' to system '{$targetSystem->name}'");
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
        return $this->numerical_value <=> $convertedGrade->numerical_value;
    }

    /**
     * Représentation en chaîne de caractères
     */
    public function __toString(): string
    {
        return $this->value;
    }
}