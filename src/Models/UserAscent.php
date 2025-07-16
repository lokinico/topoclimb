<?php
// src/Models/UserAscent.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use \DateTime;

class UserAscent extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'user_ascents';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'user_id', 'route_id', 'topo_item', 'route_name', 'difficulty',
        'ascent_type', 'climbing_type', 'with_user', 'ascent_date',
        'quality_rating', 'difficulty_comment', 'attempts', 'comment',
        'favorite', 'style', 'tags'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'user_id' => 'required|numeric',
        'route_id' => 'required|numeric',
        'route_name' => 'required|max:255',
        'difficulty' => 'required|max:50',
        'ascent_type' => 'required|max:50',
        'ascent_date' => 'required|date',
        'attempts' => 'numeric',
        'favorite' => 'in:0,1'
    ];
    
    /**
     * Liste des types d'ascension possibles
     */
    public const ASCENT_TYPES = [
        'onsight' => 'À vue',
        'flash' => 'Flash',
        'redpoint' => 'Après travail',
        'toprope' => 'Moulinette',
        'attempt' => 'Essai',
        'repeat' => 'Répétition'
    ];
    
    /**
     * Liste des types d'escalade possibles
     */
    public const CLIMBING_TYPES = [
        'sport' => 'Voie sportive',
        'trad' => 'Traditionnel',
        'boulder' => 'Bloc',
        'multipitch' => 'Grande voie',
        'aid' => 'Artificiel',
        'ice' => 'Glace',
        'mixed' => 'Mixte'
    ];
    
    /**
     * Relation avec l'utilisateur
     */
    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Relation avec la voie
     */
    public function route(): ?Route
    {
        return $this->belongsTo(Route::class);
    }
    
    /**
     * Obtenir le libellé du type d'ascension
     */
    public function getAscentTypeLabel(): string
    {
        return self::ASCENT_TYPES[$this->ascent_type] ?? $this->ascent_type;
    }
    
    /**
     * Obtenir le libellé du type d'escalade
     */
    public function getClimbingTypeLabel(): string
    {
        return self::CLIMBING_TYPES[$this->climbing_type] ?? $this->climbing_type;
    }
    
    /**
     * Accesseur pour la date d'ascension formatée
     */
    public function getFormattedAscentDateAttribute(): string
    {
        if (!isset($this->attributes['ascent_date'])) {
            return '';
        }
        
        return (new DateTime($this->attributes['ascent_date']))->format('d/m/Y');
    }
    
    /**
     * Vérifie si c'est un "à vue" ou un "flash"
     */
    public function isFirstTry(): bool
    {
        return in_array($this->ascent_type, ['onsight', 'flash']);
    }
    
    /**
     * Calculer les statistiques d'ascension pour un utilisateur
     */
    public static function calculateUserStats(int $userId): array
    {
        $conn = static::getConnection();
        
        try {
            // Nombre total d'ascensions
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ?");
            $stmt->execute([$userId]);
            $totalAscents = $stmt->fetchColumn();
            
            // Nombre d'ascensions par type
            $stmt = $conn->prepare("SELECT ascent_type, COUNT(*) as count 
                                    FROM user_ascents 
                                    WHERE user_id = ? 
                                    GROUP BY ascent_type");
            $stmt->execute([$userId]);
            $ascentsByType = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Ascension la plus difficile
            $stmt = $conn->prepare("SELECT ua.*, r.difficulty_system_id 
                                    FROM user_ascents ua
                                    JOIN climbing_routes r ON ua.route_id = r.id
                                    JOIN climbing_difficulty_grades dg ON r.difficulty = dg.value AND r.difficulty_system_id = dg.system_id
                                    WHERE ua.user_id = ?
                                    ORDER BY dg.numerical_value DESC
                                    LIMIT 1");
            $stmt->execute([$userId]);
            $hardestAscentData = $stmt->fetch(\PDO::FETCH_ASSOC);
            $hardestAscent = $hardestAscentData ? static::find($hardestAscentData['id']) : null;
            
            // Jours d'escalade (nombre de jours distincts avec au moins une ascension)
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT ascent_date) as days FROM user_ascents WHERE user_id = ?");
            $stmt->execute([$userId]);
            $climbingDays = $stmt->fetchColumn();
            
            return [
                'total_ascents' => $totalAscents,
                'ascents_by_type' => $ascentsByType,
                'hardest_ascent' => $hardestAscent,
                'climbing_days' => $climbingDays,
            ];
        } catch (\PDOException $e) {
            throw new ModelException("Error calculating user stats: " . $e->getMessage());
        }
    }
    
    /**
     * Trouver toutes les voies grimpées par un utilisateur
     */
    public static function findByUser(int $userId, array $options = []): array
    {
        $table = static::getTable();
        $query = "SELECT * FROM {$table} WHERE user_id = :userId";
        $params = [':userId' => $userId];
        
        // Filtrage par type d'ascension
        if (!empty($options['ascent_type'])) {
            $query .= " AND ascent_type = :ascentType";
            $params[':ascentType'] = $options['ascent_type'];
        }
        
        // Filtrage par date
        if (!empty($options['date_from'])) {
            $query .= " AND ascent_date >= :dateFrom";
            $params[':dateFrom'] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $query .= " AND ascent_date <= :dateTo";
            $params[':dateTo'] = $options['date_to'];
        }
        
        // Tri
        $sortBy = $options['sort_by'] ?? 'ascent_date';
        $sortDir = $options['sort_dir'] ?? 'DESC';
        $query .= " ORDER BY {$sortBy} {$sortDir}";
        
        // Pagination
        if (!empty($options['limit'])) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int)$options['limit'];
            
            if (!empty($options['offset'])) {
                $query .= " OFFSET :offset";
                $params[':offset'] = (int)$options['offset'];
            }
        }
        
        try {
            $conn = static::getConnection();
            $stmt = $conn->prepare($query);
            
            // Lier les paramètres
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, 
                    is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
                );
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }
            
            return $models;
        } catch (\PDOException $e) {
            throw new ModelException("Error finding ascents: " . $e->getMessage());
        }
    }
}