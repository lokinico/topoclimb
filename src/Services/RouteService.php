<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\DifficultySystem;
use TopoclimbCH\Models\UserAscent;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Pagination;

class RouteService
{
    protected Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Récupère toutes les voies
     */
    public function getAllRoutes(): array
    {
        return Route::all();
    }
    
    /**
     * Récupère les voies paginées avec filtres optionnels
     */
    public function getPaginatedRoutes(array $filters = [], int $page = 1, int $perPage = 30): Pagination
    {
        $query = Route::query();
        
        // Applique les filtres
        if (!empty($filters['sector_id'])) {
            $query->where('sector_id', $filters['sector_id']);
        }
        
        if (!empty($filters['name'])) {
            $query->where('name', 'LIKE', "%{$filters['name']}%");
        }
        
        if (!empty($filters['difficulty_min'])) {
            $query->whereNumericalDifficultyAtLeast($filters['difficulty_min']);
        }
        
        if (!empty($filters['difficulty_max'])) {
            $query->whereNumericalDifficultyAtMost($filters['difficulty_max']);
        }
        
        if (!empty($filters['style'])) {
            $query->where('style', $filters['style']);
        }
        
        if (!empty($filters['beauty'])) {
            $query->where('beauty', '>=', $filters['beauty']);
        }
        
        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        
        // Tri les résultats
        $orderBy = $filters['order_by'] ?? 'number';
        $orderDir = $filters['order_dir'] ?? 'asc';
        $query->orderBy($orderBy, $orderDir);
        
        // Pagination
        return $query->paginate($perPage, $page);
    }
    
    /**
     * Récupère une voie par ID
     */
    public function getRoute(int $id): ?Route
    {
        return Route::find($id);
    }
    
    /**
     * Récupère une voie avec ses relations
     */
    public function getRouteWithRelations(int $id, array $relations = []): ?Route
    {
        $route = Route::find($id);
        
        if (!$route) {
            return null;
        }
        
        foreach ($relations as $relation) {
            $route->load($relation);
        }
        
        return $route;
    }
    
    /**
     * Récupère les voies similaires à une voie donnée
     */
    public function getSimilarRoutes(Route $route, int $limit = 5): array
    {
        // Récupère les voies du même secteur mais pas celle en cours
        $conditions = [
            'sector_id' => $route->sector_id,
            'active' => 1
        ];
        
        $sql = "SELECT r.* FROM " . Route::getTable() . " r
                WHERE r.sector_id = :sectorId 
                AND r.id != :routeId
                AND r.active = 1
                ORDER BY ABS(r.difficulty_system_id - :diffSystem) ASC, 
                        ABS(r.difficulty - :diff) ASC
                LIMIT :limit";
        
        $params = [
            ':sectorId' => $route->sector_id,
            ':routeId' => $route->id,
            ':diffSystem' => $route->difficulty_system_id,
            ':diff' => $route->difficulty,
            ':limit' => $limit
        ];
        
        try {
            $db = Database::getInstance();
            $statement = $db->getConnection()->prepare($sql);
            $statement->execute($params);
            $data = $statement->fetchAll(PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($data as $item) {
                $models[] = new Route($item);
            }
            
            return $models;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la recherche de routes similaires: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Récupère les statistiques d'ascension pour une voie
     */
    public function getAscentStatistics(Route $route): array
    {
        $ascents = UserAscent::where('route_id', $route->id)->get();
        
        if (empty($ascents)) {
            return [
                'count' => 0,
                'ascent_types' => [],
                'quality_ratings' => [],
                'avg_quality' => 0,
                'difficulty_comments' => []
            ];
        }
        
        // Compte les ascensions
        $count = count($ascents);
        
        // Groupe par type d'ascension
        $ascentTypes = [
            'flash' => 0,
            'onsight' => 0,
            'redpoint' => 0,
            'attempt' => 0
        ];
        
        // Groupe par note de qualité
        $qualityRatings = [
            0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0
        ];
        
        // Groupe par commentaire de difficulté
        $difficultyComments = [
            'easy' => 0,
            'accurate' => 0,
            'hard' => 0
        ];
        
        $totalQuality = 0;
        $qualityCount = 0;
        
        foreach ($ascents as $ascent) {
            // Type d'ascension
            if (isset($ascentTypes[$ascent->ascent_type])) {
                $ascentTypes[$ascent->ascent_type]++;
            }
            
            // Note de qualité
            if ($ascent->quality_rating !== null) {
                $qualityRatings[$ascent->quality_rating]++;
                $totalQuality += $ascent->quality_rating;
                $qualityCount++;
            }
            
            // Commentaire de difficulté
            if (!empty($ascent->difficulty_comment) && isset($difficultyComments[$ascent->difficulty_comment])) {
                $difficultyComments[$ascent->difficulty_comment]++;
            }
        }
        
        // Calcule la note moyenne
        $avgQuality = $qualityCount > 0 ? round($totalQuality / $qualityCount, 1) : 0;
        
        return [
            'count' => $count,
            'ascent_types' => $ascentTypes,
            'quality_ratings' => $qualityRatings,
            'avg_quality' => $avgQuality,
            'difficulty_comments' => $difficultyComments
        ];
    }
    
    /**
     * Crée une nouvelle voie
     */
    public function createRoute(array $data): Route
    {
        // Définit le numéro de la voie si non fourni
        if (empty($data['number'])) {
            $data['number'] = $this->getNextRouteNumber($data['sector_id']);
        }
        
        // Définit created_by si non défini
        if (!isset($data['created_by']) && auth()->check()) {
            $data['created_by'] = auth()->id();
        }
        
        // Crée la voie
        $route = new Route();
        $route->fill($data);
        $route->save();
        
        return $route;
    }
    
    /**
     * Met à jour une voie existante
     */
    public function updateRoute(Route $route, array $data): Route
    {
        // Définit updated_by si non défini
        if (!isset($data['updated_by']) && auth()->check()) {
            $data['updated_by'] = auth()->id();
        }
        
        // Met à jour la voie
        $route->fill($data);
        $route->save();
        
        return $route;
    }
    
    /**
     * Supprime une voie
     */
    public function deleteRoute(Route $route): bool
    {
        return $route->delete();
    }
    
    /**
     * Récupère tous les systèmes de difficulté
     */
    public function getDifficultySystems(): array
    {
        return DifficultySystem::orderBy('name')->get();
    }
    
    /**
     * Récupère le prochain numéro de voie pour un secteur
     */
    protected function getNextRouteNumber(int $sectorId): int
    {
        $maxNumber = Route::where('sector_id', $sectorId)
            ->orderBy('number', 'desc')
            ->value('number');
        
        return ($maxNumber ?? 0) + 1;
    }
    
    /**
     * Enregistre une ascension
     */
    public function recordAscent(array $data): UserAscent
    {
        // Vérifie si l'utilisateur a déjà une ascension pour cette voie
        $existingAscent = UserAscent::where('user_id', $data['user_id'])
            ->where('route_id', $data['route_id'])
            ->first();
        
        if ($existingAscent) {
            // Met à jour l'ascension existante
            $existingAscent->fill($data);
            $existingAscent->save();
            
            return $existingAscent;
        }
        
        // Crée une nouvelle ascension
        $route = Route::find($data['route_id']);
        
        // Ajoute des données supplémentaires
        $data['route_name'] = $route->name;
        $data['difficulty'] = $route->difficulty;
        $data['topo_item'] = $route->sector->code;
        
        $ascent = new UserAscent();
        $ascent->fill($data);
        $ascent->save();
        
        return $ascent;
    }
}