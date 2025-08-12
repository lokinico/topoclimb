<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Pagination\Paginator;

class RouteController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->db = $db;
    }

    /**
     * Affichage de la liste des voies
     */
    public function index(Request $request): Response
    {
        try {
            // Validation et nettoyage des filtres
            $filters = $this->validateAndSanitizeFilters($request);

            // Récupération sécurisée des données
            $data = $this->executeInTransaction(function () use ($filters) {
                return $this->getRoutesData($filters);
            });

            // Log de l'action
            $this->logAction('view_routes_list', ['filters' => $filters]);
            
            return $this->render('routes/index', $data);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement des voies');

            return $this->render('routes/index', [
                'routes' => [],
                'sectors' => [],
                'regions' => [],
                'filters' => [],
                'stats' => ['total_routes' => 0, 'avg_difficulty' => null],
                'paginator' => null,
                'error' => 'Impossible de charger les voies actuellement.'
            ]);
        }
    }

    /**
     * Validation et nettoyage des filtres
     */
    private function validateAndSanitizeFilters(Request $request): array
    {
        $filters = [
            'sector_id' => $request->query->get('sector_id', ''),
            'region_id' => $request->query->get('region_id', ''),
            'difficulty_min' => $request->query->get('difficulty_min', ''),
            'difficulty_max' => $request->query->get('difficulty_max', ''),
            'length_min' => $request->query->get('length_min', ''),
            'length_max' => $request->query->get('length_max', ''),
            'search' => $request->query->get('search', ''),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc'),
            'page' => $request->query->get('page', 1),
            'per_page' => $request->query->get('per_page', 15)
        ];

        // Validation des paramètres numériques
        if ($filters['sector_id'] && !is_numeric($filters['sector_id'])) {
            $filters['sector_id'] = '';
        }
        if ($filters['region_id'] && !is_numeric($filters['region_id'])) {
            $filters['region_id'] = '';
        }
        if ($filters['length_min'] && !is_numeric($filters['length_min'])) {
            $filters['length_min'] = '';
        }
        if ($filters['length_max'] && !is_numeric($filters['length_max'])) {
            $filters['length_max'] = '';
        }

        // Validation de la pagination
        $filters['page'] = max(1, (int)$filters['page']);
        $filters['per_page'] = Paginator::validatePerPage((int)$filters['per_page']);

        // Limiter la recherche textuelle
        if (strlen($filters['search']) > 100) {
            $filters['search'] = substr($filters['search'], 0, 100);
        }

        // Valider les colonnes de tri autorisées
        $allowedSorts = ['name', 'difficulty', 'length', 'created_at', 'sector_name'];
        if (!in_array($filters['sort'], $allowedSorts)) {
            $filters['sort'] = 'name';
        }
        
        // Mapper les colonnes de tri vers les colonnes avec préfixes de table
        $sortMapping = [
            'name' => 'r.name',
            'difficulty' => 'r.difficulty',
            'length' => 'r.length',
            'created_at' => 'r.created_at',
            'sector_name' => 's.name'
        ];
        $filters['sort'] = $sortMapping[$filters['sort']];

        // Valider l'ordre de tri
        if (!in_array(strtolower($filters['order']), ['asc', 'desc'])) {
            $filters['order'] = 'asc';
        }

        $cleanFilters = array_filter($filters, fn($value) => $value !== '' && $value !== null);
        
        // Assurer des valeurs par défaut pour le tri et la pagination
        if (!isset($cleanFilters['sort'])) {
            $cleanFilters['sort'] = 'r.name';
        }
        if (!isset($cleanFilters['order'])) {
            $cleanFilters['order'] = 'asc';
        }
        if (!isset($cleanFilters['page'])) {
            $cleanFilters['page'] = 1;
        }
        if (!isset($cleanFilters['per_page'])) {
            $cleanFilters['per_page'] = 15;
        }
        
        return $cleanFilters;
    }

    /**
     * Récupération sécurisée des données voies avec pagination
     */
    private function getRoutesData(array $filters): array
    {
        // Construction sécurisée de la requête de comptage
        $countSql = "SELECT COUNT(*) as total
                     FROM climbing_routes r 
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                     LEFT JOIN climbing_regions re ON s.region_id = re.id
                     WHERE 1=1";
        $params = [];

        // Conditions de filtrage
        if (isset($filters['sector_id'])) {
            $countSql .= " AND r.sector_id = ?";
            $params[] = (int)$filters['sector_id'];
        }

        if (isset($filters['region_id'])) {
            $countSql .= " AND re.id = ?";
            $params[] = (int)$filters['region_id'];
        }

        if (isset($filters['difficulty_min'])) {
            $countSql .= " AND r.difficulty >= ?";
            $params[] = $filters['difficulty_min'];
        }

        if (isset($filters['difficulty_max'])) {
            $countSql .= " AND r.difficulty <= ?";
            $params[] = $filters['difficulty_max'];
        }

        if (isset($filters['length_min'])) {
            $countSql .= " AND r.length >= ?";
            $params[] = (int)$filters['length_min'];
        }

        if (isset($filters['length_max'])) {
            $countSql .= " AND r.length <= ?";
            $params[] = (int)$filters['length_max'];
        }

        if (isset($filters['search'])) {
            $countSql .= " AND r.name LIKE ?";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
        }

        // Compter le total
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // Construction de la requête principale (colonnes minimales compatibles)
        $sql = "SELECT r.id, r.name, r.difficulty, r.length, r.created_at,
                       s.name as sector_name, s.id as sector_id,
                       re.name as region_name, re.id as region_id
                FROM climbing_routes r 
                LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                LEFT JOIN climbing_regions re ON s.region_id = re.id
                WHERE 1=1";

        // Même conditions de filtrage
        $mainParams = $params;

        $sql .= " ORDER BY " . $filters['sort'] . " " . strtoupper($filters['order']);

        // Calcul de l'offset et limite
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $sql .= " LIMIT ? OFFSET ?";
        $mainParams[] = $filters['per_page'];
        $mainParams[] = $offset;

        $routes = $this->db->fetchAll($sql, $mainParams);

        // Récupération des données pour les filtres
        $sectors = $this->db->fetchAll(
            "SELECT * FROM climbing_sectors WHERE 1=1 ORDER BY name ASC"
        );

        $regions = $this->db->fetchAll(
            "SELECT * FROM climbing_regions WHERE 1=1 ORDER BY name ASC"
        );

        // Calcul des statistiques
        $stats = $this->calculateStats();

        // Création de la pagination
        $queryParams = array_filter($filters, function($key) {
            return !in_array($key, ['page', 'per_page']);
        }, ARRAY_FILTER_USE_KEY);

        $paginator = new Paginator($routes, $total, $filters['per_page'], $filters['page'], $queryParams);

        return [
            'routes' => $routes,
            'sectors' => $sectors,
            'regions' => $regions,
            'filters' => $filters,
            'stats' => $stats,
            'paginator' => $paginator
        ];
    }

    /**
     * Affichage sécurisé d'une voie avec détails
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            if (!$id || !is_numeric($id)) {
                $this->flash('error', 'ID de voie invalide');
                return $this->redirect('/routes');
            }
            
            $id = (int) $id;

            // Récupération des données
            $data = $this->getRouteDetails($id);

            return $this->render('routes/show', $data);
        } catch (\Exception $e) {
            error_log("RouteController::show - Erreur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement de la voie');
            return $this->redirect('/routes');
        }
    }

    /**
     * Récupération des détails d'une voie
     */
    private function getRouteDetails(int $id): array
    {
        // Récupération de base de la voie (colonnes explicites compatibles)
        $route = $this->db->fetchOne(
            "SELECT r.id, r.name, r.difficulty, r.length, r.created_at, r.sector_id,
                    s.name as sector_name, s.id as sector_id_alias,
                    re.name as region_name, re.id as region_id
             FROM climbing_routes r 
             LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
             LEFT JOIN climbing_regions re ON s.region_id = re.id
             WHERE r.id = ?",
            [$id]
        );

        $this->requireEntity($route, 'Voie non trouvée');

        // Récupération des autres voies du même secteur (colonnes compatibles)
        $relatedRoutes = [];
        if ($route['sector_id']) {
            $relatedRoutes = $this->db->fetchAll(
                "SELECT r.id, r.name, r.difficulty, r.length
                 FROM climbing_routes r 
                 WHERE r.sector_id = ? AND r.id != ?
                 ORDER BY r.name ASC 
                 LIMIT 20",
                [$route['sector_id'], $id]
            );
        }

        // Récupération des médias associés à la voie
        $media = [];
        try {
            $media = $this->db->fetchAll(
                "SELECT m.id, m.title, m.file_path, m.file_type, m.created_at
                 FROM climbing_media m 
                 WHERE m.entity_type = 'route' AND m.entity_id = ? AND m.active = 1
                 ORDER BY m.display_order ASC, m.created_at ASC",
                [$id]
            );
        } catch (\Exception $e) {
            error_log("Erreur récupération médias route {$id}: " . $e->getMessage());
        }

        return [
            'title' => $route['name'],
            'route' => $route,
            'related_routes' => $relatedRoutes,
            'media' => $media
        ];
    }

    /**
     * API publique avec rate limiting
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $sectorId = $request->query->get('sector_id', '');
            $regionId = $request->query->get('region_id', '');
            $search = $request->query->get('search', '');
            $limit = min((int)$request->query->get('limit', 100), 500);

            // Validation des paramètres
            if ($sectorId && !is_numeric($sectorId)) {
                return new JsonResponse(['error' => 'ID secteur invalide'], 400);
            }

            if ($regionId && !is_numeric($regionId)) {
                return new JsonResponse(['error' => 'ID région invalide'], 400);
            }

            $sql = "SELECT r.id, r.name, r.difficulty, r.length, 
                           s.name as sector_name, re.name as region_name
                    FROM climbing_routes r 
                    LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                    LEFT JOIN climbing_regions re ON s.region_id = re.id
                    WHERE 1=1";
            $params = [];

            if ($sectorId) {
                $sql .= " AND r.sector_id = ?";
                $params[] = (int)$sectorId;
            }

            if ($regionId) {
                $sql .= " AND re.id = ?";
                $params[] = (int)$regionId;
            }

            if ($search) {
                $search = trim(strip_tags($search));
                if (strlen($search) > 100) {
                    $search = substr($search, 0, 100);
                }
                $sql .= " AND r.name LIKE ?";
                $params[] = '%' . $search . '%';
            }

            $sql .= " ORDER BY r.name ASC LIMIT ?";
            $params[] = $limit;

            $routes = $this->db->fetchAll($sql, $params);

            return new JsonResponse([
                'success' => true,
                'data' => $routes,
                'count' => count($routes),
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur API');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * API Show - détails d'une voie spécifique
     */
    public function apiShow(Request $request): JsonResponse
    {
        try {
            $id = $this->validateId($request->attributes->get('id'), 'ID de voie');

            $route = $this->db->fetchOne(
                "SELECT r.id, r.name, r.difficulty, r.length, r.created_at, r.sector_id,
                        s.name as sector_name,
                        re.name as region_name, re.id as region_id
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                 LEFT JOIN climbing_regions re ON s.region_id = re.id
                 WHERE r.id = ?",
                [$id]
            );

            if (!$route) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Voie non trouvée'
                ], 404);
            }

            // Formatage sécurisé des données (colonnes compatibles)
            $data = [
                'id' => (int)$route['id'],
                'name' => $route['name'],
                'difficulty' => $route['difficulty'],
                'length' => $route['length'] ? (int)$route['length'] : null,
                'sector' => [
                    'id' => (int)$route['sector_id'],
                    'name' => $route['sector_name']
                ],
                'region' => [
                    'id' => (int)$route['region_id'],
                    'name' => $route['region_name']
                ],
                'created_at' => $route['created_at']
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur récupération voie');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * Calcul sécurisé des statistiques générales
     */
    private function calculateStats(): array
    {
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_routes,
                    AVG(length) as avg_length,
                    MIN(length) as min_length,
                    MAX(length) as max_length
                 FROM climbing_routes WHERE length IS NOT NULL"
            );

            return [
                'total_routes' => (int)($stats['total_routes'] ?? 0),
                'avg_length' => $stats['avg_length'] ? round($stats['avg_length'], 1) : null,
                'min_length' => (int)($stats['min_length'] ?? 0),
                'max_length' => (int)($stats['max_length'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log('Erreur calcul stats routes: ' . $e->getMessage());
            return ['total_routes' => 0, 'avg_length' => null, 'min_length' => 0, 'max_length' => 0];
        }
    }
}