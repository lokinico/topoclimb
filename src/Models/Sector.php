<?php
// src/Models/Sector.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Core\Filtering\SectorFilter;
use TopoclimbCH\Exceptions\ModelException;

class Sector extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_sectors';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'book_id', 'region_id', 'name', 'code', 'description', 'access_info', 
        'color', 'access_time', 'altitude', 'approach', 'height', 
        'parking_info', 'coordinates_lat', 'coordinates_lng',
        'coordinates_swiss_e', 'coordinates_swiss_n', 'active'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'book_id' => 'required|numeric',
        'name' => 'required|max:255',
        'code' => 'required|max:50',
        'coordinates_lat' => 'nullable|numeric|min:-90|max:90',
        'coordinates_lng' => 'nullable|numeric|min:-180|max:180',
        'altitude' => 'nullable|numeric|min:0|max:9000',
        'active' => 'in:0,1'
    ];
    
    /**
     * Relation avec les voies d'escalade
     */
    public function routes(): array
    {
        return $this->hasMany(Route::class);
    }
    
    /**
     * Relation avec la région
     */
    public function region(): ?Region
    {
        return $this->belongsTo(Region::class);
    }
    
    /**
     * Relation avec le livre/site
     */
    public function book(): ?Book
    {
        return $this->belongsTo(Book::class);
    }
    
    /**
     * Relation avec les expositions
     */
    public function exposures(): array
    {
        return $this->belongsToMany(
            Exposure::class, 
            'climbing_sector_exposures', 
            'sector_id', 
            'exposure_id'
        );
    }
    
    /**
     * Relation avec les mois (qualité par mois)
     */
    public function months(): array
    {
        return $this->belongsToMany(
            Month::class, 
            'climbing_sector_months', 
            'sector_id', 
            'month_id'
        );
    }
    
    /**
     * Récupère les parkings associés au secteur
     */
    public function parkings(): array
    {
        return $this->belongsToMany(
            Parking::class, 
            'parking_secteur', 
            'secteur_id', 
            'parking_id'
        );
    }
    
    /**
     * Mutateur pour le champ active
     */
    public function setActiveAttribute($value): bool
    {
        return (bool) $value;
    }
    
    /**
     * Accesseur pour le temps d'accès formaté
     */
    public function getAccessTimeFormattedAttribute(): string
    {
        $time = $this->attributes['access_time'] ?? null;
        
        if ($time === null) {
            return 'Non spécifié';
        }
        
        if ($time < 60) {
            return "{$time} minutes";
        }
        
        $hours = floor($time / 60);
        $minutes = $time % 60;
        
        if ($minutes === 0) {
            return "{$hours} heure" . ($hours > 1 ? 's' : '');
        }
        
        return "{$hours}h{$minutes}";
    }
    
    /**
     * Récupère les secteurs actifs
     */
    public static function active(): array
    {
        return static::where(['active' => 1]);
    }
    
    /**
     * Méthode pour vérifier si le secteur a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return isset($this->attributes['coordinates_lat']) && 
               isset($this->attributes['coordinates_lng']) &&
               $this->attributes['coordinates_lat'] !== null &&
               $this->attributes['coordinates_lng'] !== null;
    }
    
    /**
     * Méthode pour obtenir l'URL Google Maps
     */
    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->hasCoordinates()) {
            return null;
        }
        
        $lat = $this->attributes['coordinates_lat'];
        $lng = $this->attributes['coordinates_lng'];
        
        return "https://www.google.com/maps?q={$lat},{$lng}";
    }
    
    /**
     * Événement avant la sauvegarde
     * 
     * @throws ModelException
     * @return bool
     */
    protected function onSaving(): bool
    {
        // S'assurer que le code est unique
        if (isset($this->attributes['code'])) {
            $this->attributes['code'] = $this->generateUniqueCode($this->attributes['code']);
        }
        
        return true;
    }
    
    /**
     * Génère un code unique
     * 
     * @param string $baseCode
     * @return string
     * @throws ModelException
     */
    protected function generateUniqueCode(string $baseCode): string
    {
        $code = $baseCode;
        $counter = 1;
        $maxAttempts = 100; // Éviter une boucle infinie
        
        while ($counter <= $maxAttempts) {
            // Vérifier si le code existe déjà
            $sql = "SELECT id FROM " . static::$table . " WHERE code = ?";
            if (isset($this->id)) {
                $sql .= " AND id != ?";
                $params = [$code, $this->id];
            } else {
                $params = [$code];
            }
            
            $existing = self::getConnection()->fetchOne($sql, $params);
            
            // Si le code n'existe pas, l'utiliser
            if (!$existing) {
                return $code;
            }
            
            // Sinon, générer un nouveau code avec un compteur
            $code = "{$baseCode}-{$counter}";
            $counter++;
        }
        
        throw new ModelException("Impossible de générer un code unique après {$maxAttempts} tentatives pour '{$baseCode}'");
    }
    
    /**
     * Valide les coordonnées géographiques
     * 
     * @return bool
     */
    public function validateCoordinates(): bool
    {
        if (!$this->hasCoordinates()) {
            return true; // Pas de coordonnées à valider
        }
        
        $lat = $this->attributes['coordinates_lat'];
        $lng = $this->attributes['coordinates_lng'];
        
        return is_numeric($lat) && is_numeric($lng) && 
               $lat >= -90 && $lat <= 90 && 
               $lng >= -180 && $lng <= 180;
    }
    
    /**
     * Récupère les secteurs avec pagination et filtrage
     *
     * @param Filter $filter Filtre à appliquer
     * @param int $page Page courante
     * @param int $perPage Nombre d'éléments par page
     * @param string|null $orderBy Champ pour le tri
     * @param string $direction Direction du tri (ASC/DESC)
     * @return Paginator
     */
    public static function filterAndPaginate(
        \TopoclimbCH\Core\Filtering\Filter $filter,
        int $page = 1,
        int $perPage = 15,
        ?string $orderBy = null,
        string $direction = 'ASC'
    ): \TopoclimbCH\Core\Pagination\Paginator {
        // S'assurer que les valeurs sont valides
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        
        // Utiliser 'name' comme champ de tri par défaut si non spécifié
        $sortBy = $orderBy ?? 'name';
        $sortDir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        
        // Préfixe de table pour les secteurs
        $tablePrefix = 's';
        
        // Obtenir l'instance de Database
        $db = \TopoclimbCH\Core\Database::getInstance();
        
        // Construire la clause WHERE à partir du filtre
        $filterResult = $filter->apply();
        $whereConditions = $filterResult['conditions'] ?? [];
        $whereParams = $filterResult['parameters'] ?? [];
        $joins = $filterResult['joins'] ?? [];
        
        // Simplifier la condition d'active pour déboguer
        // Note : nous gardons cette condition, mais vous pourriez la commenter temporairement
        // pour voir si c'est elle qui cause le problème
        $whereConditions[] = "{$tablePrefix}.active = 1";
        
        // Construire la clause WHERE complète
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(' AND ', $whereConditions);
        }
        
        // Construire les jointures
        $joinClause = '';
        if (!empty($joins)) {
            $joinClause = implode(' ', array_unique($joins));
        }
        
        // Valider et sécuriser le tri
        $allowedSortFields = ['name', 'altitude', 'access_time', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'name';
        
        // Vérification directe du nombre de secteurs dans la table
        $checkSql = "SELECT COUNT(*) as count FROM " . static::$table;
        $checkResult = $db->fetchOne($checkSql);
        $totalInTable = (int) ($checkResult['count'] ?? 0);
        
        // Si aucun secteur dans la table, retourner un paginateur vide
        if ($totalInTable === 0) {
            return new \TopoclimbCH\Core\Pagination\Paginator(
                [],
                0,
                $page,
                $perPage
            );
        }
        
        // Construire la requête de base pour le comptage
        $countSql = "SELECT COUNT(DISTINCT {$tablePrefix}.id) as total 
                    FROM " . static::$table . " {$tablePrefix}
                    {$joinClause}
                    {$whereClause}";
        
        // Exécuter la requête de comptage
        $countResult = $db->fetchOne($countSql, $whereParams);
        $total = (int) ($countResult['total'] ?? 0);
        
        // Si aucun résultat avec les filtres, retourner un paginateur vide
        if ($total === 0) {
            return new \TopoclimbCH\Core\Pagination\Paginator(
                [],
                0,
                $page,
                $perPage
            );
        }
        
        // Calculer l'offset pour la pagination
        $offset = ($page - 1) * $perPage;
        
        // Requête simplifiée pour tester
        $sql = "SELECT {$tablePrefix}.*, r.name as region_name 
                FROM " . static::$table . " {$tablePrefix}
                LEFT JOIN climbing_regions r ON {$tablePrefix}.region_id = r.id
                {$joinClause}
                {$whereClause}
                ORDER BY {$tablePrefix}.{$sortBy} {$sortDir}
                LIMIT {$perPage} OFFSET {$offset}";
        
        // Exécuter la requête principale
        $items = $db->fetchAll($sql, $whereParams);
        error_log("SQL Count: " . $countSql . " | Params: " . json_encode($whereParams));
        error_log("SQL Items: " . $sql . " | Results count: " . count($items));
        // Créer et retourner un objet Paginator
        return new \TopoclimbCH\Core\Pagination\Paginator(
            $items,
            $total,
            $page,
            $perPage
        );
    }    
    /**
     * Récupère tous les secteurs d'une région
     *
     * @param int $regionId
     * @param bool $activeOnly Retourner uniquement les secteurs actifs
     * @return array
     */
    public static function getAllByRegion(int $regionId, bool $activeOnly = true): array
    {
        $sql = "SELECT s.*, r.name as region_name
                FROM " . static::$table . " s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.region_id = ?";
                
        if ($activeOnly) {
            $sql .= " AND s.active = 1";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        return self::getConnection()->fetchAll($sql, [$regionId]);
    }
    
    /**
     * Récupère tous les secteurs d'un site/book
     *
     * @param int $bookId
     * @param bool $activeOnly Retourner uniquement les secteurs actifs
     * @return array
     */
    public static function getAllByBook(int $bookId, bool $activeOnly = true): array
    {
        $sql = "SELECT s.*, r.name as region_name
                FROM " . static::$table . " s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.book_id = ?";
                
        if ($activeOnly) {
            $sql .= " AND s.active = 1";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        return self::getConnection()->fetchAll($sql, [$bookId]);
    }
    
    /**
     * Recherche de secteurs par mots-clés
     *
     * @param string $keyword Terme de recherche
     * @param int $limit Limite de résultats
     * @return array
     */
    public static function search(string $keyword, int $limit = 10): array
    {
        $keyword = '%' . trim($keyword) . '%';
        
        $sql = "SELECT s.*, r.name as region_name
                FROM " . static::$table . " s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.active = 1 AND (
                    s.name LIKE ? OR 
                    s.description LIKE ? OR
                    s.code LIKE ?
                )
                ORDER BY s.name ASC
                LIMIT ?";
                
        return self::getConnection()->fetchAll($sql, [$keyword, $keyword, $keyword, $limit]);
    }
    
    /**
     * Récupère les secteurs recommandés pour un mois donné
     *
     * @param int $monthId ID du mois (1-12)
     * @param string $quality Qualité minimale (excellent, good, average)
     * @param int $limit Limite de résultats
     * @return array
     */
    public static function getRecommendedForMonth(int $monthId, string $quality = 'good', int $limit = 10): array
    {
        $validQualities = ['excellent', 'good', 'average'];
        $quality = in_array($quality, $validQualities) ? $quality : 'good';
        
        // Construire la condition de qualité
        $qualityCondition = "";
        if ($quality === 'excellent') {
            $qualityCondition = "sm.quality = 'excellent'";
        } elseif ($quality === 'good') {
            $qualityCondition = "sm.quality IN ('excellent', 'good')";
        } else {
            $qualityCondition = "sm.quality IN ('excellent', 'good', 'average')";
        }
        
        $sql = "SELECT s.*, r.name as region_name, sm.quality
                FROM " . static::$table . " s
                JOIN climbing_sector_months sm ON s.id = sm.sector_id
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.active = 1 AND sm.month_id = ? AND {$qualityCondition}
                ORDER BY FIELD(sm.quality, 'excellent', 'good', 'average'), s.name ASC
                LIMIT ?";
                
        return self::getConnection()->fetchAll($sql, [$monthId, $limit]);
    }
    /**
     * Récupère les statistiques d'un secteur
     *
     * @param int $sectorId
     * @return array
     */
    public static function getStats(int $sectorId): array
    {
        // Obtenir l'instance de Database
        $db = \TopoclimbCH\Core\Database::getInstance();
        
        // Compter les voies
        $routesCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ? AND active = 1",
            [$sectorId]
        );
        
        // Récupérer la difficulté min et max
        $difficultyStats = $db->fetchOne(
            "SELECT MIN(numerical_value) as min_difficulty, MAX(numerical_value) as max_difficulty
            FROM climbing_routes r
            JOIN climbing_difficulty_grades g ON r.difficulty = g.value AND r.difficulty_system_id = g.system_id
            WHERE r.sector_id = ? AND r.active = 1",
            [$sectorId]
        );
        
        // Compter les médias
        $mediaCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_media_relationships 
            WHERE entity_type = 'sector' AND entity_id = ?",
            [$sectorId]
        );
        
        return [
            'routes_count' => (int) ($routesCount['count'] ?? 0),
            'min_difficulty' => $difficultyStats['min_difficulty'] ?? null,
            'max_difficulty' => $difficultyStats['max_difficulty'] ?? null,
            'media_count' => (int) ($mediaCount['count'] ?? 0)
        ];
    }
    
    /**
     * Recherche les secteurs proches géographiquement
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param float $radiusKm Rayon de recherche en km
     * @param int $limit Nombre maximum de résultats
     * @return array
     */
    public static function findNearby(float $lat, float $lng, float $radiusKm = 10.0, int $limit = 10): array
    {
        // Formule Haversine pour calculer la distance
        $sql = "SELECT s.*, r.name as region_name,
               (6371 * acos(cos(radians(?)) * cos(radians(s.coordinates_lat)) * 
                cos(radians(s.coordinates_lng) - radians(?)) + 
                sin(radians(?)) * sin(radians(s.coordinates_lat)))) AS distance
                FROM " . static::$table . " s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.active = 1 
                AND s.coordinates_lat IS NOT NULL 
                AND s.coordinates_lng IS NOT NULL
                HAVING distance < ?
                ORDER BY distance ASC
                LIMIT ?";
        
        return self::getConnection()->fetchAll($sql, [$lat, $lng, $lat, $radiusKm, $limit]);
    }
    
    /**
     * Récupère les secteurs similaires basés sur divers critères
     *
     * @param int $sectorId
     * @param int $limit
     * @return array
     */
    public static function getSimilarSectors(int $sectorId, int $limit = 5): array
    {
        $sector = self::find($sectorId);
        
        if (!$sector) {
            return [];
        }
        
        // Base SQL query
        $sql = "SELECT s.*, r.name as region_name
                FROM " . static::$table . " s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.id != ? AND s.active = 1";
        
        $params = [$sectorId];
        
        // Filter by region if available
        if ($sector->region_id) {
            $sql .= " AND s.region_id = ?";
            $params[] = $sector->region_id;
        }
        
        // Filter by altitude range if available
        if ($sector->altitude) {
            $minAlt = max(0, $sector->altitude - 300);
            $maxAlt = $sector->altitude + 300;
            $sql .= " AND (s.altitude BETWEEN ? AND ?)";
            $params[] = $minAlt;
            $params[] = $maxAlt;
        }
        
        $sql .= " ORDER BY s.name ASC LIMIT ?";
        $params[] = $limit;
        
        return self::getConnection()->fetchAll($sql, $params);
    }
    
    /**
     * Génère une slug URL convivial à partir du nom et du code
     * 
     * @return string
     */
    public function getSlugAttribute(): string
    {
        $name = $this->attributes['name'] ?? '';
        $code = $this->attributes['code'] ?? '';
        
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        
        return $slug . '-' . $code;
    }
}