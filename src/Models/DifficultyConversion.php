<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class DifficultyConversion extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_difficulty_conversions';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'from_grade_id', 'to_grade_id', 'is_approximate'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'from_grade_id' => 'required|numeric',
        'to_grade_id' => 'required|numeric'
    ];

    /**
     * Relation avec le grade source
     */
    public function fromGrade()
    {
        return $this->belongsTo(DifficultyGrade::class, 'from_grade_id');
    }

    /**
     * Relation avec le grade cible
     */
    public function toGrade()
    {
        return $this->belongsTo(DifficultyGrade::class, 'to_grade_id');
    }

    /**
     * Créer une conversion bidirectionnelle entre deux grades
     */
    public static function createBidirectional(int $fromGradeId, int $toGradeId, bool $isApproximate = false): array
    {
        $conversions = [];
        
        // Conversion aller
        $forward = new self();
        $forward->from_grade_id = $fromGradeId;
        $forward->to_grade_id = $toGradeId;
        $forward->is_approximate = $isApproximate;
        $forward->save();
        $conversions[] = $forward;
        
        // Conversion retour
        $backward = new self();
        $backward->from_grade_id = $toGradeId;
        $backward->to_grade_id = $fromGradeId;
        $backward->is_approximate = $isApproximate;
        $backward->save();
        $conversions[] = $backward;
        
        return $conversions;
    }
}