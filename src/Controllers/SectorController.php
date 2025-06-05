<?php
// src/Controllers/SectorController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;  
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Filtering\SectorFilter;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Month;
use TopoclimbCH\Models\Exposure;
use TopoclimbCH\Models\Route; /
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
     * @var Database
     */
    private Database $db;

    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     * @param SectorService $sectorService
     * @param MediaService $mediaService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        SectorService $sectorService,
        MediaService $mediaService,
        Database $db,
        CsrfManager $csrfManager
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->sectorService = $sectorService;
        $this->mediaService = $mediaService;
        $this->db = $db;
    }

    /**
     * List all sectors
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            // Créer le filtre à partir des paramètres de requête
            $filter = new SectorFilter($request->query->all());

            // Récupérer la page courante
            $page = (int) $request->query->get('page', 1);
            $perPage = (int) $request->query->get('per_page', 20);

            // Obtenir le champ et la direction de tri
            $sortBy = $request->query->get('sort_by', 'name');
            $sortDir = $request->query->get('sort_dir', 'ASC');

            // Paginer les résultats filtrés
            $paginatedSectors = Sector::filterAndPaginate(
                $filter,
                $page,
                $perPage,
                $sortBy,
                $sortDir
            );

            // Récupérer les données pour les filtres
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $exposures = Exposure::getAllSorted();
            $months = Month::getAllSorted();

            return $this->render('sectors/index', [
                'sectors' => $paginatedSectors,
                'filter' => $filter,
                'regions' => $regions,
                'exposures' => $exposures,
                'months' => $months,
                'currentUrl' => $request->getPathInfo(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des secteurs: ' . $e->getMessage());
            return $this->render('sectors/index', [
                'sectors' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show a single sector
     *
     * @param Request $request
     * @return Response
     */

    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');
        error_log("DEBUG show() - ID reçu: " . ($id ?? 'NULL'));

        if (!$id) {
            error_log("DEBUG show() - Pas d'ID, redirection");
            $this->session->flash('error', 'ID du secteur non spécifié');
            return Response::redirect('/sectors');
        }

        try {
            error_log("DEBUG show() - Appel getSectorById pour ID: " . $id);
            $sector = $this->sectorService->getSectorById((int) $id);
            error_log("DEBUG show() - Secteur trouvé: " . ($sector ? 'OUI' : 'NON'));

            if (!$sector) {
                error_log("DEBUG show() - Secteur non trouvé, redirection");
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');
            }

            // Récupérer les routes du secteur
            $routes = Route::where(['sector_id' => $id, 'active' => 1]);
            $routes_count = count($routes);

            // Récupérer les expositions
            $exposures = $this->db->fetchAll(
                "SELECT e.* FROM climbing_exposures e 
             JOIN climbing_sector_exposures se ON e.id = se.exposure_id 
             WHERE se.sector_id = ?",
                [(int)$id]
            );

            // Récupérer les médias
            $media = $this->mediaService->getMediaForEntity('sector', (int)$id);

            // Calculer les statistiques
            $stats = $this->calculateSectorStats($routes);

            return $this->render('sectors/show', [
                'title' => $sector['name'],
                'sector' => $sector,
                'exposures' => $exposures,
                'media' => $media,
                'routes' => $routes,
                'routes_count' => $routes_count,
                'min_difficulty' => $stats['min_difficulty'] ?? null,
                'max_difficulty' => $stats['max_difficulty'] ?? null,
                'avg_route_length' => $stats['avg_length'] ?? null,
                'route_styles' => array_unique(array_column($routes, 'style'))
            ]);
        } catch (\Exception $e) {
            error_log("DEBUG show() - Exception: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/sectors');
        }
    }

    /**
     * Calculer les statistiques du secteur
     */
    private function calculateSectorStats(array $routes): array
    {
        $stats = [];

        if (empty($routes)) {
            return $stats;
        }

        // Calcul des difficultés min/max
        $difficulties = array_column($routes, 'difficulty');
        $difficulties = array_filter($difficulties); // Enlever les valeurs vides
        if (!empty($difficulties)) {
            $stats['min_difficulty'] = min($difficulties);
            $stats['max_difficulty'] = max($difficulties);
        }

        // Calcul de la longueur moyenne
        $lengths = array_column($routes, 'length');
        $lengths = array_filter($lengths); // Enlever les valeurs nulles
        if (!empty($lengths)) {
            $stats['avg_length'] = round(array_sum($lengths) / count($lengths));
        }

        return $stats;
    }

    /**
     * Display create sector form
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        try {
            // Get data for form selections
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $books = $this->db->fetchAll("SELECT id, name FROM climbing_books WHERE active = 1 ORDER BY name ASC");
            $exposures = $this->db->fetchAll("SELECT id, name, code FROM climbing_exposures ORDER BY sort_order ASC");
            $months = $this->db->fetchAll("SELECT id, name, short_name FROM climbing_months ORDER BY month_number ASC");

            // Précharger des valeurs par défaut
            $sector = [
                'color' => '#FF0000',
                'active' => 1
            ];

            // Si un region_id est spécifié, préconfigurer le secteur
            if ($request->query->has('region_id')) {
                $sector['region_id'] = (int) $request->query->get('region_id');
            }

            // Si un book_id est spécifié
            if ($request->query->has('book_id')) {
                $sector['book_id'] = (int) $request->query->get('book_id');
            }

            return $this->render('sectors/form', [
                'title' => 'Créer un nouveau secteur',
                'sector' => $sector,
                'regions' => $regions,
                'books' => $books,
                'exposures' => $exposures,
                'months' => $months,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/sectors');  // ✅ CORRECTION
        }
    }

    /**
     * Store a new sector
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sectors/create');  // ✅ CORRECTION
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['name']) || empty($data['code']) || empty($data['book_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return Response::redirect('/sectors/create');  // ✅ CORRECTION
        }

        try {
            // Add the current user ID
            $data['created_by'] = $_SESSION['auth_user_id'] ?? $this->session->get('user_id') ?? 1;

            // Start transaction
            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sectors/create');  // ✅ CORRECTION
            }

            // Prepare data for insertion
            $sectorData = [
                'name' => $data['name'],
                'code' => $data['code'],
                'book_id' => $data['book_id'],
                'region_id' => $data['region_id'] ?? null,
                'description' => $data['description'] ?? null,
                'access_info' => $data['access_info'] ?? null,
                'color' => $data['color'] ?? '#FF0000',
                'access_time' => !empty($data['access_time']) ? (int)$data['access_time'] : null,
                'altitude' => !empty($data['altitude']) ? (int)$data['altitude'] : null,
                'approach' => $data['approach'] ?? null,
                'height' => !empty($data['height']) ? (float)$data['height'] : null,
                'parking_info' => $data['parking_info'] ?? null,
                'coordinates_lat' => !empty($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => !empty($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'coordinates_swiss_e' => $data['coordinates_swiss_e'] ?? null,
                'coordinates_swiss_n' => $data['coordinates_swiss_n'] ?? null,
                'active' => isset($data['active']) ? 1 : 0,
                'created_by' => $data['created_by'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Insert the sector
            $sectorId = $this->db->insert('climbing_sectors', $sectorData);

            if (!$sectorId) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la création du secteur');
                return Response::redirect('/sectors/create');  // ✅ CORRECTION
            }

            // Handle exposures if provided
            if (!empty($data['exposures'])) {
                foreach ($data['exposures'] as $exposureId) {
                    $isPrimary = ($data['primary_exposure'] ?? 0) == $exposureId ? 1 : 0;
                    $this->db->insert('climbing_sector_exposures', [
                        'sector_id' => (int)$sectorId,
                        'exposure_id' => (int)$exposureId,
                        'is_primary' => $isPrimary,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            // Handle months if provided
            if (!empty($data['months'])) {
                foreach ($data['months'] as $monthId => $monthData) {
                    if (isset($monthData['quality'])) {
                        $this->db->insert('climbing_sector_months', [
                            'sector_id' => (int)$sectorId,
                            'month_id' => (int)$monthId,
                            'quality' => $monthData['quality'],
                            'notes' => $monthData['notes'] ?? null,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            // Traiter le média si présent (nouvelle approche unifiée)
            $mediaFile = $_FILES['media_file'] ?? null;
            if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name'])) {
                $this->mediaService->uploadMedia($mediaFile, [
                    'title' => $data['media_title'] ?? $data['name'],
                    'description' => "Image pour le secteur: {$data['name']}",
                    'is_public' => 1,
                    'media_type' => 'image',
                    'entity_type' => 'sector',
                    'entity_id' => $sectorId,
                    'relationship_type' => $data['media_relationship_type'] ?? 'main'
                ], $data['created_by']);
            }
            // Support de l'ancien champ pour compatibilité
            elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $this->mediaService->uploadMedia($_FILES['image'], [
                    'entity_type' => 'sector',
                    'entity_id' => $sectorId,
                    'relationship_type' => 'main',
                    'title' => $data['name'],
                    'is_public' => 1
                ], $data['created_by']);
            }

            if (!$this->db->commit()) {
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            $this->session->flash('success', 'Secteur créé avec succès');
            return Response::redirect('/sectors/' . $sectorId);  // ✅ CORRECTION
        } catch (ServiceException $e) {
            $this->db->rollBack();
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return Response::redirect('/sectors/create');  // ✅ CORRECTION
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->session->flash('error', 'Erreur lors de la création du secteur');
            return Response::redirect('/sectors/create');  // ✅ CORRECTION
        }
    }
    /**
     * Display edit sector form
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du secteur non spécifié');
            return Response::redirect('/sectors');  // ✅ CORRECTION
        }

        try {
            $sector = $this->sectorService->getSectorById((int) $id);

            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');  // ✅ CORRECTION
            }

            // Get data for form selections
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $books = $this->db->fetchAll("SELECT id, name FROM climbing_books WHERE active = 1 ORDER BY name ASC");
            $exposures = $this->db->fetchAll("SELECT id, name, code FROM climbing_exposures ORDER BY sort_order ASC");
            $months = $this->db->fetchAll("SELECT id, name, short_name FROM climbing_months ORDER BY month_number ASC");

            // Get current exposures for this sector
            $sectorExposures = $this->sectorService->getSectorExposures((int) $id);
            $currentExposures = array_column($sectorExposures, 'exposure_id');
            $primaryExposure = null;

            foreach ($sectorExposures as $exposure) {
                if ($exposure['is_primary']) {
                    $primaryExposure = $exposure['exposure_id'];
                    break;
                }
            }

            // Get months data
            $sectorMonths = $this->sectorService->getSectorMonths((int) $id);
            $monthsData = [];

            foreach ($sectorMonths as $month) {
                $monthsData[$month['month_id']] = [
                    'quality' => $month['quality'],
                    'notes' => $month['notes']
                ];
            }

            // Récupérer les médias associés à ce secteur via MediaService
            $media = $this->mediaService->getMediaForEntity('sector', (int) $id);


            return $this->render('sectors/form', [
                'title' => 'Modifier le secteur ' . $sector['name'],
                'sector' => $sector,
                'regions' => $regions,
                'books' => $books,
                'exposures' => $exposures,
                'months' => $months,
                'currentExposures' => $currentExposures,
                'primaryExposure' => $primaryExposure,
                'monthsData' => $monthsData,
                'media' => $media,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/sectors');  // ✅ CORRECTION
        }
    }
    /**
     * Update a sector
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du secteur non spécifié');
            return Response::redirect('/sectors');  // ✅ CORRECTION
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['name']) || empty($data['code']) || empty($data['book_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return Response::redirect('/sectors/' . $id . '/edit');  // ✅ CORRECTION
        }

        try {
            // IMPORTANT: Obtenir l'ID utilisateur directement de $_SESSION
            $data['updated_by'] = $_SESSION['auth_user_id'] ?? $this->session->get('user_id') ?? 1;

            // Begin transaction avec gestion d'erreur explicite
            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sectors/' . $id . '/edit');  // ✅ CORRECTION
            }

            // Mise à jour principale de la table climbing_sectors
            $updateData = [
                'name' => $data['name'],
                'code' => $data['code'],
                'book_id' => $data['book_id'],
                'region_id' => $data['region_id'] ?? null,
                'description' => $data['description'] ?? null,
                'access_info' => $data['access_info'] ?? null,
                'color' => $data['color'] ?? '#FF0000',
                'access_time' => !empty($data['access_time']) ? (int)$data['access_time'] : null,
                'altitude' => !empty($data['altitude']) ? (int)$data['altitude'] : null,
                'approach' => $data['approach'] ?? null,
                'height' => !empty($data['height']) ? (float)$data['height'] : null,
                'parking_info' => $data['parking_info'] ?? null,
                'coordinates_lat' => !empty($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => !empty($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'coordinates_swiss_e' => $data['coordinates_swiss_e'] ?? null,
                'coordinates_swiss_n' => $data['coordinates_swiss_n'] ?? null,
                'active' => isset($data['active']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $data['updated_by']
            ];

            // Mise à jour directe via Database
            $success = $this->db->update('climbing_sectors', $updateData, 'id = ?', [(int)$id]);

            if (!$success) {
                throw new \Exception("Échec de la mise à jour du secteur principal");
            }

            // Gestion des exposures - suppression puis réinsertion
            $this->db->delete('climbing_sector_exposures', 'sector_id = ?', [(int)$id]);

            if (!empty($data['exposures'])) {
                foreach ($data['exposures'] as $exposureId) {
                    $isPrimary = ($data['primary_exposure'] ?? 0) == $exposureId ? 1 : 0;
                    $this->db->insert('climbing_sector_exposures', [
                        'sector_id' => (int)$id,
                        'exposure_id' => (int)$exposureId,
                        'is_primary' => $isPrimary,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            // Gestion des months - suppression puis réinsertion si définis
            $this->db->delete('climbing_sector_months', 'sector_id = ?', [(int)$id]);

            if (!empty($data['months'])) {
                foreach ($data['months'] as $monthId => $monthData) {
                    if (isset($monthData['quality'])) {
                        $this->db->insert('climbing_sector_months', [
                            'sector_id' => (int)$id,
                            'month_id' => (int)$monthId,
                            'quality' => $monthData['quality'],
                            'notes' => $monthData['notes'] ?? null,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            // MODIFICATION: Traitement unifié des médias - uniquement avec media_file
            try {
                $mediaFile = $_FILES['media_file'] ?? null;
                if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name']) && $mediaFile['error'] === UPLOAD_ERR_OK) {
                    // Récupérer les informations sur le média
                    $mediaTitle = $data['media_title'] ?? null;
                    $relationshipType = $data['media_relationship_type'] ?? 'gallery';

                    // Uploader le média
                    $mediaId = $this->mediaService->uploadMedia($mediaFile, [
                        'title' => $mediaTitle ?? $data['name'],
                        'description' => "Image pour le secteur: {$data['name']}",
                        'is_public' => 1,
                        'media_type' => 'image',
                        'entity_type' => 'sector',
                        'entity_id' => (int)$id,
                        'relationship_type' => $relationshipType
                    ], $data['updated_by']);

                    if ($mediaId) {

                        // Si c'est une image principale, mettre à jour les anciennes relations "main"
                        if ($relationshipType === 'main') {
                            $this->db->update(
                                'climbing_media_relationships',
                                ['relationship_type' => 'gallery'],
                                'entity_type = ? AND entity_id = ? AND relationship_type = ? AND media_id != ?',
                                ['sector', (int)$id, 'main', $mediaId]
                            );
                        }
                    } else {
                        error_log("SectorUpdate: Échec de l'ajout du média");
                    }
                }
            } catch (\Exception $e) {
                // Capture des erreurs de traitement d'image mais continue l'exécution
                error_log("SectorUpdate: Erreur lors du traitement de l'image: " . $e->getMessage());
                // Ne pas lancer d'exception ici pour permettre à la mise à jour de se terminer
            }

            // Commit de la transaction
            if (!$this->db->commit()) {
                error_log("SectorUpdate: Échec commit transaction");
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            error_log("SectorUpdate: Mise à jour réussie du secteur #" . $id);
            $this->session->flash('success', 'Secteur mis à jour avec succès');
            return Response::redirect('/sectors/' . $id);  // ✅ CORRECTION
        } catch (\Exception $e) {
            // En cas d'erreur, rollback et message d'erreur
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("SectorUpdate - Exception: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return Response::redirect('/sectors/' . $id . '/edit');  // ✅ CORRECTION
        }
    }

    /**
     * Delete a sector
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du secteur non spécifié');
            return Response::redirect('/sectors');  // ✅ CORRECTION
        }

        // Check if it's a POST request with confirmation
        if ($request->getMethod() !== 'POST') {
            try {
                $sector = $this->sectorService->getSectorById((int) $id);

                if (!$sector) {
                    $this->session->flash('error', 'Secteur non trouvé');
                    return Response::redirect('/sectors');  // ✅ CORRECTION
                }

                // Get routes count
                $routesCount = count($this->sectorService->getSectorRoutes((int) $id));
                $mediaCount = count($this->sectorService->getSectorMedia((int) $id));

                // Show confirmation page
                return $this->render('sectors/delete', [
                    'title' => 'Supprimer le secteur ' . $sector['name'],
                    'sector' => $sector,
                    'routesCount' => $routesCount,
                    'mediaCount' => $mediaCount,
                    'csrf_token' => $this->createCsrfToken()
                ]);
            } catch (\Exception $e) {
                $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return Response::redirect('/sectors');  // ✅ CORRECTION
            }
        }

        // It's a POST request, proceed with deletion

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sectors/' . $id . '/delete');  // ✅ CORRECTION
        }

        try {
            // Vérifier si le secteur existe
            $sector = $this->sectorService->getSectorById((int) $id);
            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return Response::redirect('/sectors');  // ✅ CORRECTION
            }

            if (!$this->db->beginTransaction()) {
                error_log("SectorDelete: Erreur démarrage transaction");
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sectors/' . $id . '/delete');  // ✅ CORRECTION
            }

            // Supprimer les relations associées
            $this->db->delete('climbing_sector_exposures', 'sector_id = ?', [(int)$id]);
            $this->db->delete('climbing_sector_months', 'sector_id = ?', [(int)$id]);

            // Récupérer les relations média pour pouvoir supprimer les médias
            $mediaRelations = $this->db->fetchAll("SELECT media_id FROM climbing_media_relationships WHERE entity_type = 'sector' AND entity_id = ?", [(int)$id]);
            foreach ($mediaRelations as $relation) {
                // Supprimer les annotations des médias
                $this->db->delete('climbing_media_annotations', 'media_id = ?', [(int)$relation['media_id']]);
                // Supprimer les relations des médias
                $this->db->delete('climbing_media_relationships', 'media_id = ?', [(int)$relation['media_id']]);
                // Supprimer les tags des médias
                $this->db->delete('climbing_media_tags', 'media_id = ?', [(int)$relation['media_id']]);
                // Supprimer les médias eux-mêmes
                $this->db->delete('climbing_media', 'id = ?', [(int)$relation['media_id']]);
            }

            // Supprimer le secteur lui-même
            $success = $this->db->delete('climbing_sectors', 'id = ?', [(int)$id]);

            if (!$success) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la suppression du secteur');
                return Response::redirect('/sectors/' . $id . '/delete');  // ✅ CORRECTION
            }

            if (!$this->db->commit()) {
                error_log("SectorDelete: Échec commit transaction");
                throw new \Exception("Échec lors de la suppression finale");
            }

            $this->session->flash('success', 'Secteur supprimé avec succès');
            return Response::redirect('/sectors');  // ✅ CORRECTION
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SectorDelete: Exception: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression du secteur: ' . $e->getMessage());
            return Response::redirect('/sectors/' . $id . '/delete');  // ✅ CORRECTION
        }
    }
}
