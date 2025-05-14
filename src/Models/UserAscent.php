<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use DateTime;

class UserAscent extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'user_ascents';

    /**
     * Champs remplissables en masse
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
        'attempts' => 'numeric'
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
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation avec la voie
     */
    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
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
     * Obtenir la date d'ascension formatée
     */
    public function getFormattedAscentDate(string $format = 'd/m/Y'): string
    {
        return (new DateTime($this->ascent_date))->format($format);
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
        $db = self::getDb();
        
        // Nombre total d'ascensions
        $totalAscents = $db->fetchOne(
            "SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ?",
            [$userId]
        )['count'] ?? 0;
        
        // Nombre d'ascensions par type
        $ascentsByType = $db->fetchAll(
            "SELECT ascent_type, COUNT(*) as count 
             FROM user_ascents 
             WHERE user_id = ? 
             GROUP BY ascent_type",
            [$userId]
        );
        
        // Ascension la plus difficile
        $hardestAscent = $db->fetchOne(
            "SELECT ua.*, r.difficulty_system_id 
             FROM user_ascents ua
             JOIN climbing_routes r ON ua.route_id = r.id
             JOIN climbing_difficulty_grades dg ON r.difficulty = dg.value AND r.difficulty_system_id = dg.system_id
             WHERE ua.user_id = ?
             ORDER BY dg.numerical_value DESC
             LIMIT 1",
            [$userId]
        );
        
        // Jours d'escalade (nombre de jours distincts avec au moins une ascension)
        $climbingDays = $db->fetchOne(
            "SELECT COUNT(DISTINCT ascent_date) as days FROM user_ascents WHERE user_id = ?",
            [$userId]
        )['days'] ?? 0;
        
        return [
            'total_ascents' => $totalAscents,
            'ascents_by_type' => $ascentsByType,
            'hardest_ascent' => $hardestAscent ? self::find($hardestAscent['id']) : null,
            'climbing_days' => $climbingDays,
        ];
    }

    /**
     * Trouver toutes les voies grimpées par un utilisateur
     */
    public static function findByUser(int $userId, array $options = []): array
    {
        $query = "SELECT * FROM user_ascents WHERE user_id = ?";
        $params = [$userId];
        
        // Filtrage par type d'ascension
        if (!empty($options['ascent_type'])) {
            $query .= " AND ascent_type = ?";
            $params[] = $options['ascent_type'];
        }
        
        // Filtrage par date
        if (!empty($options['date_from'])) {
            $query .= " AND ascent_date >= ?";
            $params[] = $options['date_from'];
        }
        
        if (!empty($options['date_to'])) {
            $query .= " AND ascent_date <= ?";
            $params[] = $options['date_to'];
        }
        
        // Tri
        $query .= " ORDER BY " . ($options['sort_by'] ?? 'ascent_date') . " " . 
                  ($options['sort_dir'] ?? 'DESC');
        
        // Pagination
        if (!empty($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
            
            if (!empty($options['offset'])) {
                $query .= " OFFSET " . (int)$options['offset'];
            }
        }
        
        return array_map(
            fn($data) => self::hydrate($data),
            self::getDb()->fetchAll($query, $params)
        );
    }
}