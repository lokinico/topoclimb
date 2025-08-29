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
use TopoclimbCH\Services\MediaUploadService;

class SectorController extends BaseController
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
     * Affichage de la liste des secteurs avec pagination
     */
    public function index(Request $request): Response
    {
        try {
            // Validation et nettoyage des filtres
            $filters = $this->validateAndSanitizeFilters($request);

            // Récupération sécurisée des données
            $data = $this->executeInTransaction(function () use ($filters) {
                return $this->getSectorsData($filters);
            });

            // Log de l'action
            $this->logAction('view_sectors_list', ['filters' => $filters]);
            
            return $this->render('sectors/index', $data);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement des secteurs');

            return $this->render('sectors/index', [
                'sectors' => [],
                'regions' => [],
                'sites' => [],
                'filters' => [],
                'stats' => ['total_sectors' => 0, 'total_routes' => 0],
                'paginator' => null,
                'error' => 'Impossible de charger les secteurs actuellement.'
            ]);
        }
    }

    /**
     * Validation et nettoyage des filtres
     */
    private function validateAndSanitizeFilters(Request $request): array
    {
        $filters = [
            'region_id' => $request->query->get('region_id', ''),
            'site_id' => $request->query->get('site_id', ''),
            'altitude_min' => $request->query->get('altitude_min', ''),
            'altitude_max' => $request->query->get('altitude_max', ''),
            'exposition' => $request->query->get('exposition', ''),
            'best_months' => $request->query->get('best_months', ''),
            'difficulty_range' => $request->query->get('difficulty_range', ''),
            'search' => $request->query->get('search', ''),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc'),
            'page' => $request->query->get('page', 1),
            'per_page' => $request->query->get('per_page', 15)
        ];

        // Validation des paramètres numériques
        if ($filters['region_id'] && !is_numeric($filters['region_id'])) {
            $filters['region_id'] = '';
        }
        if ($filters['site_id'] && !is_numeric($filters['site_id'])) {
            $filters['site_id'] = '';
        }
        if ($filters['altitude_min'] && !is_numeric($filters['altitude_min'])) {
            $filters['altitude_min'] = '';
        }
        if ($filters['altitude_max'] && !is_numeric($filters['altitude_max'])) {
            $filters['altitude_max'] = '';
        }
        
        // Validation exposition (N, NE, E, SE, S, SW, W, NW)
        $validExpositions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        if ($filters['exposition'] && !in_array($filters['exposition'], $validExpositions)) {
            $filters['exposition'] = '';
        }
        
        // Validation mois (format: "1,2,3" ou "12")
        if ($filters['best_months'] && !preg_match('/^[0-9,]+$/', $filters['best_months'])) {
            $filters['best_months'] = '';
        }
        
        // Validation difficulté (format: "3,4" ou "5")
        if ($filters['difficulty_range'] && !preg_match('/^[0-9,]+$/', $filters['difficulty_range'])) {
            $filters['difficulty_range'] = '';
        }

        // Validation de la pagination
        $filters['page'] = max(1, (int)$filters['page']);
        $filters['per_page'] = Paginator::validatePerPage((int)$filters['per_page']);

        // Limiter la recherche textuelle
        if (strlen($filters['search']) > 100) {
            $filters['search'] = substr($filters['search'], 0, 100);
        }

        // Valider les colonnes de tri autorisées
        $allowedSorts = ['name', 'altitude', 'region_name', 'site_name', 'created_at'];
        if (!in_array($filters['sort'], $allowedSorts)) {
            $filters['sort'] = 'name';
        }
        
        // Mapper les colonnes de tri vers les colonnes avec préfixes de table
        $sortMapping = [
            'name' => 's.name',
            'altitude' => 's.altitude',
            'region_name' => 'r.name',
            'site_name' => 'si.name',
            'created_at' => 's.created_at'
        ];
        $filters['sort'] = $sortMapping[$filters['sort']];

        // Valider l'ordre de tri
        if (!in_array(strtolower($filters['order']), ['asc', 'desc'])) {
            $filters['order'] = 'asc';
        }

        $cleanFilters = array_filter($filters, fn($value) => $value !== '' && $value !== null);
        
        // Assurer des valeurs par défaut pour le tri et la pagination
        if (!isset($cleanFilters['sort'])) {
            $cleanFilters['sort'] = 's.name';
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
     * Récupération sécurisée des données secteurs avec pagination
     */
    private function getSectorsData(array $filters): array
    {
        // Construction sécurisée de la requête de comptage
        $countSql = "SELECT COUNT(*) as total
                     FROM climbing_sectors s 
                     LEFT JOIN climbing_regions r ON s.region_id = r.id 
                     LEFT JOIN climbing_sites si ON s.site_id = si.id
                     WHERE s.active = 1";
        $params = [];

        // Conditions de filtrage
        if (isset($filters['region_id'])) {
            $countSql .= " AND s.region_id = ?";
            $params[] = (int)$filters['region_id'];
        }

        if (isset($filters['site_id'])) {
            $countSql .= " AND s.site_id = ?";
            $params[] = (int)$filters['site_id'];
        }

        if (isset($filters['altitude_min'])) {
            $countSql .= " AND s.altitude >= ?";
            $params[] = (int)$filters['altitude_min'];
        }

        if (isset($filters['altitude_max'])) {
            $countSql .= " AND s.altitude <= ?";
            $params[] = (int)$filters['altitude_max'];
        }

        if (isset($filters['search'])) {
            $countSql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Compter le total
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // Construction de la requête principale avec comptage des voies
        $sql = "SELECT s.id, s.name, s.description, s.altitude, s.created_at,
                       r.name as region_name, si.name as site_name,
                       COUNT(routes.id) as routes_count
                FROM climbing_sectors s 
                LEFT JOIN climbing_regions r ON s.region_id = r.id 
                LEFT JOIN climbing_sites si ON s.site_id = si.id
                LEFT JOIN climbing_routes routes ON s.id = routes.sector_id
                WHERE s.active = 1";

        // Reconstruire les mêmes conditions de filtrage pour la requête principale
        $mainParams = [];
        
        if (isset($filters['region_id'])) {
            $sql .= " AND s.region_id = ?";
            $mainParams[] = (int)$filters['region_id'];
        }

        if (isset($filters['site_id'])) {
            $sql .= " AND s.site_id = ?";
            $mainParams[] = (int)$filters['site_id'];
        }

        if (isset($filters['altitude_min'])) {
            $sql .= " AND s.altitude >= ?";
            $mainParams[] = (int)$filters['altitude_min'];
        }

        if (isset($filters['altitude_max'])) {
            $sql .= " AND s.altitude <= ?";
            $mainParams[] = (int)$filters['altitude_max'];
        }

        if (isset($filters['search'])) {
            $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $mainParams[] = $searchTerm;
            $mainParams[] = $searchTerm;
        }

        $sql .= " GROUP BY s.id, s.name, s.description, s.altitude, s.created_at, r.name, si.name";
        $sql .= " ORDER BY " . $filters['sort'] . " " . strtoupper($filters['order']);

        // Calcul de l'offset et limite
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $sql .= " LIMIT ? OFFSET ?";
        $mainParams[] = $filters['per_page'];
        $mainParams[] = $offset;

        $sectors = $this->db->fetchAll($sql, $mainParams);

        // Récupération des données pour les filtres
        $regions = $this->db->fetchAll("SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
        $sites = $this->db->fetchAll("SELECT * FROM climbing_sites WHERE active = 1 ORDER BY name ASC");

        // Calcul des statistiques
        $stats = $this->calculateStats();

        // Création de la pagination
        $queryParams = array_filter($filters, function($key) {
            return !in_array($key, ['page', 'per_page']);
        }, ARRAY_FILTER_USE_KEY);

        $paginator = new Paginator($sectors, $total, $filters['per_page'], $filters['page'], $queryParams);

        return [
            'sectors' => $sectors,
            'regions' => $regions,
            'sites' => $sites,
            'filters' => $filters,
            'stats' => $stats,
            'paginator' => $paginator
        ];
    }

    /**
     * Calcul sécurisé des statistiques générales
     */
    private function calculateStats(): array
    {
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_sectors,
                    AVG(altitude) as avg_altitude,
                    MIN(altitude) as min_altitude,
                    MAX(altitude) as max_altitude,
                    (SELECT COUNT(*) FROM climbing_routes r 
                     JOIN climbing_sectors s ON r.sector_id = s.id 
                     WHERE s.active = 1) as total_routes
                 FROM climbing_sectors WHERE active = 1"
            );

            return [
                'total_sectors' => (int)($stats['total_sectors'] ?? 0),
                'total_routes' => (int)($stats['total_routes'] ?? 0),
                'avg_altitude' => $stats['avg_altitude'] ? round($stats['avg_altitude']) : null,
                'min_altitude' => (int)($stats['min_altitude'] ?? 0),
                'max_altitude' => (int)($stats['max_altitude'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log('Erreur calcul stats sectors: ' . $e->getMessage());
            return ['total_sectors' => 0, 'total_routes' => 0, 'avg_altitude' => null, 'min_altitude' => 0, 'max_altitude' => 0];
        }
    }

    /**
     * Affichage d'un secteur individuel
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            if (!$id || !is_numeric($id)) {
                $this->flash('error', 'ID de secteur invalide');
                return $this->redirect('/sectors');
            }
            
            $id = (int) $id;

            // Récupération du secteur avec ses détails
            $sector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id,
                        si.name as site_name, si.id as site_id
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );

            if (!$sector) {
                $this->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }

            // Récupération des voies du secteur (colonnes minimales compatibles)
            $routes = $this->db->fetchAll(
                "SELECT r.id, r.name, r.difficulty, r.length, r.created_at
                 FROM climbing_routes r 
                 WHERE r.sector_id = ?
                 ORDER BY r.name ASC 
                 LIMIT 200",
                [$id]
            );

            // Récupération des médias associés au secteur
            $media = [];
            try {
                $media = $this->db->fetchAll(
                    "SELECT m.id, m.title, m.file_path, m.media_type, m.created_at
                     FROM climbing_media m 
                     JOIN climbing_media_relationships mr ON m.id = mr.media_id
                     WHERE mr.entity_type = 'sector' AND mr.entity_id = ? AND m.is_public = 1
                     ORDER BY mr.relationship_type, mr.sort_order ASC, m.created_at ASC",
                    [$id]
                );
            } catch (\Exception $e) {
                error_log("Erreur récupération médias secteur {$id}: " . $e->getMessage());
            }

            $stats = [
                'routes_count' => count($routes),
                'min_difficulty' => null,
                'max_difficulty' => null,
                'avg_length' => null
            ];

            // Calcul des statistiques
            if (!empty($routes)) {
                $difficulties = array_filter(array_column($routes, 'difficulty'));
                if (!empty($difficulties)) {
                    $stats['min_difficulty'] = min($difficulties);
                    $stats['max_difficulty'] = max($difficulties);
                }

                $lengths = array_filter(array_column($routes, 'length'));
                if (!empty($lengths)) {
                    $stats['avg_length'] = round(array_sum($lengths) / count($lengths), 1);
                }
            }

            return $this->render('sectors/show', [
                'title' => $sector['name'],
                'sector' => $sector,
                'routes' => $routes,
                'media' => $media,
                'stats' => $stats,
                'routes_count' => $stats['routes_count'],
                'min_difficulty' => $stats['min_difficulty'],
                'max_difficulty' => $stats['max_difficulty'],
                'avg_route_length' => $stats['avg_length'],
                'ascents_count' => 0, // TODO: Implémenter comptage ascensions
            ]);
        } catch (\Exception $e) {
            error_log("SectorController::show - Erreur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement du secteur');
            return $this->redirect('/sectors');
        }
    }

    /**
     * API simple
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.coordinates_lat, s.coordinates_lng, 
                        r.name as region_name
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.active = 1
                 ORDER BY s.name ASC 
                 LIMIT 100"
            );

            return new JsonResponse([
                'success' => true,
                'data' => $sectors,
                'count' => count($sectors)
            ]);
        } catch (\Exception $e) {
            error_log('Erreur API secteurs: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * API: Secteurs par site
     */
    public function apiBySite(Request $request): JsonResponse
    {
        $siteId = $request->attributes->get('site_id');
        
        if (!$siteId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'ID de site requis'
            ], 400);
        }

        try {
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.description, s.site_id, site.name as site_name, s.orientation,
                        s.coordinates_lat, s.coordinates_lng, s.altitude
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_sites site ON s.site_id = site.id
                 WHERE s.site_id = ? AND s.active = 1
                 ORDER BY s.name ASC",
                [(int)$siteId]
            );

            return new JsonResponse([
                'success' => true,
                'data' => $sectors,
                'count' => count($sectors)
            ]);
        } catch (\Exception $e) {
            error_log('Erreur API secteurs par site: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des secteurs'
            ], 500);
        }
    }

    /**
     * API Show - détails d'un secteur spécifique
     */
    public function apiShow(Request $request): JsonResponse
    {
        try {
            $id = $request->attributes->get('id');

            if (!$id || !is_numeric($id)) {
                return new JsonResponse(['error' => 'ID de secteur invalide'], 400);
            }

            $id = (int) $id;

            $sector = $this->db->fetchOne(
                "SELECT s.id, s.name, s.description, s.coordinates_lat, s.coordinates_lng, 
                        s.altitude, s.created_at,
                        r.name as region_name, r.id as region_id,
                        si.name as site_name, si.id as site_id
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );

            if (!$sector) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secteur non trouvé'
                ], 404);
            }

            // Récupérer les statistiques
            $routesCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ?",
                [$id]
            );

            // Formatage sécurisé des données
            $data = [
                'id' => (int)$sector['id'],
                'name' => $sector['name'],
                'description' => $sector['description'],
                'coordinates' => [
                    'lat' => $sector['coordinates_lat'] ? (float)$sector['coordinates_lat'] : null,
                    'lng' => $sector['coordinates_lng'] ? (float)$sector['coordinates_lng'] : null
                ],
                'altitude' => $sector['altitude'] ? (int)$sector['altitude'] : null,
                'region' => [
                    'id' => (int)$sector['region_id'],
                    'name' => $sector['region_name']
                ],
                'site' => [
                    'id' => (int)$sector['site_id'],
                    'name' => $sector['site_name']
                ],
                'stats' => [
                    'routes_count' => (int)($routesCount['count'] ?? 0)
                ],
                'created_at' => $sector['created_at']
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            error_log('Erreur récupération secteur: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * Page de création de secteur (version test sans authentification)
     */
    public function testCreate(Request $request): Response
    {
        try {
            // Récupérer les régions
            $regions = $this->db->fetchAll(
                "SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
            );

            // Récupérer les sites avec leurs régions
            $sites = $this->db->fetchAll(
                "SELECT s.*, r.name as region_name 
                 FROM climbing_sites s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.active = 1 
                 ORDER BY r.name ASC, s.name ASC"
            );

            // Récupérer les expositions (avec fallback si table n'existe pas)
            try {
                $expositions = $this->db->fetchAll(
                    "SELECT * FROM climbing_expositions ORDER BY name ASC"
                );
            } catch (\Exception $e) {
                // Fallback si table expositions n'existe pas
                $expositions = [
                    (object)['id' => 1, 'name' => 'Nord', 'code' => 'N'],
                    (object)['id' => 2, 'name' => 'Sud', 'code' => 'S'],
                    (object)['id' => 3, 'name' => 'Est', 'code' => 'E'],
                    (object)['id' => 4, 'name' => 'Ouest', 'code' => 'W']
                ];
            }

            return $this->render('sectors/form', [
                'sector' => (object)[],
                'regions' => $regions ?? [],
                'sites' => $sites ?? [],
                'exposures' => $expositions ?? [],
                'currentExposures' => [],
                'primaryExposure' => null,
                'media' => [],
                'csrf_token' => 'test-token-' . bin2hex(random_bytes(16)),
                'is_edit' => false,
                'is_test' => true
            ]);
        } catch (\Exception $e) {
            error_log('Erreur testCreate secteur: ' . $e->getMessage());
            return new Response('Formulaire secteur - Test (Erreur: ' . $e->getMessage() . ')', 500);
        }
    }

    /**
     * Enregistrement nouveau secteur
     */
    public function store(Request $request): Response
    {
        $this->requireAuth();
        $this->requireRole([0, 1, 2]);
        
        try {
            // Validation CSRF
            if (!$this->validateCsrfToken($request->request->get('csrf_token'))) {
                $this->flash('error', 'Token de sécurité invalide');
                return $this->redirect('/sectors/create');
            }
            
            // Récupération et validation des données
            $data = $this->validateSectorData($request);
            
            // Insertion en base
            $sectorId = $this->createSector($data);
            
            if ($sectorId) {
                // Gestion de l'upload d'image si présente
                $this->handleImageUpload($request, $sectorId);
                
                $this->flash('success', 'Secteur créé avec succès');
                return $this->redirect('/sectors/' . $sectorId);
            } else {
                throw new \Exception('Impossible de créer le secteur');
            }
            
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la création du secteur');
            return $this->redirect('/sectors/create');
        }
    }

    /**
     * Page de création de secteur
     */
    public function create(Request $request): Response
    {
        // Auth déjà gérée par AuthMiddleware dans la configuration de routes
        error_log("SectorController::create - Méthode appelée (auth déjà validée par middleware)");

        // Si site_id est fourni, rediriger vers la méthode spécialisée
        $siteId = $request->query->get('site_id');
        if ($siteId && is_numeric($siteId)) {
            error_log("SectorController::create - Redirection vers createFromSite pour site: " . $siteId);
            
            // Simuler la requête pour createFromSite
            $request->attributes->set('site_id', $siteId);
            return $this->createFromSite($request);
        }

        try {
            // Récupérer les régions
            $regions = $this->db->fetchAll(
                "SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
            );

            // Récupérer les sites avec leurs régions
            $sites = $this->db->fetchAll(
                "SELECT s.*, r.name as region_name 
                 FROM climbing_sites s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.active = 1 
                 ORDER BY r.name ASC, s.name ASC"
            );

            // Récupérer les expositions (avec fallback si table n'existe pas)
            try {
                $expositions = $this->db->fetchAll(
                    "SELECT * FROM climbing_expositions ORDER BY name ASC"
                );
            } catch (\Exception $e) {
                // Fallback si table expositions n'existe pas
                $expositions = [
                    (object)['id' => 1, 'name' => 'Nord', 'code' => 'N'],
                    (object)['id' => 2, 'name' => 'Sud', 'code' => 'S'],
                    (object)['id' => 3, 'name' => 'Est', 'code' => 'E'],
                    (object)['id' => 4, 'name' => 'Ouest', 'code' => 'W']
                ];
            }

            // Pré-sélection site si fourni
            $site_id = $request->query->get('site_id');
            
            return $this->render('sectors/form', [
                'sector' => (object)['site_id' => $site_id],
                'regions' => $regions,
                'sites' => $sites,
                'exposures' => $expositions ?? [],
                'currentExposures' => [],
                'primaryExposure' => null,
                'media' => [],
                'csrf_token' => $this->createCsrfToken(),
                'is_edit' => false
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire de création');
            return $this->redirect('/sectors');
        }
    }

    /**
     * Page de création de secteur depuis un site parent
     */
    public function createFromSite(Request $request): Response
    {
        // Auth déjà vérifiée par le middleware ou par la méthode appelante
        error_log("SectorController::createFromSite - Méthode appelée");
        
        try {
            $site_id = $request->attributes->get('site_id');
            
            if (!$site_id || !is_numeric($site_id)) {
                $this->flash('error', 'ID de site invalide');
                return $this->redirect('/sites');
            }
            
            // Vérifier que le site existe
            $site = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id 
                 FROM climbing_sites s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.id = ? AND s.active = 1",
                [$site_id]
            );
            
            if (!$site) {
                $this->flash('error', 'Site non trouvé');
                return $this->redirect('/sites');
            }
            
            // Récupérer toutes les régions et sites pour le formulaire
            $regions = $this->db->fetchAll(
                "SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
            );
            
            $sites = $this->db->fetchAll(
                "SELECT s.*, r.name as region_name 
                 FROM climbing_sites s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.active = 1 
                 ORDER BY r.name ASC, s.name ASC"
            );
            
            // Récupérer les expositions
            $expositions = [];
            try {
                $expositions = $this->db->fetchAll(
                    "SELECT DISTINCT exposure FROM climbing_sectors 
                     WHERE exposure IS NOT NULL AND exposure != '' 
                     ORDER BY exposure ASC"
                );
                $expositions = array_column($expositions, 'exposure');
            } catch (\Exception $e) {
                $expositions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
            }
            
            return $this->render('sectors/form', [
                'sector' => (object)[
                    'site_id' => $site_id,
                    'region_id' => $site['region_id']
                ],
                'regions' => $regions,
                'sites' => $sites,
                'exposures' => $expositions,
                'currentExposures' => [],
                'primaryExposure' => null,
                'media' => [],
                'csrf_token' => $this->createCsrfToken(),
                'is_edit' => false,
                'parent_site' => $site
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire de création');
            return $this->redirect('/sites/' . ($site_id ?? ''));
        }
    }

    /**
     * Validation des données de secteur
     */
    private function validateSectorData(Request $request): array
    {
        $data = [
            'name' => trim($request->request->get('name', '')),
            'code' => trim($request->request->get('code', '')),
            'description' => trim($request->request->get('description', '')),
            'region_id' => (int)$request->request->get('region_id', 0),
            'site_id' => $request->request->get('site_id') ? (int)$request->request->get('site_id') : null,
            'book_id' => $request->request->get('book_id') ? (int)$request->request->get('book_id') : null,
            'altitude' => $request->request->get('altitude') ? (int)$request->request->get('altitude') : null,
            'height' => $request->request->get('height') ? (float)$request->request->get('height') : null,
            'coordinates_lat' => $request->request->get('coordinates_lat') ? (float)$request->request->get('coordinates_lat') : null,
            'coordinates_lng' => $request->request->get('coordinates_lng') ? (float)$request->request->get('coordinates_lng') : null,
            'coordinates_swiss_e' => $request->request->get('coordinates_swiss_e') ?: null,
            'coordinates_swiss_n' => $request->request->get('coordinates_swiss_n') ?: null,
            'access_info' => trim($request->request->get('access_info', '')),
            'access_time' => $request->request->get('access_time') ? (int)$request->request->get('access_time') : null,
            'approach' => trim($request->request->get('approach', '')),
            'parking_info' => trim($request->request->get('parking_info', '')),
            'orientation' => trim($request->request->get('orientation', '')),
            'active' => (int)$request->request->get('active', 1)
        ];

        // Validation des champs obligatoires
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Le nom du secteur est obligatoire');
        }

        if (empty($data['code'])) {
            throw new \InvalidArgumentException('Le code du secteur est obligatoire');
        }

        if ($data['region_id'] <= 0) {
            throw new \InvalidArgumentException('Une région valide est obligatoire');
        }

        // Validation des contraintes
        if (strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 255 caractères');
        }

        if ($data['altitude'] !== null && ($data['altitude'] < 0 || $data['altitude'] > 9000)) {
            throw new \InvalidArgumentException('L\'altitude doit être entre 0 et 9000 mètres');
        }

        if ($data['height'] !== null && ($data['height'] < 0 || $data['height'] > 2000)) {
            throw new \InvalidArgumentException('La hauteur doit être entre 0 et 2000 mètres');
        }

        if ($data['access_time'] !== null && ($data['access_time'] < 0 || $data['access_time'] > 1440)) {
            throw new \InvalidArgumentException('Le temps d\'accès doit être entre 0 et 1440 minutes (24h)');
        }

        // Validation coordonnées GPS
        if ($data['coordinates_lat'] !== null && ($data['coordinates_lat'] < -90 || $data['coordinates_lat'] > 90)) {
            throw new \InvalidArgumentException('La latitude doit être entre -90 et 90 degrés');
        }

        if ($data['coordinates_lng'] !== null && ($data['coordinates_lng'] < -180 || $data['coordinates_lng'] > 180)) {
            throw new \InvalidArgumentException('La longitude doit être entre -180 et 180 degrés');
        }


        return $data;
    }

    /**
     * Création d'un secteur en base de données (compatible production)
     */
    private function createSector(array $data): int
    {
        // Déterminer le driver de base de données pour adapter la syntaxe
        $isMySQL = $this->db->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
        $dateFunction = $isMySQL ? 'NOW()' : 'datetime(\'now\')';
        
        // Récupérer la structure de la table pour compatibilité production
        $availableColumns = $this->getAvailableColumns('climbing_sectors');
        
        // Colonnes de base obligatoires
        $baseColumns = ['name', 'description', 'region_id', 'active', 'created_at', 'updated_at'];
        $baseValues = ['?', '?', '?', '?', $dateFunction, $dateFunction];
        $baseParams = [
            $data['name'], $data['description'],
            $data['region_id'],
            $data['active']
        ];
        
        // Colonnes optionnelles avec vérification existence
        $optionalFields = [
            'code' => $data['code'],
            'book_id' => $data['book_id'],
            'site_id' => $data['site_id'],
            'altitude' => $data['altitude'],
            'height' => $data['height'],
            'coordinates_lat' => $data['coordinates_lat'],
            'coordinates_lng' => $data['coordinates_lng'],
            'coordinates_swiss_e' => $data['coordinates_swiss_e'],
            'coordinates_swiss_n' => $data['coordinates_swiss_n'],
            'access_info' => $data['access_info'],
            'access_time' => $data['access_time'],
            'approach' => $data['approach'],
            'parking_info' => $data['parking_info'],
            'orientation' => $data['orientation']
        ];
        
        $columns = $baseColumns;
        $values = $baseValues;
        $params = $baseParams;
        
        // Ajouter les colonnes optionnelles si elles existent
        foreach ($optionalFields as $column => $value) {
            if (in_array($column, $availableColumns)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }
        
        $query = "INSERT INTO climbing_sectors (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        
        $result = $this->db->query($query, $params);
        
        if ($result) {
            return (int)$this->db->getConnection()->lastInsertId();
        }
        
        return 0;
    }

    /**
     * Récupère les colonnes disponibles dans une table (compatible SQLite/MySQL)
     */
    private function getAvailableColumns(string $tableName): array
    {
        try {
            $isMySQL = $this->db->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
            
            if ($isMySQL) {
                $columns = $this->db->fetchAll("DESCRIBE {$tableName}");
                return array_column($columns, 'Field');
            } else {
                $columns = $this->db->fetchAll("PRAGMA table_info({$tableName})");
                return array_column($columns, 'name');
            }
        } catch (\Exception $e) {
            error_log("Erreur récupération colonnes {$tableName}: " . $e->getMessage());
            return ['id', 'name', 'description', 'region_id', 'active', 'created_at', 'updated_at'];
        }
    }

    /**
     * Affiche le formulaire d'édition d'un secteur
     */
    public function edit(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        if (!$id) {
            $this->flash('error', 'ID du secteur non spécifié');
            return $this->redirect('/sectors');
        }
        
        try {
            // Récupérer le secteur avec ses relations complètes
            $sector = $this->db->fetchOne(
                "SELECT s.*, site.name as site_name, site.region_id as site_region_id,
                        reg.name as region_name, reg.id as region_id
                 FROM climbing_sectors s
                 LEFT JOIN climbing_sites site ON s.site_id = site.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 WHERE s.id = ? AND s.active = 1",
                [(int)$id]
            );
            
            if (!$sector) {
                $this->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }
            
            // Récupérer toutes les régions
            $regions = $this->db->fetchAll(
                "SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
            );
            
            // Récupérer tous les sites avec leurs régions
            $sites = $this->db->fetchAll(
                "SELECT s.id, s.name, s.region_id, r.name as region_name
                 FROM climbing_sites s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.active = 1
                 ORDER BY r.name, s.name"
            );
            
            // Récupérer les médias existants
            $media = [];
            try {
                $media = $this->db->fetchAll(
                    "SELECT m.id, m.title, m.file_path, m.media_type, mr.relationship_type
                     FROM climbing_media m 
                     JOIN climbing_media_relationships mr ON m.id = mr.media_id
                     WHERE mr.entity_type = 'sector' AND mr.entity_id = ? AND m.is_public = 1
                     ORDER BY mr.relationship_type, mr.sort_order ASC",
                    [$id]
                );
            } catch (\Exception $e) {
                error_log("Erreur récupération médias edit secteur {$id}: " . $e->getMessage());
            }
            
            return $this->render('sectors/form', [
                'title' => 'Modifier le secteur ' . $sector['name'],
                'sector' => $sector,
                'regions' => $regions,
                'sites' => $sites,
                'media' => $media,
                'selected_site' => $sector['site_id'] ? ['id' => $sector['site_id'], 'name' => $sector['site_name']] : null,
                'is_edit' => true,
                'csrf_token' => $this->createCsrfToken()
            ]);
            
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire d\'édition');
            return $this->redirect('/sectors');
        }
    }

    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        if (!$id) {
            $this->flash('error', 'ID du secteur non spécifié');
            return $this->redirect('/sectors');
        }
        
        if (!$this->validateCsrfToken($request->request->get('csrf_token'))) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect("/sectors/{$id}/edit");
        }
        
        try {
            // Récupérer le secteur à modifier
            $sector = $this->db->fetchOne(
                "SELECT * FROM climbing_sectors WHERE id = ? AND active = 1", 
                [$id]
            );
            
            if (!$sector) {
                $this->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }
            
            // Récupérer et valider les données de mise à jour
            $updateData = $this->validateSectorData($request);
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // Mettre à jour en base
            $updated = $this->db->update('climbing_sectors', $updateData, 'id = ?', [$id]);
            
            if ($updated) {
                // Gestion de l'upload d'image si présente
                $this->handleImageUpload($request, $id);
                
                $this->flash('success', 'Secteur mis à jour avec succès!');
                return $this->redirect("/sectors/{$id}");
            } else {
                $this->flash('error', 'Erreur lors de la mise à jour');
                return $this->redirect("/sectors/{$id}/edit");
            }
            
        } catch (\Exception $e) {
            app_log("Erreur update secteur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return $this->redirect("/sectors/{$id}/edit");
        }
    }
    
    public function delete($id)
    {
        // TODO: Implémenter suppression secteur
        $this->flash('success', 'Secteur supprimé avec succès!');
        return $this->redirect('/sectors');
    }
    
    public function getRoutes($id)
    {
        try {
            // TODO: Récupérer routes du secteur via API
            $routes = [];
            return $this->json($routes);
        } catch (Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des routes'], 500);
        }
    }
    
    /**
     * Gestion de l'upload d'image pour un secteur
     */
    private function handleImageUpload(Request $request, int $sectorId): void
    {
        error_log("SectorController::handleImageUpload - Début méthode pour secteur {$sectorId}");
        
        // Vérifier si un fichier a été uploadé (nom du champ: media_file ou image)
        $files = $request->files->all();
        error_log("SectorController::handleImageUpload - Files reçus: " . print_r($files, true));
        
        $uploadedFile = null;
        if (isset($files['media_file']) && $files['media_file']) {
            $uploadedFile = $files['media_file'];
            error_log("SectorController::handleImageUpload - Fichier media_file trouvé");
        } elseif (isset($files['image']) && $files['image']) {
            $uploadedFile = $files['image'];
            error_log("SectorController::handleImageUpload - Fichier image trouvé");
        } else {
            error_log("SectorController::handleImageUpload - Pas de fichier media_file ou image");
            return; // Pas de fichier, pas d'erreur
        }
        
        // Vérifier que c'est bien un objet UploadedFile et qu'il y a un fichier
        if (!$uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            error_log("SectorController::handleImageUpload - Fichier n'est pas UploadedFile");
            return;
        }
        
        if ($uploadedFile->getError() === UPLOAD_ERR_NO_FILE) {
            error_log("SectorController::handleImageUpload - UPLOAD_ERR_NO_FILE");
            return; // Pas de fichier sélectionné, normal
        }
        
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            error_log("SectorController::handleImageUpload - Erreur upload: " . $uploadedFile->getError());
            error_log("SectorController::handleImageUpload - Création du secteur continue sans image");
            return; // Ne pas faire échouer la création du secteur pour une erreur d'image
        }
        
        try {
            $mediaService = new MediaUploadService($this->db);
            $userId = $this->session->get('user_id');
            
            // Upload avec entity_type = 'sector' au lieu de 'route'
            $mediaId = $mediaService->uploadMedia(
                $uploadedFile, 
                'sector', 
                $sectorId, 
                'Image du secteur', 
                $userId
            );
            
            if ($mediaId) {
                error_log("SectorController: Image uploadée avec succès - Media ID: {$mediaId}");
            }
            
        } catch (\Exception $e) {
            error_log("SectorController: Erreur upload image - " . $e->getMessage());
            // Ne pas faire échouer la création du secteur pour une erreur d'image
            $this->flash('warning', 'Secteur créé mais erreur lors de l\'upload de l\'image: ' . $e->getMessage());
        }
    }
}