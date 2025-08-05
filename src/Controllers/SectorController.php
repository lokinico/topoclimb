<?php
// src/Controllers/SectorController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\ValidationService;
use TopoclimbCH\Core\Filtering\SectorFilter;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Month;
use TopoclimbCH\Models\Exposure;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Exceptions\ServiceException;
use TopoclimbCH\Core\Security\CsrfManager;

class SectorController extends BaseController
{
    /**
     * @var SectorService
     */
    private SectorService $sectorService;

    /**
     * @var MediaService
     */
    private MediaService $mediaService;

    /**
     * @var ValidationService
     */
    private ValidationService $validationService;


    /**
     * Expositions valides pour validation
     */
    private const VALID_EXPOSURES = ['N', 'S', 'E', 'W', 'NE', 'NW', 'SE', 'SW'];

    /**
     * Qualités valides pour les mois
     */
    private const VALID_MONTH_QUALITIES = ['poor', 'fair', 'good', 'excellent'];

    /**
     * Types MIME autorisés pour upload
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/pdf'
    ];

    /**
     * Limites géographiques Suisse
     */
    private const SWISS_LAT_MIN = 45.8;
    private const SWISS_LAT_MAX = 47.9;
    private const SWISS_LNG_MIN = 5.9;
    private const SWISS_LNG_MAX = 10.6;

    /**
     * Constructor
     */
    public function __construct(
        View $view,
        Session $session,
        SectorService $sectorService,
        MediaService $mediaService,
        ValidationService $validationService,
        Database $db,
        CsrfManager $csrfManager,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->sectorService = $sectorService;
        $this->mediaService = $mediaService;
        $this->validationService = $validationService;
        $this->db = $db;
    }

    /**
     * List all sectors
     */
    public function index(Request $request): Response
    {
        try {
            // TODO: Restauré - Vérification permission de lecture
            if (!$this->canViewSectors()) {
                $this->session->flash('error', 'Accès non autorisé');
                return Response::redirect('/');
            }
            
            // TODO: Restauré - Utilisation du système de filtrage complet
            $filter = new SectorFilter($request->query->all());

            // TODO: Restauré - Utilisation du SectorService avec pagination complète
            $paginatedSectors = $this->sectorService->getPaginatedSectors($filter);
            
            // Get sort parameters from filter or request
            $sortBy = $request->query->get('sort_by', 'name');
            $sortDir = $request->query->get('sort_dir', 'ASC');

            // TODO: Restauré - Récupérer les données pour les filtres complets
            $regions = $this->db->fetchAll(
                "SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
            );
            
            // Récupérer les sites pour les filtres
            $sites = $this->db->fetchAll(
                "SELECT id, name FROM climbing_sites WHERE active = 1 ORDER BY name ASC"
            );
            
            // Récupérer les expositions
            $exposures = $this->db->fetchAll(
                "SELECT id, code, name FROM climbing_exposures ORDER BY code ASC"
            );
            
            // Récupérer les mois avec qualité
            $months = $this->db->fetchAll(
                "SELECT id, code, name FROM climbing_months ORDER BY id ASC"
            );

            return $this->render('sectors/index', [
                'sectors' => $paginatedSectors,
                'filter' => $filter,
                'regions' => $regions ?: [],
                'sites' => $sites,
                'exposures' => $exposures,
                'months' => $months,
                'currentUrl' => $request->getPathInfo(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir
            ]);
        } catch (\Exception $e) {
            error_log("SectorIndex Error: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des secteurs');
            return $this->render('sectors/index', [
                'sectors' => [],
                'error' => 'Erreur de chargement'
            ]);
        }
    }

    /**
     * Show a single sector
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');

        // Validation ID
        if (!$id || !is_numeric($id) || $id <= 0) {
            $this->session->flash('error', 'ID du secteur invalide');
            return Response::redirect('/sectors');
        }

        // Vérification permission de lecture
        if (!$this->canViewSectors()) {
            $this->session->flash('error', 'Accès non autorisé');
            return Response::redirect('/');
        }

        try {
            $sector = $this->sectorService->getSectorById((int) $id);

            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');
            }

            // Récupérer les routes du secteur de manière sécurisée
            $routes = $this->getSectorRoutesSecure((int) $id);
            $routes_count = count($routes);

            // Récupérer les expositions de manière sécurisée
            $exposures = $this->getSectorExposuresSecure((int) $id);

            // Récupérer les médias de manière sécurisée
            $media = $this->mediaService->getMediaForEntity('sector', (int) $id);

            // Calculer les statistiques de manière sécurisée
            $stats = $this->calculateSectorStatsSecure($routes);

            return $this->render('sectors/show', [
                'title' => htmlspecialchars($sector->name, ENT_QUOTES, 'UTF-8'),
                'sector' => $sector,
                'exposures' => $exposures,
                'media' => $media,
                'routes' => $routes,
                'routes_count' => $routes_count,
                'min_difficulty' => $stats['min_difficulty'] ?? null,
                'max_difficulty' => $stats['max_difficulty'] ?? null,
                'avg_route_length' => $stats['avg_length'] ?? null,
                'route_styles' => array_unique(array_filter(array_column($routes, 'style')))
            ]);
        } catch (\Exception $e) {
            error_log("SectorShow Error: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement du secteur');
            return Response::redirect('/sectors');
        }
    }

    /**
     * Display create sector form
     */
    public function create(Request $request): Response
    {
        // Vérification permission de création
        if (!$this->canCreateSectors()) {
            $this->session->flash('error', 'Vous n\'avez pas les permissions pour créer un secteur');
            return Response::redirect('/sectors');
        }

        try {
            // Get data for form selections avec validation
            $regions = $this->getValidRegions();
            $sites = $this->getValidSites();
            $exposures = $this->getValidExposures();
            $months = $this->getValidMonths();

            // Précharger des valeurs par défaut sécurisées
            $sector = [
                'color' => '#FF0000',
                'active' => 1
            ];

            // Validation des paramètres URL
            if ($request->query->has('region_id')) {
                $regionId = (int) $request->query->get('region_id');
                if ($this->isValidRegionId($regionId)) {
                    $sector['region_id'] = $regionId;
                }
            }

            if ($request->query->has('site_id')) {
                $siteId = (int) $request->query->get('site_id');
                if ($this->isValidSiteId($siteId)) {
                    $sector['site_id'] = $siteId;
                }
            }

            return $this->render('sectors/form', [
                'title' => 'Créer un nouveau secteur',
                'sector' => $sector,
                'regions' => $regions,
                'sites' => $sites,
                'exposures' => $exposures,
                'months' => $months,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log("SectorCreate Error: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement du formulaire');
            return Response::redirect('/sectors');
        }
    }

    /**
     * Display create sector form (version test sans authentification)
     */
    public function testCreate(Request $request): Response
    {
        try {
            // Get data for form selections avec validation
            $regions = $this->getValidRegions();
            $sites = $this->getValidSites();
            $exposures = $this->getValidExposures();
            $months = $this->getValidMonths();

            // Précharger des valeurs par défaut sécurisées
            $sector = [
                'color' => '#FF0000',
                'active' => 1
            ];

            return $this->render('sectors/form', [
                'title' => 'Créer un nouveau secteur',
                'sector' => $sector,
                'regions' => $regions,
                'sites' => $sites,
                'exposures' => $exposures,
                'months' => $months,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            // Version fallback avec formulaire HTML simple
            $html = '<form method="post">
                <input type="hidden" name="csrf_token" value="test">
                <input type="text" name="name" placeholder="Nom">
                <textarea name="description" placeholder="Description"></textarea>
                <select name="region_id"><option value="1">Region 1</option></select>
                <select name="site_id"><option value="1">Site 1</option></select>
                <input type="color" name="color" value="#FF0000">
                <input type="checkbox" name="active" value="1" checked>
                <button type="submit">Créer</button>
            </form>';
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * Store a new sector
     */
    public function store(Request $request): Response
    {
        // Vérification permission de création
        if (!$this->canCreateSectors()) {
            $this->session->flash('error', 'Vous n\'avez pas les permissions pour créer un secteur');
            return Response::redirect('/sectors');
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sectors/create');
        }

        // Get form data
        $data = $request->request->all();

        // Validation complète des données
        $validationErrors = $this->validateSectorData($data);
        if (!empty($validationErrors)) {
            $this->session->flash('error', 'Erreurs de validation : ' . implode(', ', $validationErrors));
            return Response::redirect('/sectors/create');
        }

        // Vérification unicité du code
        if ($this->sectorCodeExists($data['code'], $data['site_id'] ?? null)) {
            $this->session->flash('error', 'Ce code secteur existe déjà pour ce site');
            return Response::redirect('/sectors/create');
        }

        try {
            // Add the current user ID de manière sécurisée
            $userId = $this->getCurrentUserId();
            if (!$userId) {
                throw new \Exception('Utilisateur non authentifié');
            }

            // Start transaction
            if (!$this->db->beginTransaction()) {
                throw new \Exception('Impossible de démarrer la transaction');
            }

            // Prepare data for insertion de manière sécurisée
            $sectorData = $this->prepareSectorData($data, $userId);

            // Insert the sector
            $sectorId = $this->db->insert('climbing_sectors', $sectorData);

            if (!$sectorId) {
                throw new \Exception('Erreur lors de la création du secteur');
            }

            // Handle exposures de manière sécurisée
            if (!empty($data['exposures'])) {
                $this->handleSectorExposures($sectorId, $data['exposures'], $data['primary_exposure'] ?? null);
            }

            // Handle months de manière sécurisée
            if (!empty($data['months'])) {
                $this->handleSectorMonths($sectorId, $data['months']);
            }

            // Traiter le média de manière sécurisée
            $this->handleSectorMediaUpload($sectorId, $data, $userId);

            if (!$this->db->commit()) {
                throw new \Exception('Échec lors de l\'enregistrement final');
            }

            $this->session->flash('success', 'Secteur créé avec succès');
            return Response::redirect('/sectors/' . $sectorId);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SectorStore Error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création : ' . $e->getMessage());
            return Response::redirect('/sectors/create');
        }
    }

    /**
     * Display edit sector form
     */
    public function edit(Request $request): Response
    {
        $id = $request->attributes->get('id');

        // Validation ID
        if (!$id || !is_numeric($id) || $id <= 0) {
            $this->session->flash('error', 'ID du secteur invalide');
            return Response::redirect('/sectors');
        }

        // Vérification permission de modification
        if (!$this->canEditSectors()) {
            $this->session->flash('error', 'Vous n\'avez pas les permissions pour modifier ce secteur');
            return Response::redirect('/sectors');
        }

        try {
            $sector = $this->sectorService->getSectorById((int) $id);

            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');
            }

            // Get data for form selections de manière sécurisée
            $regions = $this->getValidRegions();
            $books = $this->getValidBooks();
            $exposures = $this->getValidExposures();
            $months = $this->getValidMonths();

            // Get current exposures for this sector de manière sécurisée
            $sectorExposures = $this->getSectorExposuresSecure((int) $id);
            $currentExposures = array_column($sectorExposures, 'exposure_id');
            $primaryExposure = null;

            foreach ($sectorExposures as $exposure) {
                if ($exposure['is_primary']) {
                    $primaryExposure = $exposure['exposure_id'];
                    break;
                }
            }

            // Get months data de manière sécurisée
            $sectorMonths = $this->getSectorMonthsSecure((int) $id);
            $monthsData = [];

            foreach ($sectorMonths as $month) {
                $monthsData[$month['month_id']] = [
                    'quality' => $month['quality'],
                    'notes' => htmlspecialchars($month['notes'] ?? '', ENT_QUOTES, 'UTF-8')
                ];
            }

            // Récupérer les médias de manière sécurisée
            $media = $this->mediaService->getMediaForEntity('sector', (int) $id);

            return $this->render('sectors/form', [
                'title' => 'Modifier le secteur ' . htmlspecialchars($sector->name, ENT_QUOTES, 'UTF-8'),
                'sector' => $sector,
                'regions' => $regions,
                'sites' => $sites,
                'exposures' => $exposures,
                'months' => $months,
                'currentExposures' => $currentExposures,
                'primaryExposure' => $primaryExposure,
                'monthsData' => $monthsData,
                'media' => $media,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log("SectorEdit Error: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement du formulaire');
            return Response::redirect('/sectors');
        }
    }

    /**
     * Update a sector
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        // Validation ID
        if (!$id || !is_numeric($id) || $id <= 0) {
            $this->session->flash('error', 'ID du secteur invalide');
            return Response::redirect('/sectors');
        }

        // Vérification permission de modification
        if (!$this->canEditSectors()) {
            $this->session->flash('error', 'Vous n\'avez pas les permissions pour modifier ce secteur');
            return Response::redirect('/sectors');
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sectors/' . $id . '/edit');
        }

        // Get form data
        $data = $request->request->all();

        // Validation complète des données
        $validationErrors = $this->validateSectorData($data, (int) $id);
        if (!empty($validationErrors)) {
            $this->session->flash('error', 'Erreurs de validation : ' . implode(', ', $validationErrors));
            return Response::redirect('/sectors/' . $id . '/edit');
        }

        // Vérification unicité du code (en excluant le secteur actuel)
        if ($this->sectorCodeExists($data['code'], $data['site_id'] ?? null, (int) $id)) {
            $this->session->flash('error', 'Ce code secteur existe déjà pour ce site');
            return Response::redirect('/sectors/' . $id . '/edit');
        }

        try {
            // Get current user ID de manière sécurisée
            $userId = $this->getCurrentUserId();
            if (!$userId) {
                throw new \Exception('Utilisateur non authentifié');
            }

            // Begin transaction
            if (!$this->db->beginTransaction()) {
                throw new \Exception('Impossible de démarrer la transaction');
            }

            // Prepare update data de manière sécurisée
            $updateData = $this->prepareSectorData($data, $userId, true);

            // Update sector
            $success = $this->db->update('climbing_sectors', $updateData, 'id = ?', [(int) $id]);

            if (!$success) {
                throw new \Exception('Échec de la mise à jour du secteur');
            }

            // Handle exposures de manière sécurisée
            $this->updateSectorExposures((int) $id, $data['exposures'] ?? [], $data['primary_exposure'] ?? null);

            // Handle months de manière sécurisée
            $this->updateSectorMonths((int) $id, $data['months'] ?? []);

            // Traiter le média de manière sécurisée
            $this->handleSectorMediaUpload((int) $id, $data, $userId);

            if (!$this->db->commit()) {
                throw new \Exception('Échec lors de l\'enregistrement final');
            }

            $this->session->flash('success', 'Secteur mis à jour avec succès');
            return Response::redirect('/sectors/' . $id);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("SectorUpdate Error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            return Response::redirect('/sectors/' . $id . '/edit');
        }
    }

    /**
     * Delete a sector
     */
    public function delete(Request $request): Response
    {
        $id = $request->attributes->get('id');

        // Validation ID
        if (!$id || !is_numeric($id) || $id <= 0) {
            $this->session->flash('error', 'ID du secteur invalide');
            return Response::redirect('/sectors');
        }

        // Vérification permission de suppression
        if (!$this->canDeleteSectors()) {
            $this->session->flash('error', 'Vous n\'avez pas les permissions pour supprimer ce secteur');
            return Response::redirect('/sectors');
        }

        // Check if it's a POST request with confirmation
        if ($request->getMethod() !== 'POST') {
            try {
                $sector = $this->sectorService->getSectorById((int) $id);

                if (!$sector) {
                    $this->session->flash('error', 'Secteur non trouvé');
                    return Response::redirect('/sectors');
                }

                // Vérifications de dépendances de manière sécurisée
                $dependencies = $this->checkSectorDependencies((int) $id);

                // Show confirmation page avec informations sur les dépendances
                return $this->render('sectors/delete', [
                    'title' => 'Supprimer le secteur ' . htmlspecialchars($sector->name, ENT_QUOTES, 'UTF-8'),
                    'sector' => $sector,
                    'dependencies' => $dependencies,
                    'csrf_token' => $this->createCsrfToken()
                ]);
            } catch (\Exception $e) {
                error_log("SectorDeleteForm Error: " . $e->getMessage());
                $this->session->flash('error', 'Une erreur est survenue lors du chargement');
                return Response::redirect('/sectors');
            }
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sectors/' . $id . '/delete');
        }

        try {
            // Vérifier si le secteur existe
            $sector = $this->sectorService->getSectorById((int) $id);
            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');
            }

            // Vérification finale des dépendances
            $dependencies = $this->checkSectorDependencies((int) $id);
            if ($dependencies['canDelete'] === false) {
                $this->session->flash('error', 'Impossible de supprimer : ' . $dependencies['reason']);
                return Response::redirect('/sectors/' . $id . '/delete');
            }

            // Begin transaction
            if (!$this->db->beginTransaction()) {
                throw new \Exception('Impossible de démarrer la transaction');
            }

            // Suppression sécurisée avec cascade contrôlée
            $this->deleteSectorSecurely((int) $id);

            if (!$this->db->commit()) {
                throw new \Exception('Échec lors de la suppression finale');
            }

            $this->session->flash('success', 'Secteur supprimé avec succès');
            return Response::redirect('/sectors');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SectorDelete Error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            return Response::redirect('/sectors/' . $id . '/delete');
        }
    }

    /**
     * Destroy a sector (alias for delete method)
     */
    public function destroy(Request $request): Response
    {
        return $this->delete($request);
    }

    /**
     * API endpoint pour récupérer les voies d'un secteur (SÉCURISÉ)
     */
    public function getRoutes(Request $request): Response
    {
        // Vérification authentification pour API
        if (!$this->isAuthenticated()) {
            return Response::json([
                'success' => false,
                'error' => 'Authentification requise'
            ], 401);
        }

        $id = $request->attributes->get('id');

        // Validation ID
        if (!$id || !is_numeric($id) || $id <= 0) {
            return Response::json([
                'success' => false,
                'error' => 'ID du secteur invalide'
            ], 400);
        }

        // Vérification permission de lecture
        if (!$this->canViewSectors()) {
            return Response::json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        try {
            // Vérifier que le secteur existe
            $sector = $this->sectorService->getSectorById((int) $id);
            if (!$sector) {
                return Response::json([
                    'success' => false,
                    'error' => 'Secteur non trouvé'
                ], 404);
            }

            // Récupérer les routes de manière sécurisée
            $routes = $this->getSectorRoutesSecure((int) $id);

            // Enrichir les données des voies de manière sécurisée
            $enrichedRoutes = [];
            foreach ($routes as $route) {
                $enrichedRoutes[] = $this->enrichRouteDataSecure($route);
            }

            // Calculer les statistiques de manière sécurisée
            $stats = $this->calculateSectorStatsSecure($routes);

            return Response::json([
                'success' => true,
                'data' => [
                    'routes' => $enrichedRoutes,
                    'stats' => $stats,
                    'total_count' => count($routes)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("API getRoutes error for sector $id: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors du chargement des voies'
            ], 500);
        }
    }

    // ===================== API METHODS =====================

    /**
     * API: Liste de tous les secteurs
     */
    public function apiIndex(Request $request): Response
    {
        try {
            $limit = min((int)($request->query->get('limit') ?? 100), 500);
            $offset = max((int)($request->query->get('offset') ?? 0), 0);
            
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.altitude, s.coordinates_lat, s.coordinates_lng,
                        si.name as site_name, r.name as region_name
                 FROM climbing_sectors s
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.active = 1
                 ORDER BY s.name ASC
                 LIMIT ? OFFSET ?",
                [$limit, $offset]
            );

            return Response::json([
                'success' => true,
                'data' => $sectors,
                'count' => count($sectors),
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            error_log("SectorController::apiIndex error: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors du chargement des secteurs'
            ], 500);
        }
    }

    /**
     * API: Recherche de secteurs
     */
    public function apiSearch(Request $request): Response
    {
        try {
            $query = trim($request->query->get('q', ''));
            $limit = min((int)($request->query->get('limit') ?? 50), 200);
            
            if (strlen($query) < 2) {
                return Response::json([
                    'success' => true,
                    'data' => [],
                    'query' => $query
                ]);
            }

            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.altitude, s.coordinates_lat, s.coordinates_lng,
                        si.name as site_name, r.name as region_name
                 FROM climbing_sectors s
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.active = 1 
                   AND (s.name LIKE ? OR si.name LIKE ? OR r.name LIKE ?)
                 ORDER BY s.name ASC
                 LIMIT ?",
                ["%$query%", "%$query%", "%$query%", $limit]
            );

            return Response::json([
                'success' => true,
                'data' => $sectors,
                'query' => $query,
                'count' => count($sectors)
            ]);
        } catch (\Exception $e) {
            error_log("SectorController::apiSearch error: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    // ===================== MÉTHODES DE SÉCURITÉ =====================

    /**
     * Validate sector data
     */
    private function validateSectorData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        // Validation nom
        if (empty($data['name']) || strlen(trim($data['name'])) < 2 || strlen(trim($data['name'])) > 255) {
            $errors[] = 'Le nom doit contenir entre 2 et 255 caractères';
        }

        // Validation code
        if (empty($data['code']) || !preg_match('/^[A-Z0-9_-]{1,20}$/i', $data['code'])) {
            $errors[] = 'Le code doit contenir uniquement des lettres, chiffres, tirets et underscores (max 20 caractères)';
        }

        // Validation site_id
        if (empty($data['site_id']) || !is_numeric($data['site_id'])) {
            $errors[] = 'Le site est obligatoire';
        } elseif (!$this->isValidSiteId((int) $data['site_id'])) {
            $errors[] = 'Site invalide';
        }

        // Validation coordonnées GPS Suisse
        if (!empty($data['coordinates_lat'])) {
            $lat = (float) $data['coordinates_lat'];
            if ($lat < self::SWISS_LAT_MIN || $lat > self::SWISS_LAT_MAX) {
                $errors[] = 'Latitude hors des limites suisses (' . self::SWISS_LAT_MIN . ' - ' . self::SWISS_LAT_MAX . ')';
            }
        }

        if (!empty($data['coordinates_lng'])) {
            $lng = (float) $data['coordinates_lng'];
            if ($lng < self::SWISS_LNG_MIN || $lng > self::SWISS_LNG_MAX) {
                $errors[] = 'Longitude hors des limites suisses (' . self::SWISS_LNG_MIN . ' - ' . self::SWISS_LNG_MAX . ')';
            }
        }

        // Validation altitude
        if (!empty($data['altitude'])) {
            $altitude = (int) $data['altitude'];
            if ($altitude < 200 || $altitude > 4000) {
                $errors[] = 'Altitude doit être entre 200m et 4000m';
            }
        }

        // Validation temps d'accès
        if (!empty($data['access_time'])) {
            $accessTime = (int) $data['access_time'];
            if ($accessTime < 5 || $accessTime > 600) {
                $errors[] = 'Temps d\'accès doit être entre 5 et 600 minutes';
            }
        }

        // Validation expositions
        if (!empty($data['exposures'])) {
            if (!is_array($data['exposures'])) {
                $errors[] = 'Format d\'expositions invalide';
            } else {
                foreach ($data['exposures'] as $exposureId) {
                    if (!$this->isValidExposureId($exposureId)) {
                        $errors[] = 'Exposition invalide : ' . $exposureId;
                    }
                }
            }
        }

        // Validation mois
        if (!empty($data['months'])) {
            if (!is_array($data['months'])) {
                $errors[] = 'Format de mois invalide';
            } else {
                foreach ($data['months'] as $monthId => $monthData) {
                    if (!$this->isValidMonthId($monthId)) {
                        $errors[] = 'Mois invalide : ' . $monthId;
                    }
                    if (isset($monthData['quality']) && !in_array($monthData['quality'], self::VALID_MONTH_QUALITIES)) {
                        $errors[] = 'Qualité de mois invalide : ' . $monthData['quality'];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if user can view sectors
     */
    private function canViewSectors(): bool
    {
        return $this->isAuthenticated(); // Tous les utilisateurs connectés peuvent voir
    }

    /**
     * Check if user can create sectors
     */
    private function canCreateSectors(): bool
    {
        return $this->hasRole(['0', '1', '2']); // Admin, Moderator, Editor
    }

    /**
     * Check if user can edit sectors
     */
    private function canEditSectors(): bool
    {
        return $this->hasRole(['0', '1', '2']); // Admin, Moderator, Editor
    }

    /**
     * Check if user can delete sectors
     */
    private function canDeleteSectors(): bool
    {
        return $this->hasRole(['0', '1']); // Admin, Moderator seulement
    }

    /**
     * Check if code exists for this site (excluding current sector)
     */
    private function sectorCodeExists(string $code, ?int $siteId, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) as count FROM climbing_sectors WHERE code = ? AND site_id = ?";
        $params = [$code, $siteId];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchOne($query, $params);
        return (int) ($result['count'] ?? 0) > 0;
    }

    /**
     * Validate exposure ID against valid exposures
     */
    private function isValidExposureId($exposureId): bool
    {
        if (!is_numeric($exposureId)) return false;

        $exposure = $this->db->fetchOne(
            "SELECT code FROM climbing_exposures WHERE id = ?",
            [(int) $exposureId]
        );

        return $exposure && in_array($exposure['code'], self::VALID_EXPOSURES);
    }

    /**
     * Validate month ID
     */
    private function isValidMonthId($monthId): bool
    {
        return is_numeric($monthId) && $monthId >= 1 && $monthId <= 12;
    }

    /**
     * Validate region ID
     */
    private function isValidRegionId(int $regionId): bool
    {
        $region = $this->db->fetchOne(
            "SELECT id FROM climbing_regions WHERE id = ? AND active = 1",
            [$regionId]
        );
        return (bool) $region;
    }

    /**
     * Validate site ID
     */
    private function isValidSiteId(int $siteId): bool
    {
        $site = $this->db->fetchOne(
            "SELECT id FROM climbing_sites WHERE id = ? AND active = 1",
            [$siteId]
        );
        return (bool) $site;
    }

    /**
     * Get valid regions
     */
    private function getValidRegions(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC"
        );
    }

    /**
     * Get valid sites
     */
    private function getValidSites(): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.name, s.region_id, r.name as region_name 
             FROM climbing_sites s 
             LEFT JOIN climbing_regions r ON s.region_id = r.id 
             WHERE s.active = 1 
             ORDER BY r.name ASC, s.name ASC"
        );
    }

    /**
     * Get valid exposures
     */
    private function getValidExposures(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, code FROM climbing_exposures WHERE code IN ('" .
                implode("','", self::VALID_EXPOSURES) . "') ORDER BY sort_order ASC"
        );
    }

    /**
     * Get valid months
     */
    private function getValidMonths(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, short_name, month_number FROM climbing_months ORDER BY month_number ASC"
        );
    }

    /**
     * Prepare sector data for database
     */
    private function prepareSectorData(array $data, int $userId, bool $isUpdate = false): array
    {
        $sectorData = [
            'name' => trim($data['name']),
            'code' => strtoupper(trim($data['code'])),
            'site_id' => (int) $data['site_id'],
            'region_id' => !empty($data['region_id']) ? (int) $data['region_id'] : null,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'access_info' => !empty($data['access_info']) ? trim($data['access_info']) : null,
            'color' => $data['color'] ?? '#FF0000',
            'access_time' => !empty($data['access_time']) ? (int) $data['access_time'] : null,
            'altitude' => !empty($data['altitude']) ? (int) $data['altitude'] : null,
            'approach' => !empty($data['approach']) ? trim($data['approach']) : null,
            'height' => !empty($data['height']) ? (float) $data['height'] : null,
            'parking_info' => !empty($data['parking_info']) ? trim($data['parking_info']) : null,
            'coordinates_lat' => !empty($data['coordinates_lat']) ? (float) $data['coordinates_lat'] : null,
            'coordinates_lng' => !empty($data['coordinates_lng']) ? (float) $data['coordinates_lng'] : null,
            'coordinates_swiss_e' => !empty($data['coordinates_swiss_e']) ? trim($data['coordinates_swiss_e']) : null,
            'coordinates_swiss_n' => !empty($data['coordinates_swiss_n']) ? trim($data['coordinates_swiss_n']) : null,
            'active' => isset($data['active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId
        ];

        if (!$isUpdate) {
            $sectorData['created_at'] = date('Y-m-d H:i:s');
            $sectorData['created_by'] = $userId;
        }

        return $sectorData;
    }

    /**
     * Handle sector exposures securely
     */
    private function handleSectorExposures(int $sectorId, array $exposures, ?string $primaryExposure): void
    {
        foreach ($exposures as $exposureId) {
            if (!$this->isValidExposureId($exposureId)) {
                continue; // Skip invalid exposures
            }

            $isPrimary = ($primaryExposure && $primaryExposure == $exposureId) ? 1 : 0;
            $this->db->insert('climbing_sector_exposures', [
                'sector_id' => $sectorId,
                'exposure_id' => (int) $exposureId,
                'is_primary' => $isPrimary,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Update sector exposures securely
     */
    private function updateSectorExposures(int $sectorId, array $exposures, ?string $primaryExposure): void
    {
        // Delete existing exposures
        $this->db->delete('climbing_sector_exposures', 'sector_id = ?', [$sectorId]);

        // Add new exposures
        if (!empty($exposures)) {
            $this->handleSectorExposures($sectorId, $exposures, $primaryExposure);
        }
    }

    /**
     * Handle sector months securely
     */
    private function handleSectorMonths(int $sectorId, array $months): void
    {
        foreach ($months as $monthId => $monthData) {
            if (!$this->isValidMonthId($monthId) || !isset($monthData['quality'])) {
                continue; // Skip invalid months
            }

            if (!in_array($monthData['quality'], self::VALID_MONTH_QUALITIES)) {
                continue; // Skip invalid qualities
            }

            $this->db->insert('climbing_sector_months', [
                'sector_id' => $sectorId,
                'month_id' => (int) $monthId,
                'quality' => $monthData['quality'],
                'notes' => !empty($monthData['notes']) ? trim($monthData['notes']) : null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Update sector months securely
     */
    private function updateSectorMonths(int $sectorId, array $months): void
    {
        // Delete existing months
        $this->db->delete('climbing_sector_months', 'sector_id = ?', [$sectorId]);

        // Add new months
        if (!empty($months)) {
            $this->handleSectorMonths($sectorId, $months);
        }
    }

    /**
     * Handle sector media upload securely
     */
    private function handleSectorMediaUpload(int $sectorId, array $data, int $userId): void
    {
        try {
            $mediaFile = $_FILES['media_file'] ?? null;
            if (!$mediaFile || !isset($mediaFile['tmp_name']) || !is_uploaded_file($mediaFile['tmp_name'])) {
                return; // No file uploaded
            }

            // Validate file type
            if (!$this->isValidMediaFile($mediaFile)) {
                throw new \Exception('Type de fichier non autorisé');
            }

            // Validate file size (5MB max)
            if ($mediaFile['size'] > 5 * 1024 * 1024) {
                throw new \Exception('Fichier trop volumineux (max 5MB)');
            }

            $relationshipType = $data['media_relationship_type'] ?? 'gallery';
            if (!in_array($relationshipType, ['main', 'gallery', 'topo'])) {
                $relationshipType = 'gallery';
            }

            $mediaId = $this->mediaService->uploadMedia($mediaFile, [
                'title' => !empty($data['media_title']) ? trim($data['media_title']) : 'Image secteur',
                'description' => "Image pour le secteur: {$data['name']}",
                'is_public' => 1,
                'media_type' => 'image',
                'entity_type' => 'sector',
                'entity_id' => $sectorId,
                'relationship_type' => $relationshipType
            ], $userId);

            // Si c'est une image principale, mettre à jour les anciennes relations "main"
            if ($relationshipType === 'main' && $mediaId) {
                $this->db->update(
                    'climbing_media_relationships',
                    ['relationship_type' => 'gallery'],
                    'entity_type = ? AND entity_id = ? AND relationship_type = ? AND media_id != ?',
                    ['sector', $sectorId, 'main', $mediaId]
                );
            }
        } catch (\Exception $e) {
            error_log("Media upload error: " . $e->getMessage());
            // Ne pas faire échouer toute l'opération pour un problème de média
        }
    }

    /**
     * Validate media file
     */
    private function isValidMediaFile(array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * Get sector routes securely
     */
    private function getSectorRoutesSecure(int $sectorId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM climbing_routes WHERE sector_id = ? AND active = 1 ORDER BY number ASC, name ASC",
            [$sectorId]
        );
    }

    /**
     * Get sector exposures securely
     */
    private function getSectorExposuresSecure(int $sectorId): array
    {
        return $this->db->fetchAll(
            "SELECT e.*, se.is_primary FROM climbing_exposures e 
             JOIN climbing_sector_exposures se ON e.id = se.exposure_id 
             WHERE se.sector_id = ? AND e.code IN ('" . implode("','", self::VALID_EXPOSURES) . "')
             ORDER BY e.sort_order ASC",
            [$sectorId]
        );
    }

    /**
     * Get sector months securely
     */
    private function getSectorMonthsSecure(int $sectorId): array
    {
        return $this->db->fetchAll(
            "SELECT sm.*, m.name, m.month_number FROM climbing_sector_months sm
             JOIN climbing_months m ON sm.month_id = m.id
             WHERE sm.sector_id = ? AND sm.quality IN ('" . implode("','", self::VALID_MONTH_QUALITIES) . "')
             ORDER BY m.month_number ASC",
            [$sectorId]
        );
    }

    /**
     * Calculate sector stats securely
     */
    private function calculateSectorStatsSecure(array $routes): array
    {
        $stats = [];

        if (empty($routes)) {
            return $stats;
        }

        // Calcul des difficultés min/max de manière sécurisée
        $difficulties = array_filter(array_column($routes, 'difficulty'));
        if (!empty($difficulties)) {
            $stats['min_difficulty'] = min($difficulties);
            $stats['max_difficulty'] = max($difficulties);
        }

        // Calcul de la longueur moyenne de manière sécurisée
        $lengths = array_filter(array_map('floatval', array_column($routes, 'length')));
        if (!empty($lengths)) {
            $stats['avg_length'] = round(array_sum($lengths) / count($lengths));
        }

        return $stats;
    }

    /**
     * Enrich route data securely for API
     */
    private function enrichRouteDataSecure($route): array
    {
        return [
            'id' => (int) $route->id,
            'name' => htmlspecialchars($route->name ?? '', ENT_QUOTES, 'UTF-8'),
            'number' => $route->number ?? null,
            'difficulty' => htmlspecialchars($route->difficulty ?? '', ENT_QUOTES, 'UTF-8'),
            'beauty' => (int) ($route->beauty ?? 0),
            'style' => htmlspecialchars($route->style ?? '', ENT_QUOTES, 'UTF-8'),
            'length' => $route->length ? (float) $route->length : null,
            'equipment' => htmlspecialchars($route->equipment ?? '', ENT_QUOTES, 'UTF-8'),
            'comment' => htmlspecialchars($route->comment ?? '', ENT_QUOTES, 'UTF-8'),
            'lengthFormatted' => $route->length ? $route->length . 'm' : null,
            'ascents_count' => $this->getRouteAscentsCountSecure((int) $route->id)
        ];
    }

    /**
     * Get route ascents count securely
     */
    private function getRouteAscentsCountSecure(int $routeId): int
    {
        try {
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM user_ascents WHERE route_id = ?",
                [$routeId]
            );
            return max(0, (int) ($result['count'] ?? 0));
        } catch (\Exception $e) {
            error_log("Error counting ascents for route $routeId: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check sector dependencies before deletion
     */
    private function checkSectorDependencies(int $sectorId): array
    {
        $dependencies = [
            'canDelete' => true,
            'reason' => '',
            'routesCount' => 0,
            'ascentsCount' => 0,
            'mediaCount' => 0
        ];

        try {
            // Count routes
            $routesResult = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ?",
                [$sectorId]
            );
            $dependencies['routesCount'] = (int) ($routesResult['count'] ?? 0);

            // Count ascents via routes
            $ascentsResult = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM user_ascents ua 
                 JOIN climbing_routes r ON ua.route_id = r.id 
                 WHERE r.sector_id = ?",
                [$sectorId]
            );
            $dependencies['ascentsCount'] = (int) ($ascentsResult['count'] ?? 0);

            // Count media
            $mediaResult = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_media_relationships 
                 WHERE entity_type = 'sector' AND entity_id = ?",
                [$sectorId]
            );
            $dependencies['mediaCount'] = (int) ($mediaResult['count'] ?? 0);

            // Determine if deletion is allowed
            if ($dependencies['ascentsCount'] > 0) {
                $dependencies['canDelete'] = false;
                $dependencies['reason'] = "Ce secteur contient {$dependencies['ascentsCount']} ascensions d'utilisateurs";
            } elseif ($dependencies['routesCount'] > 10) {
                $dependencies['canDelete'] = false;
                $dependencies['reason'] = "Ce secteur contient {$dependencies['routesCount']} voies (limite: 10)";
            }
        } catch (\Exception $e) {
            error_log("Error checking dependencies for sector $sectorId: " . $e->getMessage());
            $dependencies['canDelete'] = false;
            $dependencies['reason'] = 'Erreur lors de la vérification des dépendances';
        }

        return $dependencies;
    }

    /**
     * Delete sector securely with controlled cascade
     */
    private function deleteSectorSecurely(int $sectorId): void
    {
        // Delete relations first
        $this->db->delete('climbing_sector_exposures', 'sector_id = ?', [$sectorId]);
        $this->db->delete('climbing_sector_months', 'sector_id = ?', [$sectorId]);

        // Handle media deletion securely
        $mediaRelations = $this->db->fetchAll(
            "SELECT media_id FROM climbing_media_relationships WHERE entity_type = 'sector' AND entity_id = ?",
            [$sectorId]
        );

        foreach ($mediaRelations as $relation) {
            $mediaId = (int) $relation['media_id'];

            // Delete media annotations
            $this->db->delete('climbing_media_annotations', 'media_id = ?', [$mediaId]);

            // Delete media relationships
            $this->db->delete('climbing_media_relationships', 'media_id = ?', [$mediaId]);

            // Delete media tags
            $this->db->delete('climbing_media_tags', 'media_id = ?', [$mediaId]);

            // Delete media itself
            $this->db->delete('climbing_media', 'id = ?', [$mediaId]);
        }

        // Delete routes if any (should be few based on dependency check)
        $routes = $this->db->fetchAll("SELECT id FROM climbing_routes WHERE sector_id = ?", [$sectorId]);
        foreach ($routes as $route) {
            $routeId = (int) $route['id'];

            // Note: Ascensions should not exist based on dependency check
            // But we check anyway for safety
            $this->db->delete('user_ascents', 'route_id = ?', [$routeId]);
            $this->db->delete('climbing_routes', 'id = ?', [$routeId]);
        }

        // Finally delete the sector
        $success = $this->db->delete('climbing_sectors', 'id = ?', [$sectorId]);

        if (!$success) {
            throw new \Exception('Échec de la suppression du secteur');
        }
    }

    /**
     * Get current user ID securely
     */
    private function getCurrentUserId(): ?int
    {
        return $this->auth ? $this->auth->id() : null;
    }

    /**
     * Check if user is authenticated
     */
    private function isAuthenticated(): bool
    {
        return $this->auth ? $this->auth->check() : false;
    }

    /**
     * Check if user has specific role(s)
     */
    private function hasRole(array $allowedRoles): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userRole = (string)$this->auth->role();
        return in_array($userRole, $allowedRoles);
    }
}
