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

class RegionController extends BaseController
{
    private RegionService $regionService;
    private MediaService $mediaService;
    private WeatherService $weatherService;
    private Database $db;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        MediaService $mediaService,
        WeatherService $weatherService,
        Database $db,
        ?Auth $auth = null,
        $authService = null // Paramètre optionnel non utilisé
    ) {
        parent::__construct($view, $session, $csrfManager);

        $this->regionService = $regionService;
        $this->mediaService = $mediaService;
        $this->weatherService = $weatherService;
        $this->db = $db;
    }

    /**
     * Display list of regions
     */
    public function index(Request $request): Response
    {
        try {
            // Récupération simple des filtres
            $filters = [
                'country_id' => $request->query->get('country_id', ''),
                'difficulty' => $request->query->get('difficulty', ''),
                'season' => $request->query->get('season', ''),
                'style' => $request->query->get('style', ''),
                'search' => $request->query->get('search', ''),
                'sort' => $request->query->get('sort', 'name'),
                'order' => $request->query->get('order', 'asc')
            ];

            // Nettoyer les filtres vides
            $cleanFilters = array_filter($filters, function ($value) {
                return $value !== '' && $value !== null;
            });

            error_log("RegionController::index - Filtres appliqués: " . json_encode($cleanFilters));

            // Test simple d'abord - récupération basique des régions
            try {
                error_log("RegionController::index - Test de connexion DB");

                // Test 1: Vérifier si la table existe
                $tableTest = $this->db->fetchOne("SHOW TABLES LIKE 'climbing_regions'");
                error_log("RegionController::index - Table climbing_regions existe: " . ($tableTest ? 'OUI' : 'NON'));

                if (!$tableTest) {
                    throw new \Exception("Table climbing_regions n'existe pas");
                }

                // Test 2: Requête très simple
                error_log("RegionController::index - Test requête simple");
                $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
                error_log("RegionController::index - Récupéré " . count($regions) . " régions via fetchAll");

                // Test 3: Ajouter les informations pays si ça marche
                if (count($regions) > 0) {
                    error_log("RegionController::index - Test ajout des pays pour " . count($regions) . " régions");

                    foreach ($regions as $index => &$region) {
                        error_log("RegionController::index - Traitement région " . ($index + 1) . ": " . ($region['name'] ?? 'SANS_NOM'));

                        if (isset($region['country_id']) && $region['country_id']) {
                            error_log("RegionController::index - Country ID trouvé: " . $region['country_id']);
                            try {
                                $country = $this->db->fetchOne("SELECT name, code FROM climbing_countries WHERE id = ?", [$region['country_id']]);
                                if ($country) {
                                    $region['country_name'] = $country['name'];
                                    $region['country_code'] = $country['code'];
                                    error_log("RegionController::index - Pays ajouté: " . $country['name']);
                                } else {
                                    error_log("RegionController::index - Pays non trouvé pour ID: " . $region['country_id']);
                                    $region['country_name'] = null;
                                    $region['country_code'] = null;
                                }
                            } catch (\Exception $countryError) {
                                error_log("RegionController::index - Erreur récupération pays: " . $countryError->getMessage());
                                $region['country_name'] = null;
                                $region['country_code'] = null;
                            }
                        } else {
                            error_log("RegionController::index - Pas de country_id pour cette région");
                            $region['country_name'] = null;
                            $region['country_code'] = null;
                        }
                    }
                    unset($region); // Libérer la référence
                    error_log("RegionController::index - Finition ajout des pays");
                }
            } catch (\Exception $e) {
                error_log("Erreur requête régions: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $regions = [];
            }

            // Récupération des pays pour les filtres
            try {
                error_log("RegionController::index - Début récupération pays pour filtres");
                $countries = $this->db->fetchAll("SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC");
                error_log("RegionController::index - Récupéré " . count($countries) . " pays pour filtres");
            } catch (\Exception $e) {
                error_log("Erreur requête pays: " . $e->getMessage());
                $countries = [];
            }

            // Stats simples
            try {
                error_log("RegionController::index - Début calcul stats");
                $totalStats = [
                    'total_regions' => count($regions),
                    'total_sectors' => 0,
                    'total_routes' => 0,
                    'total_countries' => count($countries)
                ];
                error_log("RegionController::index - Stats de base calculées");

                // Compter secteurs et voies si nécessaire
                $stats = $this->db->fetchOne("
                    SELECT 
                        COUNT(DISTINCT s.id) as total_sectors,
                        COUNT(DISTINCT r.id) as total_routes
                    FROM climbing_sectors s
                    LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                    WHERE s.active = 1
                ");
                error_log("RegionController::index - Stats détaillées récupérées");

                if ($stats) {
                    $totalStats['total_sectors'] = $stats['total_sectors'];
                    $totalStats['total_routes'] = $stats['total_routes'];
                }
                error_log("RegionController::index - Stats finales calculées");
            } catch (\Exception $e) {
                error_log("Erreur stats: " . $e->getMessage());
                $totalStats = [
                    'total_regions' => count($regions),
                    'total_sectors' => 0,
                    'total_routes' => 0,
                    'total_countries' => count($countries)
                ];
            }

            error_log("RegionController::index - Préparation des données pour la vue");

            $viewData = [
                'regions' => $regions,
                'countries' => $countries,
                'filters' => $filters,
                'currentCountryId' => $filters['country_id'],
                'search' => $filters['search'],
                'difficulty' => $filters['difficulty'],
                'season' => $filters['season'],
                'style' => $filters['style'],
                'sortBy' => $filters['sort'],
                'sortOrder' => $filters['order'],
                'total_regions' => $totalStats['total_regions'],
                'total_sectors' => $totalStats['total_sectors'],
                'total_routes' => $totalStats['total_routes'],
                'total_countries' => $totalStats['total_countries']
            ];

            error_log("RegionController::index - Données préparées, appel du render");

            return $this->render('regions/index', $viewData);
        } catch (\Exception $e) {
            error_log('Erreur fatale dans RegionController::index: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            $this->session->flash('error', 'Une erreur est survenue lors du chargement des régions: ' . $e->getMessage());

            return $this->render('regions/index', [
                'regions' => [],
                'countries' => [],
                'filters' => [],
                'currentCountryId' => '',
                'search' => '',
                'difficulty' => '',
                'season' => '',
                'style' => '',
                'sortBy' => 'name',
                'sortOrder' => 'asc',
                'total_regions' => 0,
                'total_sectors' => 0,
                'total_routes' => 0,
                'total_countries' => 0,
                'error' => 'Une erreur est survenue lors du chargement des régions.'
            ]);
        }
    }

    /**
     * Show a single region
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            error_log("RegionController::show - ID reçu: " . ($id ?? 'NULL'));

            if (!$id) {
                error_log("RegionController::show - Pas d'ID, redirection");
                $this->session->flash('error', 'ID de la région non spécifié');
                return Response::redirect('/regions');
            }

            error_log("RegionController::show - Récupération région ID: " . $id);

            // Récupération directe de la région
            $region = $this->db->fetchOne(
                "SELECT r.*, c.name as country_name, c.code as country_code 
                 FROM climbing_regions r 
                 LEFT JOIN climbing_countries c ON r.country_id = c.id 
                 WHERE r.id = ? AND r.active = 1",
                [(int) $id]
            );
            error_log("RegionController::show - Région trouvée: " . ($region ? 'OUI' : 'NON'));

            if (!$region) {
                error_log("RegionController::show - Région non trouvée, redirection");
                $this->session->flash('error', 'Région non trouvée');
                return Response::redirect('/regions');
            }

            error_log("RegionController::show - Récupération des secteurs");
            // Récupération des secteurs
            $sectors = $this->db->query(
                "SELECT s.*, COUNT(r.id) as routes_count 
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                 WHERE s.region_id = ? AND s.active = 1
                 GROUP BY s.id
                 ORDER BY s.name",
                [(int) $id]
            );
            error_log("RegionController::show - Secteurs récupérés: " . count($sectors));

            // Stats simples
            $stats = [
                'sectors_count' => count($sectors),
                'routes_count' => array_sum(array_column($sectors, 'routes_count'))
            ];
            error_log("RegionController::show - Stats calculées");

            error_log("RegionController::show - Appel du render");
            return $this->render('regions/show', [
                'title' => $region['name'],
                'region' => $region,
                'sectors' => $sectors,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::show: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/regions');
        }
    }

    /**
     * API: Recherche de régions pour autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            $limit = min((int) $request->query->get('limit', 10), 50);

            if (strlen($query) < 2) {
                return new JsonResponse(['results' => []]);
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

            return new JsonResponse(['results' => $results]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::search: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de recherche'], 500);
        }
    }

    /**
     * API: Liste des régions pour la carte
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $countryId = $request->query->get('country_id', '');
            $search = $request->query->get('search', '');
            $limit = min((int) $request->query->get('limit', 100), 500);

            $sql = "SELECT r.id, r.name, r.coordinates_lat, r.coordinates_lng, c.name as country_name
                    FROM climbing_regions r 
                    LEFT JOIN climbing_countries c ON r.country_id = c.id 
                    WHERE r.active = 1";
            $params = [];

            if ($countryId) {
                $sql .= " AND r.country_id = ?";
                $params[] = $countryId;
            }

            if ($search) {
                $sql .= " AND r.name LIKE ?";
                $params[] = '%' . $search . '%';
            }

            $sql .= " ORDER BY r.name ASC LIMIT ?";
            $params[] = $limit;

            $regions = $this->db->query($sql, $params);

            return new JsonResponse([
                'success' => true,
                'data' => $regions,
                'count' => count($regions)
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::apiIndex: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de chargement'], 500);
        }
    }

    /**
     * Météo d'une région
     */
    public function weather(Request $request): JsonResponse
    {
        try {
            $id = (int) $request->attributes->get('id');

            $regionData = $this->db->fetchOne(
                "SELECT id, name, coordinates_lat, coordinates_lng FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );

            if (!$regionData || !$regionData['coordinates_lat'] || !$regionData['coordinates_lng']) {
                return new JsonResponse(['error' => 'Région ou coordonnées non trouvées'], 404);
            }

            return new JsonResponse([
                'success' => true,
                'region' => $regionData['name'],
                'coordinates' => [
                    'lat' => $regionData['coordinates_lat'],
                    'lng' => $regionData['coordinates_lng']
                ],
                'message' => 'Service météo disponible'
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::weather: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur météo'], 500);
        }
    }


    /**
     * Affiche le formulaire de création d'une nouvelle région - VERSION CORRIGÉE
     */
    public function create(Request $request): Response
    {
        try {
            // Vérification simple des permissions
            if (!$this->session->get('auth_user_id')) {
                $this->session->flash('error', 'Vous devez être connecté pour créer une région');
                return Response::redirect('/login');
            }

            // Récupérer les pays
            $countries = $this->db->fetchAll("SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC");

            return $this->render('regions/form', [
                'region' => (object)[], // Objet vide pour le formulaire de création
                'countries' => $countries,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log('RegionController::create error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire : ' . $e->getMessage());
            return Response::redirect('/regions');
        }
    }

    /**
     * Enregistre une nouvelle région - VERSION CORRIGÉE
     */
    public function store(Request $request): Response
    {
        try {
            // Vérification simple des permissions
            if (!$this->session->get('auth_user_id')) {
                $this->session->flash('error', 'Vous devez être connecté pour créer une région');
                return Response::redirect('/login');
            }

            // Valider le token CSRF
            if (!$this->validateCsrfToken($request)) {
                $this->session->flash('error', 'Token de sécurité invalide');
                return Response::redirect('/regions/create');
            }

            // Récupérer les données du formulaire
            $data = $request->request->all();

            // Validation basique
            if (empty($data['country_id']) || empty($data['name'])) {
                $this->session->flash('error', 'Le pays et le nom de la région sont obligatoires');
                return Response::redirect('/regions/create');
            }

            // Préparer les données pour insertion
            $regionData = [
                'country_id' => (int)$data['country_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'coordinates_lat' => !empty($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => !empty($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'altitude' => !empty($data['altitude']) ? (int)$data['altitude'] : null,
                'best_season' => $data['best_season'] ?? null,
                'access_info' => $data['access_info'] ?? null,
                'parking_info' => $data['parking_info'] ?? null,
                'active' => isset($data['active']) ? 1 : 1, // Par défaut actif
                'created_by' => $this->session->get('auth_user_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Insérer la région
            $regionId = $this->db->insert('climbing_regions', $regionData);

            if (!$regionId) {
                throw new \Exception('Erreur lors de la création de la région');
            }

            // Gérer l'upload d'image de couverture
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $this->mediaService->uploadMedia($_FILES['cover_image'], [
                        'entity_type' => 'region',
                        'entity_id' => $regionId,
                        'relationship_type' => 'cover',
                        'title' => $data['name'],
                        'is_public' => 1
                    ], $this->session->get('auth_user_id'));
                } catch (\Exception $e) {
                    // Log l'erreur mais continue
                    error_log('Erreur upload image région: ' . $e->getMessage());
                }
            }

            // Gérer l'upload d'images de galerie (multiple)
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                try {
                    for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['gallery_images']['name'][$i],
                                'type' => $_FILES['gallery_images']['type'][$i],
                                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                                'error' => $_FILES['gallery_images']['error'][$i],
                                'size' => $_FILES['gallery_images']['size'][$i]
                            ];
                            $this->mediaService->uploadMedia($file, [
                                'entity_type' => 'region',
                                'entity_id' => $regionId,
                                'relationship_type' => 'gallery',
                                'title' => $data['name'] . ' - Image ' . ($i + 1),
                                'is_public' => 1
                            ], $this->session->get('auth_user_id'));
                        }
                    }
                } catch (\Exception $e) {
                    // Log l'erreur mais continue
                    error_log('Erreur upload galerie région: ' . $e->getMessage());
                }
            }

            $this->session->flash('success', 'Région créée avec succès !');
            return Response::redirect('/regions/' . $regionId);
        } catch (\Exception $e) {
            error_log('RegionController::store error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création : ' . $e->getMessage());
            return Response::redirect('/regions/create');
        }
    }

    /**
     * API: Récupère les secteurs d'une région
     */
    public function apiSectors(Request $request): JsonResponse
    {
        try {
            $regionId = $request->attributes->get('id');

            if (!$regionId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'ID de région requis'
                ], 400);
            }

            // Vérifier que la région existe
            $region = $this->db->fetchOne(
                "SELECT id, name FROM climbing_regions WHERE id = ? AND active = 1",
                [(int) $regionId]
            );

            if (!$region) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Région non trouvée'
                ], 404);
            }

            // Récupérer les secteurs via SectorService (qui existe déjà)
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.altitude, s.access_time, s.coordinates_lat, s.coordinates_lng,
                    COUNT(r.id) as routes_count
             FROM climbing_sectors s 
             LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
             WHERE s.region_id = ? AND s.active = 1
             GROUP BY s.id
             ORDER BY s.name ASC",
                [(int) $regionId]
            );

            // Formatter les données pour le frontend
            $data = array_map(function ($sector) {
                return [
                    'id' => (int) $sector['id'],
                    'name' => $sector['name'],
                    'region_id' => (int) $sector['region_id'] ?? null,
                    'routes_count' => (int) ($sector['routes_count'] ?? 0),
                    'altitude' => $sector['altitude'] ? (int) $sector['altitude'] : null,
                    'access_time' => $sector['access_time'] ? (int) $sector['access_time'] : null,
                    'coordinates_lat' => $sector['coordinates_lat'] ? (float) $sector['coordinates_lat'] : null,
                    'coordinates_lng' => $sector['coordinates_lng'] ? (float) $sector['coordinates_lng'] : null
                ];
            }, $sectors);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'region' => [
                    'id' => (int) $region['id'],
                    'name' => $region['name']
                ],
                'count' => count($data)
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::apiSectors: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des secteurs'
            ], 500);
        }
    }
}
