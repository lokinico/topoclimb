<?php
// src/Controllers/SectorController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
use TopoclimbCH\Exceptions\ServiceException;

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
        Database $db
    ) {
        parent::__construct($view, $session);
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

        if (!$id) {
            $this->session->flash('error', 'ID du secteur non spécifié');
            return $this->redirect('/sectors');
        }

        try {
            // Debug - vérifier l'ID du secteur
            error_log("Affichage du secteur: " . $id);

            $sector = $this->sectorService->getSectorById((int) $id);

            // Debug - vérifier si le secteur est trouvé
            error_log("Secteur trouvé: " . ($sector ? 'OUI' : 'NON'));

            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }

            // Get additional data
            $exposures = $this->sectorService->getSectorExposures((int) $id);
            $routes = $this->sectorService->getSectorRoutes((int) $id);
            $media = $this->sectorService->getSectorMedia((int) $id);

            // Utilisons Database directement ici au lieu de Sector::getStats
            $db = \TopoclimbCH\Core\Database::getInstance();
            $stats = [
                'routes_count' => (int) ($db->fetchOne("SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ? AND active = 1", [$id])['count'] ?? 0),
                'media_count' => (int) ($db->fetchOne("SELECT COUNT(*) as count FROM climbing_media_relationships WHERE entity_type = 'sector' AND entity_id = ?", [$id])['count'] ?? 0)
            ];

            // Debug - toutes les données sont prêtes
            error_log("Données prêtes pour le rendu");

            return $this->render('sectors/show', [
                'title' => $sector['name'],
                'sector' => $sector,
                'exposures' => $exposures,
                'media' => $media,
                'routes' => $routes,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            // Debug - capturer et journaliser les exceptions
            error_log("Exception dans SectorController::show: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return $this->redirect('/sectors');
        }
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
            return $this->redirect('/sectors');
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
            return $this->redirect('/sectors/create');
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['name']) || empty($data['code']) || empty($data['book_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return $this->redirect('/sectors/create');
        }

        try {
            // Add the current user ID
            $data['created_by'] = $this->session->get('user_id');

            // Start transaction
            $this->db->beginTransaction();

            // Store the sector
            $sectorId = $this->sectorService->createSector($data);

            if (!$sectorId) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la création du secteur');
                return $this->redirect('/sectors/create');
            }

            // Handle exposures if provided
            if (!empty($data['exposures'])) {
                $primaryExposure = $data['primary_exposure'] ?? null;
                $this->sectorService->updateSectorExposures($sectorId, $data['exposures'], $primaryExposure);
            }

            // Handle months if provided
            if (!empty($data['months'])) {
                $this->sectorService->updateSectorMonths($sectorId, $data['months']);
            }

            // Handle uploaded image if any
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $this->mediaService->uploadMedia($_FILES['image'], [
                    'entity_type' => 'sector',
                    'entity_id' => $sectorId,
                    'relationship_type' => 'main',
                    'title' => $data['name'],
                    'is_public' => 1
                ], $this->session->get('user_id'));
            }

            $this->db->commit();
            $this->session->flash('success', 'Secteur créé avec succès');
            return $this->redirect('/sectors/' . $sectorId);
        } catch (ServiceException $e) {
            $this->db->rollBack();
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect('/sectors/create');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création du secteur');
            return $this->redirect('/sectors/create');
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
            return $this->redirect('/sectors');
        }

        try {
            $sector = $this->sectorService->getSectorById((int) $id);

            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
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

            // Get media for this sector
            $media = $this->sectorService->getSectorMedia((int) $id);

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
            return $this->redirect('/sectors');
        }
    }

    /**
     * Update a sector
     *
     * @param Request $request
     * @return Response
     */
    // Dans SectorController.php, méthode update()
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du secteur non spécifié');
            return $this->redirect('/sectors');
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['name']) || empty($data['code']) || empty($data['book_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return $this->redirect('/sectors/' . $id . '/edit');
        }

        try {
            // IMPORTANT: ⚠️ Obtenir l'ID utilisateur directement de $_SESSION pour éviter toute référence à Auth
            $data['updated_by'] = $_SESSION['auth_user_id'] ?? $this->session->get('user_id') ?? 1;

            // Log détaillé pour déboguer
            error_log("SectorUpdate: Début mise à jour secteur #" . $id . " par utilisateur #" . $data['updated_by']);

            // Begin transaction avec gestion d'erreur explicite
            if (!$this->db->beginTransaction()) {
                error_log("SectorUpdate: Erreur démarrage transaction");
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return $this->redirect('/sectors/' . $id . '/edit');
            }

            // Update with simpler direct DB access to avoid complex service calls
            try {
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
                    'active' => isset($data['active']) ? 1 : 0,

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
                    error_log("SectorUpdate: Échec mise à jour table climbing_sectors");
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
                            'is_primary' => $isPrimary
                        ]);
                    }
                }

                // Gestion des months - si définis
                if (!empty($data['months'])) {
                    // Implémentation similaire à celle des exposures
                    // ...
                }

                // Commit de la transaction
                if (!$this->db->commit()) {
                    error_log("SectorUpdate: Échec commit transaction");
                    throw new \Exception("Échec lors de l'enregistrement final des modifications");
                }

                error_log("SectorUpdate: Mise à jour réussie du secteur #" . $id);
                $this->session->flash('success', 'Secteur mis à jour avec succès');
                return $this->redirect('/sectors/' . $id);
            } catch (\Exception $e) {
                // En cas d'erreur, rollback et message d'erreur
                $this->db->rollback();
                error_log("SectorUpdate - Exception précise: " . $e->getMessage());
                $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
                return $this->redirect('/sectors/' . $id . '/edit');
            }
        } catch (\Exception $e) {
            // Cette partie ne devrait s'exécuter que si l'erreur est survenue avant ou après la transaction
            error_log("SectorUpdate - Exception externe: " . $e->getMessage());
            $this->session->flash('error', 'Erreur système lors de la mise à jour du secteur');
            return $this->redirect('/sectors/' . $id . '/edit');
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
            return $this->redirect('/sectors');
        }

        // Check if it's a POST request with confirmation
        if ($request->getMethod() !== 'POST') {
            try {
                $sector = $this->sectorService->getSectorById((int) $id);

                if (!$sector) {
                    $this->session->flash('error', 'Secteur non trouvé');
                    return $this->redirect('/sectors');
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
                return $this->redirect('/sectors');
            }
        }

        // It's a POST request, proceed with deletion

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/sectors/' . $id . '/delete');
        }

        try {
            $sector = $this->sectorService->getSectorById((int) $id);
            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }

            $this->db->beginTransaction();
            $success = $this->sectorService->deleteSector((int) $id);

            if (!$success) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la suppression du secteur');
                return $this->redirect('/sectors/' . $id . '/delete');
            }

            $this->db->commit();
            $this->session->flash('success', 'Secteur supprimé avec succès');
            return $this->redirect('/sectors');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression du secteur: ' . $e->getMessage());
            return $this->redirect('/sectors/' . $id . '/delete');
        }
    }
}
