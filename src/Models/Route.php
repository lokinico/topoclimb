<?php
// src/Models/Route.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Route extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_routes';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'sector_id', 'name', 'number', 'difficulty', 'difficulty_system_id',
        'beauty', 'style', 'length', 'equipment', 'rappel', 'comment', 
        'legacy_topo_item', 'active'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'sector_id' => 'required|numeric',
        'name' => 'required|max:255',
        'number' => 'required|numeric',
        'difficulty' => 'max:10',
        'difficulty_system_id' => 'required|numeric',
        'beauty' => 'in:0,1,2,3,4,5',
        'style' => 'in:sport,trad,mix,boulder,aid,ice,other',
        'length' => 'numeric',
        'equipment' => 'in:poor,adequate,good,excellent',
        'active' => 'in:0,1'
    ];
    
    /**
     * Relation avec le secteur
     */
    public function sector(): ?Sector
    {
        return $this->belongsTo(Sector::class);
    }
    
    /**
     * Relation avec le système de difficulté
     */
    public function difficultySystem(): ?DifficultySystem
    {
        return $this->belongsTo(DifficultySystem::class);
    }
    
    /**
     * Relation avec les ascensions des utilisateurs
     */
    public function ascents(): array
    {
        return $this->hasMany(UserAscent::class);
    }
    
    /**
     * Accesseur pour la beauté formatée (étoiles)
     */
    public function getBeautyStarsAttribute(): string
    {
        $beauty = (int) ($this->attributes['beauty'] ?? 0);
        return str_repeat('★', $beauty) . str_repeat('☆', 5 - $beauty);
    }
    
    /**
     * Accesseur pour la longueur formatée
     */
    public function getLengthFormattedAttribute(): string
    {
        $length = $this->attributes['length'] ?? null;
        
        if ($length === null) {
            return 'Non spécifié';
        }
        
        return "{$length} m";
    }
    
    /**
     * Accesseur pour l'équipement formaté
     */
    public function getEquipmentFormattedAttribute(): string
    {
        $equipment = $this->attributes['equipment'] ?? null;
        
        if ($equipment === null) {
            return 'Non spécifié';
        }
        
        return match($equipment) {
            'poor' => 'Mauvais',
            'adequate' => 'Adéquat',
            'good' => 'Bon',
            'excellent' => 'Excellent',
            default => $equipment
        };
    }
    
    /**
     * Accesseur pour le style formaté
     */
    public function getStyleFormattedAttribute(): string
    {
        $style = $this->attributes['style'] ?? null;
        
        if ($style === null) {
            return 'Non spécifié';
        }
        
        return match($style) {
            'sport' => 'Sportif',
            'trad' => 'Traditionnel',
            'mix' => 'Mixte',
            'boulder' => 'Bloc',
            'aid' => 'Artificiel',
            'ice' => 'Glace',
            'other' => 'Autre',
            default => $style
        };
    }
    
    /**
     * Méthode pour récupérer les routes par difficulté
     */
    public static function findByDifficulty(string $difficulty): array
    {
        return static::where(['difficulty' => $difficulty]);
    }
    
    /**
     * Méthode pour récupérer les routes par style
     */
    public static function findByStyle(string $style): array
    {
        return static::where(['style' => $style]);
    }
    
    /**
     * Méthode pour récupérer les routes avec notation de beauté minimale
     */
    public static function findByMinBeauty(int $minBeauty): array
    {
        $table = static::getTable();
        
        try {
            $sql = "SELECT * FROM {$table} WHERE CAST(beauty AS UNSIGNED) >= :minBeauty";
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([':minBeauty' => $minBeauty]);
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new \TopoclimbCH\Exceptions\ModelException("Erreur lors de la recherche par beauté minimale: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Événement avant la création
     */
    protected function onCreating(): bool
    {
        // Assigner automatiquement un numéro si non spécifié
        if (!isset($this->attributes['number']) || empty($this->attributes['number'])) {
            $this->attributes['number'] = $this->getNextRouteNumber();
        }
        
        return true;
    }
    
    /**
     * Récupère le prochain numéro de voie disponible pour le secteur
     */
    protected function getNextRouteNumber(): int
    {
        if (!isset($this->attributes['sector_id'])) {
            return 1;
        }
        
        $table = static::getTable();
        $sectorId = $this->attributes['sector_id'];
        
        try {
            $sql = "SELECT MAX(number) FROM {$table} WHERE sector_id = :sectorId";
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([':sectorId' => $sectorId]);
            $maxNumber = $statement->fetchColumn();
            
            return $maxNumber ? $maxNumber + 1 : 1;
        } catch (\PDOException $e) {
            return 1;
        }
    }
}