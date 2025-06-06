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
use TopoclimbCH\Services\CountryService;
use TopoclimbCH\Services\WeatherService;
use TopoclimbCH\Services\AuthService; // ← CORRECTION: Import correct
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Validation\Validator;

class RegionController extends BaseController
{
    private const SWISS_BOUNDS = [
        'lat_min' => 45.8,
        'lat_max' => 47.9,
        'lng_min' => 5.9,
        'lng_max' => 10.6
    ];

    private const VALIDATION_RULES = [
        'country_id' => 'required|numeric',
        'name' => 'required|min:2|max:100',
        'description' => 'nullable|max:2000',
        'coordinates_lat' => 'nullable|numeric|between:-90,90',
        'coordinates_lng' => 'nullable|numeric|between:-180,180',
        'altitude' => 'nullable|numeric|min:0|max:5000',
        'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
        'access_info' => 'nullable|max:1000',
        'parking_info' => 'nullable|max:1000'
    ];

    protected RegionService $regionService;
    protected MediaService $mediaService;
    protected WeatherService $weatherService;
    protected Database $db;
    protected ?Auth $auth;
    protected ?AuthService $authService;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        MediaService $mediaService,
        WeatherService $weatherService,
        Database $db,
        ?Auth $auth = null,
        ?AuthService $authService = null // ← CORRECTION: Type hint correct maintenant
    ) {
        parent::__construct($view, $session, $csrfManager);

        $this->regionService = $regionService;
        $this->mediaService = $mediaService;
        $this->weatherService = $weatherService;
        $this->db = $db;
        $this->auth = $auth ?? Auth::getInstance();
        $this->authService = $authService;
    }

    /**
     * Display list of regions with advanced filtering and statistics
     */
    public function index(Request $request): Response
    {
        $filters = [
            'country_id' => $request->query->get('country_id'),
            'difficulty' => $request->query->get('difficulty'),
            'season' => $request->query->get('season'),
            'style' => $request->query->get('style'),
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc')
        ];

        // Get filtered regions with enhanced data
        $regions = $this->regionService->getRegionsWithFilters($filters);

        // Get countries for filter dropdown
        $countries = $this->regionService->getActiveCountries();

        // Calculate overall statistics
        $totalStats = $this->regionService->getOverallStatistics();

        // Get popular regions for quick access
        $popularRegions = $this->regionService->getPopularRegions(5);

        return $this->render('regions/index', [
            'regions' => $regions,
            'countries' => $countries,
            'filters' => $filters,
            'totalStats' => $totalStats,
            'popularRegions' => $popularRegions,
            'title' => $this->buildPageTitle($filters),
            'total_sectors' => $totalStats['total_sectors'] ?? 0,
            'total_routes' => $totalStats['total_routes'] ?? 0
        ]);
    }

    /**
     * Display detailed region page with all related data
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int) $request->attributes->get('id');
            if (!$id) {
                throw new \InvalidArgumentException('ID invalide');
            }

            $region = $this->regionService->getRegionWithAllRelations($id);
            if (!$region) {
                throw new \RuntimeException('Région non trouvée');
            }

            if (!$region) {
                $this->flash('error', 'Région non trouvée');
                return $this->redirect('/regions');
            }

            // Get sectors with enhanced data
            $sectors = $this->regionService->getRegionSectorsWithStats($id);

            // Get comprehensive statistics
            $stats = $this->regionService->getRegionDetailedStatistics($id);

            // Get photos and media
            $photos = $this->mediaService->getRegionMedia($id, 'gallery');
            $coverImage = $this->mediaService->getRegionCoverImage($id);

            // Get upcoming events in this region
            $upcomingEvents = $this->regionService->getUpcomingEvents($id);

            // Get access information and parking
            $parkingAreas = $this->regionService->getRegionParking($id);

            // Get weather data if coordinates are available
            $weatherData = null;
            if ($region->coordinates_lat && $region->coordinates_lng) {
                try {
                    $weatherData = $this->weatherService->getCurrentWeather(
                        $region->coordinates_lat,
                        $region->coordinates_lng
                    );
                } catch (\Exception $e) {
                    // Weather data is optional, continue without it
                    error_log("Weather API error: " . $e->getMessage());
                }
            }

            // Get related regions (same country, similar characteristics)
            $relatedRegions = $this->regionService->getRelatedRegions($region, 4);

            return $this->render('regions/show', [
                'region' => $region,
                'sectors' => $sectors,
                'stats' => $stats,
                'photos' => $photos,
                'coverImage' => $coverImage,
                'upcomingEvents' => $upcomingEvents,
                'parkingAreas' => $parkingAreas,
                'weatherData' => $weatherData,
                'relatedRegions' => $relatedRegions,
                'title' => $region->name
            ]);
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/regions');
        }
    }

    /**
     * Show create region form
     */
    public function create(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $countries = $this->regionService->getActiveCountries();

        return $this->render('regions/form', [
            'region' => null,
            'countries' => $countries,
            'title' => 'Créer une nouvelle région',
            'csrf_token' => $this->createCsrfToken(),
            'isEditing' => false
        ]);
    }

    /**
     * Store new region with file uploads and validation
     */
    public function store(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        $validator = new Validator();
        $data = $request->request->all();

        // Validate input data
        $rules = [
            'country_id' => 'required|numeric',
            'name' => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'coordinates_lat' => 'nullable|numeric|between:-90,90',
            'coordinates_lng' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|numeric|min:0|max:5000',
            'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
            'access_info' => 'nullable|max:1000',
            'parking_info' => 'nullable|max:1000'
        ];

        if (!$validator->validate($data, $rules)) {
            return $this->jsonError('Données invalides', 400, [
                'errors' => $validator->getErrors()
            ]);
        }

        try {
            $this->db->beginTransaction();

            // Add user context
            if ($this->auth && $this->auth->check()) {
                $data['created_by'] = $this->auth->id();
            }

            // Create region
            $region = $this->regionService->createRegion($data);

            // Handle file uploads
            $this->handleFileUploads($request, $region);

            // Handle coordinates validation
            $this->validateCoordinates($data);

            $this->db->commit();

            // Clear any existing drafts
            $this->clearUserDraft($request, 'region_create');

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région créée avec succès',
                'region' => $region->toArray(),
                'redirect' => "/regions/{$region->id}"
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region creation error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la création: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Show edit region form
     */
    public function edit(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegionWithAllRelations($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        $countries = $this->regionService->getActiveCountries();

        return $this->render('regions/form', [
            'region' => $region,
            'countries' => $countries,
            'title' => 'Modifier la région : ' . $region->name,
            'csrf_token' => $this->createCsrfToken(),
            'isEditing' => true
        ]);
    }

    /**
     * Update existing region
     */
    public function update(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token CSRF invalide');
            return $this->redirect('/regions');
        }
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        $validator = new Validator();
        $data = $request->request->all();

        // Same validation rules as create
        $rules = [
            'country_id' => 'required|numeric',
            'name' => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'coordinates_lat' => 'nullable|numeric|between:-90,90',
            'coordinates_lng' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|numeric|min:0|max:5000',
            'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
            'access_info' => 'nullable|max:1000',
            'parking_info' => 'nullable|max:1000'
        ];

        if (!$validator->validate($data, $rules)) {
            return $this->jsonError('Données invalides', 400, [
                'errors' => $validator->getErrors()
            ]);
        }

        try {
            $this->db->beginTransaction();

            // Add user context
            if ($this->auth && $this->auth->check()) {
                $data['updated_by'] = $this->auth->id();
            }

            // Update region
            $region = $this->regionService->updateRegion($region, $data);

            // Handle file uploads
            $this->handleFileUploads($request, $region);

            $this->db->commit();

            // Clear draft
            $this->clearUserDraft($request, "region_edit_{$id}");

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région mise à jour avec succès',
                'region' => $region->toArray(),
                'redirect' => "/regions/{$region->id}"
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region update error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la mise à jour: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete region and all related data
     */
    public function destroy(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        try {
            $this->db->beginTransaction();

            // Check if region has dependent data
            $dependencies = $this->regionService->checkDependencies($id);

            if (!empty($dependencies['sectors']) || !empty($dependencies['routes'])) {
                return $this->jsonError(
                    'Impossible de supprimer cette région car elle contient des secteurs et des voies. ' .
                        'Veuillez d\'abord supprimer ou déplacer le contenu.',
                    400,
                    ['dependencies' => $dependencies]
                );
            }

            // Delete associated media files
            $this->mediaService->deleteRegionMedia($id);

            // Delete region
            $this->regionService->deleteRegion($region);

            $this->db->commit();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region deletion error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la suppression: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get regions data for AJAX requests (map, autocomplete, etc.)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $filters = [
            'country_id' => $request->query->get('country_id'),
            'search' => $request->query->get('search'),
            'limit' => min((int) $request->query->get('limit', 50), 100)
        ];

        $regions = $this->regionService->getRegionsForApi($filters);

        return $this->jsonResponse([
            'regions' => $regions,
            'total' => count($regions)
        ]);
    }

    /**
     * Get weather data for a specific region
     */
    public function weather(Request $request): JsonResponse
    {
        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region || !$region->coordinates_lat || !$region->coordinates_lng) {
            return $this->jsonError('Région ou coordonnées non trouvées', 404);
        }

        try {
            $weatherData = $this->weatherService->getDetailedWeather(
                $region->coordinates_lat,
                $region->coordinates_lng
            );

            return $this->jsonResponse([
                'current' => $weatherData['current'],
                'forecast' => $weatherData['forecast'],
                'climbing_conditions' => $weatherData['climbing_conditions']
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Données météo indisponibles', 503);
        }
    }

    /**
     * Get upcoming events for a region
     */
    public function events(Request $request): JsonResponse
    {
        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        $events = $this->regionService->getUpcomingEvents($id, 10);

        return $this->jsonResponse([
            'events' => $events
        ]);
    }

    /**
     * Search regions for autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = min((int) $request->query->get('limit', 10), 20);

        if (strlen($query) < 2) {
            return $this->jsonResponse(['results' => []]);
        }

        $results = $this->regionService->searchRegions($query, $limit);

        return $this->jsonResponse([
            'results' => $results
        ]);
    }

    /**
     * Export region data (GPX, KML, etc.)
     */
    public function export(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        $format = $request->query->get('format', 'json');

        $region = $this->regionService->getRegionWithAllRelations($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        try {
            switch ($format) {
                case 'gpx':
                    return $this->exportGpx($region);
                case 'kml':
                    return $this->exportKml($region);
                case 'geojson':
                    return $this->exportGeoJson($region);
                default:
                    return $this->exportJson($region);
            }
        } catch (\Exception $e) {
            return $this->jsonError('Erreur lors de l\'export: ' . $e->getMessage(), 500);
        }
    }

    // Helper methods

    protected function buildPageTitle(array $filters): string
    {
        $title = 'Régions d\'escalade';

        if (!empty($filters['country_id'])) {
            $country = $this->db->fetchOne(
                "SELECT name FROM climbing_countries WHERE id = ?",
                [$filters['country_id']]
            );
            if ($country) {
                $title = 'Régions de ' . $country['name'];
            }
        }

        if (!empty($filters['search'])) {
            $title .= ' - Recherche: ' . $filters['search'];
        }

        return $title;
    }

    protected function handleFileUploads(Request $request, Region $region): void
    {
        // Handle cover image
        $coverFile = $request->files->get('cover_image');
        if ($coverFile && $coverFile->isValid()) {
            $this->mediaService->uploadRegionCoverImage($region->id, $coverFile);
        }

        // Handle gallery images
        $galleryFiles = $request->files->get('gallery_images', []);
        foreach ($galleryFiles as $file) {
            if ($file && $file->isValid()) {
                $this->mediaService->uploadRegionGalleryImage($region->id, $file);
            }
        }
    }

    protected function validateCoordinates(array $data): void
    {
        if (empty($data['coordinates_lat']) && empty($data['coordinates_lng'])) {
            return;
        }

        if (!is_numeric($data['coordinates_lat']) || !is_numeric($data['coordinates_lng'])) {
            throw new \InvalidArgumentException('Les coordonnées doivent être numériques');
        }

        $lat = $data['coordinates_lat'] ?? null;
        $lng = $data['coordinates_lng'] ?? null;

        // Both must be provided or both must be empty
        if (($lat && !$lng) || (!$lat && $lng)) {
            throw new \InvalidArgumentException(
                'Les coordonnées latitude et longitude doivent être fournies ensemble'
            );
        }

        // If provided, they must be within Switzerland bounds (approximately)
        if ($lat && $lng) {
            $swissBounds = [
                'lat_min' => 45.8,
                'lat_max' => 47.9,
                'lng_min' => 5.9,
                'lng_max' => 10.6
            ];

            if (
                $lat < $swissBounds['lat_min'] || $lat > $swissBounds['lat_max'] ||
                $lng < $swissBounds['lng_min'] || $lng > $swissBounds['lng_max']
            ) {

                // Log warning but don't fail - coordinates might be valid for regions outside Switzerland
                error_log("Coordinates outside Switzerland bounds: lat={$lat}, lng={$lng}");
            }
        }
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->auth || !$this->auth->check()) {
            throw new \RuntimeException('Authentification requise', 401);
        }

        if (!$this->auth->user()->can($permission)) {
            throw new \RuntimeException('Permissions insuffisantes', 403);
        }
    }

    protected function clearUserDraft(Request $request, string $draftKey): void
    {
        // This would typically clear localStorage via JavaScript
        // For now, we'll just note it for the frontend
    }

    protected function jsonResponse(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            ...$extra
        ], $status);
    }

    // Export methods

    protected function exportGpx(Region $region): Response
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $gpx = $this->regionService->generateGpxExport($region, $sectors);

        return new Response($gpx, 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.gpx"'
        ]);
    }

    protected function exportKml(Region $region): Response
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $kml = $this->regionService->generateKmlExport($region, $sectors);

        return new Response($kml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.kml"'
        ]);
    }

    protected function exportGeoJson(Region $region): JsonResponse
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $geoJson = $this->regionService->generateGeoJsonExport($region, $sectors);

        return new JsonResponse($geoJson, 200, [
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.geojson"'
        ]);
    }

    protected function exportJson(Region $region): JsonResponse
    {
        $data = $this->regionService->getRegionExportData($region->id);

        return new JsonResponse($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.json"'
        ]);
    }
}
