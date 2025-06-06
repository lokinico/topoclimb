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
                    error_log("RegionController::index - Test ajout des pays");
                    foreach ($regions as &$region) {
                        if (isset($region['country_id']) && $region['country_id']) {
                            $country = $this->db->fetchOne("SELECT name, code FROM climbing_countries WHERE id = ?", [$region['country_id']]);
                            $region['country_name'] = $country['name'] ?? null;
                            $region['country_code'] = $country['code'] ?? null;
                        }
                    }
                    unset($region);
                }
            } catch (\Exception $e) {
                error_log("Erreur requête régions: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $regions = [];
            }

            // Récupération des pays pour les filtres
            try {
                $countries = $this->db->query("SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name ASC");
                error_log("RegionController::index - Récupéré " . count($countries) . " pays");
            } catch (\Exception $e) {
                error_log("Erreur requête pays: " . $e->getMessage());
                $countries = [];
            }

            // Stats simples
            $totalStats = [
                'total_regions' => count($regions),
                'total_sectors' => 0,
                'total_routes' => 0,
                'total_countries' => count($countries)
            ];

            // Compter secteurs et voies si nécessaire
            try {
                $stats = $this->db->fetchOne("
                    SELECT 
                        COUNT(DISTINCT s.id) as total_sectors,
                        COUNT(DISTINCT r.id) as total_routes
                    FROM climbing_sectors s
                    LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                    WHERE s.active = 1
                ");
                if ($stats) {
                    $totalStats['total_sectors'] = $stats['total_sectors'];
                    $totalStats['total_routes'] = $stats['total_routes'];
                }
            } catch (\Exception $e) {
                error_log("Erreur stats: " . $e->getMessage());
            }

            error_log("RegionController::index - Préparation de la vue");

            return $this->render('regions/index', [
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
            ]);
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
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID de la région non spécifié');
            return Response::redirect('/regions');
        }

        try {
            // Récupération directe de la région
            $region = $this->db->fetchOne(
                "SELECT r.*, c.name as country_name, c.code as country_code 
                 FROM climbing_regions r 
                 LEFT JOIN climbing_countries c ON r.country_id = c.id 
                 WHERE r.id = ? AND r.active = 1",
                [(int) $id]
            );

            if (!$region) {
                $this->session->flash('error', 'Région non trouvée');
                return Response::redirect('/regions');
            }

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

            // Stats simples
            $stats = [
                'sectors_count' => count($sectors),
                'routes_count' => array_sum(array_column($sectors, 'routes_count'))
            ];

            return $this->render('regions/show', [
                'title' => $region['name'],
                'region' => $region,
                'sectors' => $sectors,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::show: ' . $e->getMessage());
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
}
