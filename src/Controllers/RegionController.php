<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\WeatherService;
use TopoclimbCH\Core\Security\CsrfManager;

class RegionController extends BaseController
{
    protected RegionService $regionService;
    protected MediaService $mediaService;
    protected WeatherService $weatherService;
    protected Database $db;
    protected ?Auth $auth;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        MediaService $mediaService,
        WeatherService $weatherService,
        Database $db,
        ?Auth $auth = null,
        $authService = null // Paramètre optionnel non utilisé pour l'instant
    ) {
        parent::__construct($view, $session, $csrfManager);

        $this->regionService = $regionService;
        $this->mediaService = $mediaService;
        $this->weatherService = $weatherService;
        $this->db = $db;
        $this->auth = $auth ?? Auth::getInstance();
    }

    /**
     * Display list of regions with optional filters
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

            // Récupération des données avec gestion d'erreurs
            try {
                $regions = $this->regionService->getRegionsWithFilters($cleanFilters);
                error_log("RegionController::index - Récupéré " . count($regions) . " régions");
            } catch (\Exception $e) {
                error_log("Erreur getRegionsWithFilters: " . $e->getMessage());
                $regions = [];
            }

            try {
                $countries = $this->regionService->getActiveCountries();
                error_log("RegionController::index - Récupéré " . count($countries) . " pays");
            } catch (\Exception $e) {
                error_log("Erreur getActiveCountries: " . $e->getMessage());
                $countries = [];
            }

            try {
                $totalStats = $this->regionService->getOverallStatistics();
                error_log("RegionController::index - Stats récupérées");
            } catch (\Exception $e) {
                error_log("Erreur getOverallStatistics: " . $e->getMessage());
                $totalStats = [
                    'total_regions' => 0,
                    'total_sectors' => 0,
                    'total_routes' => 0,
                    'total_countries' => 0
                ];
            }

            // Préparation des données pour la vue
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
                // Stats totales avec valeurs par défaut
                'total_regions' => $totalStats['total_regions'] ?? count($regions),
                'total_sectors' => $totalStats['total_sectors'] ?? 0,
                'total_routes' => $totalStats['total_routes'] ?? 0,
                'total_countries' => $totalStats['total_countries'] ?? count($countries)
            ];

            error_log("RegionController::index - Préparation de la vue avec " . count($viewData) . " variables");

            // Utiliser la méthode view() de BaseController
            return $this->view('regions/index', $viewData);
        } catch (\Exception $e) {
            error_log('Erreur fatale dans RegionController::index: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            // Page d'erreur avec données minimales
            return $this->view('regions/index', [
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

            $regions = $this->regionService->searchRegions($query, $limit);

            return new JsonResponse(['results' => $regions]);
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
            $filters = [
                'country_id' => $request->query->get('country_id', ''),
                'search' => $request->query->get('search', ''),
                'limit' => min((int) $request->query->get('limit', 100), 500)
            ];

            $cleanFilters = array_filter($filters, function ($value) {
                return $value !== '' && $value !== null;
            });

            $regions = $this->regionService->getRegionsForApi($cleanFilters);

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

            // Requête SQL directe pour éviter les problèmes de modèles
            $regionData = $this->db->fetchOne(
                "SELECT id, name, coordinates_lat, coordinates_lng FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );

            if (!$regionData || !$regionData['coordinates_lat'] || !$regionData['coordinates_lng']) {
                return new JsonResponse(['error' => 'Région ou coordonnées non trouvées'], 404);
            }

            // Essayer d'obtenir les données météo
            try {
                $weatherData = $this->weatherService->getCurrentWeather(
                    (float) $regionData['coordinates_lat'],
                    (float) $regionData['coordinates_lng']
                );

                return new JsonResponse([
                    'success' => true,
                    'region' => $regionData['name'],
                    'weather' => $weatherData
                ]);
            } catch (\Exception $weatherError) {
                error_log("Erreur météo: " . $weatherError->getMessage());

                return new JsonResponse([
                    'success' => true,
                    'region' => $regionData['name'],
                    'coordinates' => [
                        'lat' => $regionData['coordinates_lat'],
                        'lng' => $regionData['coordinates_lng']
                    ],
                    'message' => 'Données météo temporairement indisponibles'
                ]);
            }
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::weather: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur météo'], 500);
        }
    }

    /**
     * Affiche les détails d'une région
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int) $request->attributes->get('id');

            if (!$id) {
                $this->session->flash('error', 'ID région invalide');
                return $this->redirect('/regions');
            }

            // Récupération directe pour éviter les problèmes
            $regionData = $this->db->fetchOne(
                "SELECT r.*, c.name as country_name 
                 FROM climbing_regions r 
                 LEFT JOIN climbing_countries c ON r.country_id = c.id 
                 WHERE r.id = ? AND r.active = 1",
                [$id]
            );

            if (!$regionData) {
                $this->session->flash('error', 'Région non trouvée');
                return $this->redirect('/regions');
            }

            // Récupération des secteurs
            $sectors = $this->db->query(
                "SELECT s.*, COUNT(r.id) as routes_count 
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                 WHERE s.region_id = ? AND s.active = 1
                 GROUP BY s.id
                 ORDER BY s.name",
                [$id]
            );

            // Stats simples
            $stats = [
                'sectors_count' => count($sectors),
                'routes_count' => array_sum(array_column($sectors, 'routes_count'))
            ];

            return $this->view('regions/show', [
                'region' => (object) $regionData,
                'sectors' => $sectors,
                'stats' => $stats,
                'title' => $regionData['name']
            ]);
        } catch (\Exception $e) {
            error_log('Erreur dans RegionController::show: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement de la région');
            return $this->redirect('/regions');
        }
    }

    /**
     * Helper method pour redirection
     */
    protected function redirect(string $url): Response
    {
        $response = new Response();
        $response->setStatusCode(302);
        $response->setHeader('Location', $url);
        return $response;
    }
}
