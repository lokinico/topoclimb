<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\WeatherService;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;
use TopoclimbCH\Exceptions\SecurityException;

class RegionController extends BaseController
{
    private RegionService $regionService;
    private MediaService $mediaService;
    private WeatherService $weatherService;

    // Constantes de sécurité pour la Suisse
    private const SWISS_BOUNDS = [
        'lat_min' => 45.8,
        'lat_max' => 47.9,
        'lng_min' => 5.9,
        'lng_max' => 10.6
    ];

    private const MAX_ALTITUDE_SWITZERLAND = 4500; // Mont-Blanc Swiss side
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_IMAGE_SIZE = 5242880; // 5MB
    private const MAX_GALLERY_IMAGES = 10;

    // Rate limiting pour API
    private const SEARCH_RATE_LIMIT = 10; // requests per minute
    private const API_RATE_LIMIT = 60; // requests per minute

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        MediaService $mediaService,
        WeatherService $weatherService,
        Database $db,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->regionService = $regionService;
        $this->mediaService = $mediaService;
        $this->weatherService = $weatherService;
        $this->db = $db;
    }

    /**
     * Affichage sécurisé de la liste des régions
     */
    public function index(Request $request): Response
    {
        try {
            // Validation et nettoyage des filtres
            $filters = $this->validateAndSanitizeFilters($request);

            // Récupération sécurisée des données
            $data = $this->executeInTransaction(function () use ($filters) {
                return $this->getRegionsData($filters);
            });

            // Log de l'action
            $this->logAction('view_regions_list', ['filters' => $filters]);

            // Ajouter la clé API météo
            $data['weather_api_key'] = $_ENV['OPENWEATHER_API_KEY'] ?? '';
            
            return $this->render('regions/index', $data);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement des régions');

            return $this->render('regions/index', [
                'regions' => [],
                'countries' => [],
                'filters' => [],
                'weather_api_key' => $_ENV['OPENWEATHER_API_KEY'] ?? '',
                'stats' => ['total_regions' => 0, 'total_sectors' => 0, 'total_routes' => 0],
                'error' => 'Impossible de charger les régions actuellement.'
            ]);
        }
    }

    /**
     * Validation et nettoyage des filtres
     */
    private function validateAndSanitizeFilters(Request $request): array
    {
        $filters = [
            'country_id' => $request->query->get('country_id', ''),
            'difficulty' => $request->query->get('difficulty', ''),
            'season' => $request->query->get('season', ''),
            'style' => $request->query->get('style', ''),
            'search' => $request->query->get('search', ''),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc')
        ];

        // Validation des paramètres
        if ($filters['country_id'] && !is_numeric($filters['country_id'])) {
            $filters['country_id'] = '';
        }

        // Limiter la recherche textuelle
        if (strlen($filters['search']) > 100) {
            $filters['search'] = substr($filters['search'], 0, 100);
        }

        // Valider les colonnes de tri autorisées et mapper aux colonnes avec préfixes
        $allowedSorts = ['name', 'created_at', 'country_name'];
        if (!in_array($filters['sort'], $allowedSorts)) {
            $filters['sort'] = 'name';
        }
        
        // Mapper les colonnes de tri vers les colonnes avec préfixes de table
        $sortMapping = [
            'name' => 'r.name',
            'created_at' => 'r.created_at',
            'country_name' => 'c.name'
        ];
        $filters['sort'] = $sortMapping[$filters['sort']];

        // Valider l'ordre de tri
        if (!in_array(strtolower($filters['order']), ['asc', 'desc'])) {
            $filters['order'] = 'asc';
        }

        return array_filter($filters, fn($value) => $value !== '' && $value !== null);
    }

    /**
     * Récupération sécurisée des données régions
     */
    private function getRegionsData(array $filters): array
    {
        // Construction sécurisée de la requête
        $sql = "SELECT r.id, r.name, r.description, r.coordinates_lat, r.coordinates_lng,
                       r.altitude, r.created_at, c.name as country_name, c.code as country_code
                FROM climbing_regions r 
                LEFT JOIN climbing_countries c ON r.country_id = c.id 
                WHERE r.active = 1";
        $params = [];

        if (isset($filters['country_id'])) {
            $sql .= " AND r.country_id = ?";
            $params[] = (int)$filters['country_id'];
        }

        if (isset($filters['search'])) {
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY " . $filters['sort'] . " " . strtoupper($filters['order']);
        $sql .= " LIMIT 500"; // Limite de sécurité

        $regions = $this->db->fetchAll($sql, $params);

        // Récupération des pays pour les filtres
        $countries = $this->db->fetchAll(
            "SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC"
        );

        // Calcul des statistiques
        $stats = $this->calculateStats();

        return [
            'regions' => $regions,
            'countries' => $countries,
            'filters' => $filters,
            'stats' => $stats
        ];
    }

    /**
     * Affichage sécurisé d'une région avec filtres
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            // Validation simple de l'ID
            if (!$id || !is_numeric($id)) {
                $this->flash('error', 'ID de région invalide');
                return $this->redirect('/regions');
            }
            
            $id = (int) $id;

            // Récupération simplifiée des données
            $data = $this->getRegionDetailsSimplified($id);

            return $this->render('regions/show', $data);
        } catch (\Exception $e) {
            error_log("RegionController::show - Erreur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement de la région');
            return $this->redirect('/regions');
        }
    }

    /**
     * Récupération simplifiée des détails d'une région
     */
    private function getRegionDetailsSimplified(int $id): array
    {
        // Récupération de base de la région
        $region = $this->db->fetchOne(
            "SELECT r.*, c.name as country_name, c.code as country_code 
             FROM climbing_regions r 
             LEFT JOIN climbing_countries c ON r.country_id = c.id 
             WHERE r.id = ? AND r.active = 1",
            [$id]
        );

        $this->requireEntity($region, 'Région non trouvée');

        // Récupération simplifiée des secteurs sans agrégation complexe
        $sectors = $this->db->fetchAll(
            "SELECT s.id, s.name, s.code, s.altitude, s.access_time, 
                    s.coordinates_lat, s.coordinates_lng, s.description,
                    si.name as site_name, si.id as site_id
             FROM climbing_sectors s 
             LEFT JOIN climbing_sites si ON s.site_id = si.id
             WHERE s.region_id = ? AND s.active = 1
             ORDER BY s.name ASC 
             LIMIT 100",
            [$id]
        );

        // Ajout du nombre de voies par secteur avec requête séparée
        foreach ($sectors as &$sector) {
            $routesCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ? AND active = 1",
                [$sector['id']]
            );
            $sector['routes_count'] = $routesCount['count'] ?? 0;
        }

        // Récupération des sites pour les filtres
        $sites = $this->db->fetchAll(
            "SELECT DISTINCT si.id, si.name 
             FROM climbing_sites si
             JOIN climbing_sectors s ON si.id = s.site_id
             WHERE s.region_id = ? AND si.active = 1 AND s.active = 1
             ORDER BY si.name ASC",
            [$id]
        );

        // Récupération des difficultés disponibles
        $difficulties = $this->db->fetchAll(
            "SELECT DISTINCT r.difficulty
             FROM climbing_routes r
             JOIN climbing_sectors s ON r.sector_id = s.id
             WHERE s.region_id = ? AND r.active = 1 AND s.active = 1 AND r.difficulty IS NOT NULL
             ORDER BY r.difficulty ASC",
            [$id]
        );

        // Récupération des médias (images)
        $media = $this->db->fetchAll(
            "SELECT m.id, m.filename, m.title, m.description, mr.relationship_type
             FROM climbing_media m
             JOIN climbing_media_relationships mr ON m.id = mr.media_id
             WHERE mr.entity_type = 'region' AND mr.entity_id = ? AND m.is_public = 1
             ORDER BY mr.relationship_type, mr.sort_order",
            [$id]
        );

        $stats = [
            'sectors_count' => count($sectors),
            'routes_count' => array_sum(array_column($sectors, 'routes_count'))
        ];

        return [
            'title' => $region['name'],
            'region' => $region,
            'sectors' => $sectors,
            'sites' => $sites,
            'difficulties' => array_column($difficulties, 'difficulty'),
            'currentFilters' => [],
            'media' => $media,
            'stats' => $stats
        ];
    }

    /**
     * Validation des filtres pour la page région
     */
    private function validateRegionFilters(Request $request): array
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'site_id' => $request->query->get('site_id', ''),
            'difficulty' => $request->query->get('difficulty', '')
        ];

        // Validation et nettoyage
        if (strlen($filters['search']) > 100) {
            $filters['search'] = substr($filters['search'], 0, 100);
        }
        
        if ($filters['site_id'] && !is_numeric($filters['site_id'])) {
            $filters['site_id'] = '';
        }

        // Nettoyer les valeurs vides
        return array_filter($filters, fn($value) => $value !== '' && $value !== null);
    }

    /**
     * Récupération sécurisée des détails d'une région avec filtres
     */
    private function getRegionDetails(int $id, array $filters = []): array
    {
        $region = $this->db->fetchOne(
            "SELECT r.*, c.name as country_name, c.code as country_code 
             FROM climbing_regions r 
             LEFT JOIN climbing_countries c ON r.country_id = c.id 
             WHERE r.id = ? AND r.active = 1",
            [$id]
        );

        $this->requireEntity($region, 'Région non trouvée');

        // Construction de la requête des secteurs avec filtres
        $sectorsQuery = "SELECT s.id, s.name, s.code, s.altitude, s.access_time, s.coordinates_lat, s.coordinates_lng,
                                s.description, si.name as site_name, si.id as site_id,
                                COUNT(r.id) as routes_count,
                                ROUND(AVG(CAST(r.difficulty AS DECIMAL(3,1))), 1) as avg_difficulty,
                                ROUND(AVG(r.beauty_rating), 1) as avg_beauty
                         FROM climbing_sectors s 
                         LEFT JOIN climbing_sites si ON s.site_id = si.id
                         LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                         WHERE s.region_id = ? AND s.active = 1";
        
        $sectorsParams = [$id];

        // Appliquer les filtres
        if (isset($filters['search'])) {
            $sectorsQuery .= " AND (s.name LIKE ? OR s.code LIKE ? OR s.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $sectorsParams[] = $searchTerm;
            $sectorsParams[] = $searchTerm;
            $sectorsParams[] = $searchTerm;
        }

        if (isset($filters['site_id'])) {
            $sectorsQuery .= " AND s.site_id = ?";
            $sectorsParams[] = (int)$filters['site_id'];
        }

        if (isset($filters['difficulty'])) {
            $sectorsQuery .= " AND EXISTS (
                SELECT 1 FROM climbing_routes ro 
                WHERE ro.sector_id = s.id AND ro.difficulty = ? AND ro.active = 1
            )";
            $sectorsParams[] = $filters['difficulty'];
        }

        $sectorsQuery .= " GROUP BY s.id ORDER BY s.name ASC LIMIT 100";
        
        $sectors = $this->db->query($sectorsQuery, $sectorsParams);

        // Récupération des sites pour les filtres
        $sites = $this->db->query(
            "SELECT DISTINCT si.id, si.name 
             FROM climbing_sites si
             JOIN climbing_sectors s ON si.id = s.site_id
             WHERE s.region_id = ? AND si.active = 1 AND s.active = 1
             ORDER BY si.name ASC",
            [$id]
        );

        // Récupération des difficultés disponibles
        $difficulties = $this->db->query(
            "SELECT DISTINCT r.difficulty
             FROM climbing_routes r
             JOIN climbing_sectors s ON r.sector_id = s.id
             WHERE s.region_id = ? AND r.active = 1 AND s.active = 1 AND r.difficulty IS NOT NULL
             ORDER BY r.difficulty ASC",
            [$id]
        );

        // Récupération des médias (images)
        $media = $this->db->query(
            "SELECT m.id, m.filename, m.title, m.description, mr.relationship_type
             FROM climbing_media m
             JOIN climbing_media_relationships mr ON m.id = mr.media_id
             WHERE mr.entity_type = 'region' AND mr.entity_id = ? AND m.is_public = 1
             ORDER BY mr.relationship_type, mr.sort_order",
            [$id]
        );

        $stats = [
            'sectors_count' => count($sectors),
            'routes_count' => array_sum(array_column($sectors, 'routes_count'))
        ];

        return [
            'title' => $region->name,
            'region' => $region,
            'sectors' => $sectors,
            'sites' => $sites,
            'difficulties' => array_column($difficulties, 'difficulty'),
            'currentFilters' => $filters,
            'media' => $media,
            'stats' => $stats
        ];
    }

    /**
     * Formulaire de création - avec vérification des permissions
     */
    public function create(Request $request): Response
    {
        try {
            // Vérification des permissions (admin, modérateur, éditeur)
            $this->requireRole([0, 1, 2], 'Permissions insuffisantes pour créer une région');

            $countries = $this->db->fetchAll(
                "SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC"
            );

            return $this->render('regions/form', [
                'region' => (object)[],
                'countries' => $countries,
                'csrf_token' => $this->createCsrfToken(),
                'is_edit' => false,
                'swisstopo_api_key' => $_ENV['SWISSTOPO_API_KEY'] ?? ''
            ]);
        } catch (AuthorizationException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/regions');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire');
            return $this->redirect('/regions');
        }
    }

    /**
     * Création d'une nouvelle région (version test sans authentification)
     */
    public function testCreate(Request $request): Response
    {
        try {
            $countries = $this->db->fetchAll(
                "SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC"
            );

            return $this->render('regions/form', [
                'region' => (object)[],
                'countries' => $countries,
                'csrf_token' => $this->createCsrfToken(),
                'is_edit' => false,
                'swisstopo_api_key' => $_ENV['SWISSTOPO_API_KEY'] ?? ''
            ]);
        } catch (\Exception $e) {
            return new Response('Formulaire région - Test', 200);
        }
    }

    /**
     * Sauvegarde sécurisée d'une nouvelle région
     */
    public function store(Request $request): Response
    {
        try {
            // Vérifications de sécurité
            $this->requireRole([0, 1, 2], 'Permissions insuffisantes pour créer une région');
            $this->requireCsrfToken($request);

            // Validation des données
            $data = $this->validateRegionData($request->request->all());

            // Création en transaction
            $regionId = $this->executeInTransaction(function () use ($data) {
                return $this->createRegion($data);
            });

            // Gestion sécurisée des uploads
            $this->handleImageUploads($request, $regionId);

            // Log de l'action
            $this->logAction('create_region', [
                'region_id' => $regionId,
                'region_name' => $data['name']
            ]);

            $this->flash('success', 'Région créée avec succès !');
            return $this->redirect('/regions/' . $regionId);
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/regions/create');
        } catch (AuthorizationException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/regions');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la création de la région');
            return $this->redirect('/regions/create');
        }
    }

    /**
     * Validation stricte des données de région
     */
    private function validateRegionData(array $data): array
    {
        $rules = [
            'country_id' => 'required|numeric',
            'name' => 'required|min:2|max:255',
            'description' => 'nullable|max:5000',
            'coordinates_lat' => 'nullable|numeric|between:' . self::SWISS_BOUNDS['lat_min'] . ',' . self::SWISS_BOUNDS['lat_max'],
            'coordinates_lng' => 'nullable|numeric|between:' . self::SWISS_BOUNDS['lng_min'] . ',' . self::SWISS_BOUNDS['lng_max'],
            'altitude' => 'nullable|numeric|min:0|max:' . self::MAX_ALTITUDE_SWITZERLAND,
            'best_season' => 'nullable|max:100',
            'access_info' => 'nullable|max:2000',
            'parking_info' => 'nullable|max:1000'
        ];

        $validatedData = $this->validateInput($data, $rules);

        // Validation supplémentaire pour coordonnées cohérentes
        if (isset($validatedData['coordinates_lat']) && isset($validatedData['coordinates_lng'])) {
            if (!$this->areCoordinatesInSwitzerland($validatedData['coordinates_lat'], $validatedData['coordinates_lng'])) {
                throw new ValidationException('Les coordonnées doivent être situées en Suisse');
            }
        }

        // Vérifier l'unicité du nom dans le pays
        if ($this->regionNameExists($validatedData['name'], $validatedData['country_id'])) {
            throw new ValidationException('Une région avec ce nom existe déjà dans ce pays');
        }

        return $validatedData;
    }

    /**
     * Validation géographique des coordonnées suisses
     */
    private function areCoordinatesInSwitzerland(float $lat, float $lng): bool
    {
        return $lat >= self::SWISS_BOUNDS['lat_min'] && $lat <= self::SWISS_BOUNDS['lat_max'] &&
            $lng >= self::SWISS_BOUNDS['lng_min'] && $lng <= self::SWISS_BOUNDS['lng_max'];
    }

    /**
     * Vérification d'unicité du nom de région
     */
    private function regionNameExists(string $name, int $countryId, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM climbing_regions WHERE name = ? AND country_id = ? AND active = 1";
        $params = [$name, $countryId];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return (bool)$this->db->fetchOne($sql, $params);
    }

    /**
     * Création sécurisée de la région
     */
    private function createRegion(array $data): int
    {
        $regionData = [
            'country_id' => (int)$data['country_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'coordinates_lat' => isset($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
            'coordinates_lng' => isset($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
            'altitude' => isset($data['altitude']) ? (int)$data['altitude'] : null,
            'best_season' => $data['best_season'] ?? null,
            'access_info' => $data['access_info'] ?? null,
            'parking_info' => $data['parking_info'] ?? null,
            'active' => 1,
            'created_by' => $this->auth->id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $regionId = $this->db->insert('climbing_regions', $regionData);

        if (!$regionId) {
            throw new \RuntimeException('Échec de la création de la région');
        }

        return $regionId;
    }

    /**
     * Gestion sécurisée des uploads d'images
     */
    private function handleImageUploads(Request $request, int $regionId): void
    {
        // Image de couverture
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $this->validateAndUploadImage($_FILES['cover_image'], $regionId, 'cover');
        }

        // Galerie d'images
        if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
            $this->validateAndUploadGallery($_FILES['gallery_images'], $regionId);
        }
    }

    /**
     * Validation et upload sécurisé d'une image
     */
    private function validateAndUploadImage(array $file, int $regionId, string $type): void
    {
        // Validation de base
        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            throw new ValidationException('L\'image est trop volumineuse (max 5MB)');
        }

        // Validation du type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            throw new ValidationException('Type d\'image non autorisé. Utilisez JPG, PNG ou WebP');
        }

        // Validation d'image réelle (pas juste l'extension)
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new ValidationException('Le fichier n\'est pas une image valide');
        }

        // Upload via MediaService sécurisé
        try {
            $this->mediaService->uploadMedia($file, [
                'entity_type' => 'region',
                'entity_id' => $regionId,
                'relationship_type' => $type,
                'title' => 'Image région',
                'is_public' => 1
            ], $this->auth->id());
        } catch (\Exception $e) {
            error_log('Erreur upload image région: ' . $e->getMessage());
            // Ne pas faire échouer la création pour les images
        }
    }

    /**
     * Validation et upload sécurisé de la galerie
     */
    private function validateAndUploadGallery(array $files, int $regionId): void
    {
        $imageCount = 0;

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                if ($imageCount >= self::MAX_GALLERY_IMAGES) {
                    error_log('Limite de galerie atteinte: ' . self::MAX_GALLERY_IMAGES);
                    break;
                }

                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];

                try {
                    $this->validateAndUploadImage($file, $regionId, 'gallery');
                    $imageCount++;
                } catch (\Exception $e) {
                    error_log('Erreur upload galerie item ' . $i . ': ' . $e->getMessage());
                    // Continuer avec les autres images
                }
            }
        }
    }

    /**
     * API de recherche avec rate limiting
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Rate limiting
            $this->checkRateLimit('search', self::SEARCH_RATE_LIMIT);

            $query = $request->query->get('q', '');
            $limit = min((int)$request->query->get('limit', 10), 50);

            if (strlen($query) < 2) {
                return new JsonResponse(['results' => []]);
            }

            // Nettoyage et validation de la requête
            $query = trim(strip_tags($query));
            if (strlen($query) > 100) {
                $query = substr($query, 0, 100);
            }

            $results = $this->db->query(
                "SELECT r.id, r.name, c.name as country_name
                 FROM climbing_regions r 
                 LEFT JOIN climbing_countries c ON r.country_id = c.id
                 WHERE r.active = 1 AND (r.name LIKE ? OR c.name LIKE ?)
                 ORDER BY r.name ASC
                 LIMIT ?",
                ['%' . $query . '%', '%' . $query . '%', $limit]
            );

            // Log de la recherche
            $this->logAction('search_regions', ['query' => $query, 'results_count' => count($results)]);

            return new JsonResponse([
                'success' => true,
                'results' => $results,
                'query' => $query
            ]);
        } catch (SecurityException $e) {
            return new JsonResponse(['error' => 'Trop de requêtes. Veuillez patienter.'], 429);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur de recherche');
            return new JsonResponse(['error' => 'Erreur de recherche'], 500);
        }
    }

    /**
     * API météo avec validation des coordonnées
     */
    public function weather(Request $request): JsonResponse
    {
        try {
            $id = $this->validateId($request->attributes->get('id'), 'ID de région');

            $region = $this->db->fetchOne(
                "SELECT id, name, coordinates_lat, coordinates_lng FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );

            $this->requireEntity($region, 'Région non trouvée');

            if (!$region->coordinates_lat || !$region->coordinates_lng) {
                return new JsonResponse(['error' => 'Coordonnées non disponibles pour cette région'], 400);
            }

            // Validation des coordonnées suisses
            if (!$this->areCoordinatesInSwitzerland($region->coordinates_lat, $region->coordinates_lng)) {
                return new JsonResponse(['error' => 'Coordonnées invalides'], 400);
            }

            // Log de la requête météo
            $this->logAction('get_weather', ['region_id' => $id, 'region_name' => $region->name]);

            return new JsonResponse([
                'success' => true,
                'region' => $region->name,
                'coordinates' => [
                    'lat' => (float)$region->coordinates_lat,
                    'lng' => (float)$region->coordinates_lng
                ],
                'message' => 'Données météo disponibles'
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur météo');
            return new JsonResponse(['error' => 'Service météo temporairement indisponible'], 500);
        }
    }

    /**
     * API publique avec rate limiting
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            // Rate limiting moins strict pour API publique
            $this->checkRateLimit('api', self::API_RATE_LIMIT);

            $countryId = $request->query->get('country_id', '');
            $search = $request->query->get('search', '');
            $limit = min((int)$request->query->get('limit', 100), 500);

            // Validation des paramètres
            if ($countryId && !is_numeric($countryId)) {
                return new JsonResponse(['error' => 'ID pays invalide'], 400);
            }

            $sql = "SELECT r.id, r.name, r.coordinates_lat, r.coordinates_lng, c.name as country_name
                    FROM climbing_regions r 
                    LEFT JOIN climbing_countries c ON r.country_id = c.id 
                    WHERE r.active = 1";
            $params = [];

            if ($countryId) {
                $sql .= " AND r.country_id = ?";
                $params[] = (int)$countryId;
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

            $regions = $this->db->fetchAll($sql, $params);

            return new JsonResponse([
                'success' => true,
                'data' => $regions,
                'count' => count($regions),
                'limit' => $limit
            ]);
        } catch (SecurityException $e) {
            return new JsonResponse(['error' => 'Limite de requêtes atteinte'], 429);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur API');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * API secteurs d'une région
     */
    public function apiSectors(Request $request): JsonResponse
    {
        try {
            $regionId = $this->validateId($request->attributes->get('id'), 'ID de région');

            // Vérifier l'existence de la région
            $region = $this->db->fetchOne(
                "SELECT id, name FROM climbing_regions WHERE id = ? AND active = 1",
                [$regionId]
            );

            $this->requireEntity($region, 'Région non trouvée');

            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.altitude, s.access_time, s.coordinates_lat, s.coordinates_lng,
                        COUNT(r.id) as routes_count
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                 WHERE s.region_id = ? AND s.active = 1
                 GROUP BY s.id
                 ORDER BY s.name ASC
                 LIMIT 100",
                [$regionId]
            );

            // Formatage sécurisé des données
            $data = array_map(function ($sector) {
                return [
                    'id' => (int)$sector['id'],
                    'name' => $sector['name'],
                    'routes_count' => (int)($sector['routes_count'] ?? 0),
                    'altitude' => $sector['altitude'] ? (int)$sector['altitude'] : null,
                    'access_time' => $sector['access_time'] ? (int)$sector['access_time'] : null,
                    'coordinates_lat' => $sector['coordinates_lat'] ? (float)$sector['coordinates_lat'] : null,
                    'coordinates_lng' => $sector['coordinates_lng'] ? (float)$sector['coordinates_lng'] : null
                ];
            }, $sectors);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'region' => ['id' => (int)$region['id'], 'name' => $region['name']],
                'count' => count($data)
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur récupération secteurs');
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * Rate limiting simple basé sur la session
     */
    private function checkRateLimit(string $action, int $maxRequests): void
    {
        $key = "rate_limit_{$action}";
        $now = time();
        $window = 60; // 1 minute

        $requests = $this->session->get($key, []);

        // Nettoyer les anciennes requêtes
        $requests = array_filter($requests, fn($timestamp) => $now - $timestamp < $window);

        if (count($requests) >= $maxRequests) {
            throw new SecurityException('Rate limit exceeded');
        }

        $requests[] = $now;
        $this->session->set($key, $requests);
    }

    /**
     * Calcul sécurisé des statistiques
     */
    private function calculateStats(): array
    {
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    (SELECT COUNT(*) FROM climbing_regions WHERE active = 1) as total_regions,
                    (SELECT COUNT(*) FROM climbing_sectors WHERE active = 1) as total_sectors,
                    (SELECT COUNT(*) FROM climbing_routes WHERE active = 1) as total_routes,
                    (SELECT COUNT(*) FROM climbing_countries WHERE active = 1) as total_countries"
            );

            return [
                'total_regions' => (int)($stats['total_regions'] ?? 0),
                'total_sectors' => (int)($stats['total_sectors'] ?? 0),
                'total_routes' => (int)($stats['total_routes'] ?? 0),
                'total_countries' => (int)($stats['total_countries'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log('Erreur calcul stats: ' . $e->getMessage());
            return ['total_regions' => 0, 'total_sectors' => 0, 'total_routes' => 0, 'total_countries' => 0];
        }
    }
}
