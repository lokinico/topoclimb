<?php
// src/Controllers/RouteController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\RouteService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Core\Filtering\RouteFilter;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\DifficultySystem;
use TopoclimbCH\Core\Security\CsrfManager;

class RouteController extends BaseController
{
    /**
     * @var RouteService
     */
    private RouteService $routeService;

    /**
     * @var MediaService
     */
    private MediaService $mediaService;

    /**
     * @var SectorService
     */
    private SectorService $sectorService;

    /**
     * @var AuthService
     */
    private AuthService $authService;


    /**
     * Constructor - Ordre exact imposé par le système d'injection
     */
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RouteService $routeService,
        MediaService $mediaService,
        SectorService $sectorService,
        AuthService $authService
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->routeService = $routeService;
        $this->mediaService = $mediaService;
        $this->sectorService = $sectorService;
        $this->authService = $authService;

        // Récupérer Database via getInstance (pattern singleton)
        $this->db = Database::getInstance();
        
        // Récupérer Auth via AuthService pour compatibilité avec BaseController
        $this->auth = $this->authService->getAuth();
    }

    /**
     * Affiche la liste des voies
     */
    public function index(Request $request): Response
    {
        try {
            // Créer le filtre à partir des paramètres de requête
            $filter = new RouteFilter($request->query->all());

            // Récupérer la page courante
            $page = (int) $request->query->get('page', 1);
            $perPage = (int) $request->query->get('per_page', 30);

            // Obtenir le champ et la direction de tri avec validation
            $sortBy = $request->query->get('sort_by', 'name');
            $sortDir = $request->query->get('sort_dir', 'ASC');
            
            // Valider les colonnes de tri autorisées
            $allowedSorts = ['name', 'grade', 'height', 'created_at', 'sector_name'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'name';
            }
            
            // Valider la direction du tri
            $sortDir = strtoupper($sortDir);
            if (!in_array($sortDir, ['ASC', 'DESC'])) {
                $sortDir = 'ASC';
            }

            // Approche simplifiée temporaire pour éviter les erreurs
            try {
                $paginatedRoutes = Route::filterAndPaginate(
                    $filter,
                    $page,
                    $perPage,
                    $sortBy,
                    $sortDir
                );
            } catch (\Exception $filterError) {
                // Fallback si filterAndPaginate échoue
                error_log('Route::filterAndPaginate error: ' . $filterError->getMessage());
                
                // Requête simple comme fallback
                $offset = ($page - 1) * $perPage;
                $routes = $this->db->fetchAll(
                    "SELECT r.*, s.name as sector_name 
                     FROM climbing_routes r 
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                     WHERE r.active = 1 
                     ORDER BY r.{$sortBy} {$sortDir} 
                     LIMIT {$perPage} OFFSET {$offset}"
                );
                
                // Créer un objet simple compatible avec le template
                $paginatedRoutes = new class($routes, count($routes), $page, $perPage) {
                    public function __construct(private array $items, private int $total, private int $currentPage, private int $perPage) {}
                    public function getItems() { return $this->items; }
                    public function getTotal() { return $this->total; }
                    public function getCurrentPage() { return $this->currentPage; }
                    public function getTotalPages() { return ceil($this->total / $this->perPage); }
                    public function getPerPage() { return $this->perPage; }
                };
            }

            // Récupérer les données pour les filtres
            $sectors = $this->db->fetchAll("SELECT id, name FROM climbing_sectors WHERE active = 1 ORDER BY name ASC");
            $sites = $this->db->fetchAll("SELECT id, name FROM climbing_sites WHERE active = 1 ORDER BY name ASC");
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $diffSystems = $this->db->fetchAll("SELECT id, name FROM climbing_difficulty_systems ORDER BY name ASC");

            return $this->render('routes/index', [
                'routes' => $paginatedRoutes,
                'filter' => $filter,
                'sectors' => $sectors,
                'sites' => $sites,
                'regions' => $regions,
                'diffSystems' => $diffSystems,
                'currentFilters' => [
                    'search' => $request->query->get('search', ''),
                    'difficulty_min' => $request->query->get('difficulty_min', ''),
                    'difficulty_max' => $request->query->get('difficulty_max', ''),
                    'sector_id' => $request->query->get('sector_id', ''),
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir
                ],
                'currentUrl' => $request->getPathInfo(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::index error: ' . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des voies: ' . $e->getMessage());
            return $this->render('routes/index', [
                'routes' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Affiche une voie spécifique
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');
        error_log("DEBUG show() - ID reçu: " . ($id ?? 'NULL'));

        if (!$id) {
            error_log("DEBUG show() - Pas d'ID, redirection");
            $this->session->flash('error', 'ID de la voie non spécifié');
            return Response::redirect('/routes');
        }

        try {
            error_log("DEBUG show() - Appel getRouteById pour ID: " . $id);
            $route = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name, s.region_id 
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                 WHERE r.id = ? AND r.active = 1",
                [(int) $id]
            );
            error_log("DEBUG show() - Voie trouvée: " . ($route ? 'OUI' : 'NON'));

            if (!$route) {
                error_log("DEBUG show() - Voie non trouvée, redirection");
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Récupérer le secteur
            $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$route['sector_id']]);

            // Récupérer les médias
            $media = $this->mediaService->getMediaForEntity('route', (int)$id);

            // Récupérer les commentaires
            $comments = $this->getRouteComments((int)$id);

            // Récupérer les voies similaires
            $similarRoutes = $this->getSimilarRoutes($route, 5);

            // Calculer les statistiques d'ascension
            $ascentStats = $this->getAscentStatistics((int)$id);

            return $this->render('routes/show', [
                'title' => $route['name'],
                'route' => $route,
                'sector' => $sector,
                'media' => $media,
                'comments' => $comments,
                'similarRoutes' => $similarRoutes,
                'ascentStats' => $ascentStats
            ]);
        } catch (\Exception $e) {
            error_log("DEBUG show() - Exception: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/routes');
        }
    }

    /**
     * Affiche le formulaire de création d'une nouvelle voie
     */
    public function create(Request $request): Response
    {
        try {
            // Vérification des permissions
            if (!$this->session->get('auth_user_id')) {
                $this->session->flash('error', 'Vous devez être connecté pour créer une voie');
                return Response::redirect('/login');
            }

            // Récupérer les régions pour le sélecteur cascade
            $regions = $this->db->fetchAll(
                "SELECT r.id, r.name, c.name as country_name
             FROM climbing_regions r 
             LEFT JOIN climbing_countries c ON r.country_id = c.id 
             WHERE r.active = 1 
             ORDER BY c.name, r.name"
            );

            // Récupérer les secteurs (pour le cas où JS est désactivé)
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.region_id, r.name as region_name 
             FROM climbing_sectors s 
             LEFT JOIN climbing_regions r ON s.region_id = r.id 
             WHERE s.active = 1 
             ORDER BY r.name, s.name"
            );

            $difficultySystems = $this->db->fetchAll("SELECT id, name FROM climbing_difficulty_systems ORDER BY name ASC");

            // Vérifier si un secteur spécifique est passé en paramètre
            $sectorId = $request->query->get('sector_id');
            $selectedSector = null;
            $selectedRegion = null;

            if ($sectorId) {
                $selectedSector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ? AND active = 1", [(int)$sectorId]);
                if ($selectedSector && $selectedSector['region_id']) {
                    $selectedRegion = $this->db->fetchOne("SELECT * FROM climbing_regions WHERE id = ?", [$selectedSector['region_id']]);
                }
            }

            // Précharger des valeurs par défaut
            $route = [
                'beauty' => '0',
                'active' => 1,
                'difficulty_system_id' => 1
            ];

            if ($selectedSector) {
                $route['sector_id'] = $selectedSector['id'];
                $route['region_id'] = $selectedSector['region_id'];
            }

            return $this->render('routes/form', [
                'title' => 'Créer une nouvelle voie',
                'route' => $route,
                'regions' => $regions,
                'sectors' => $sectors,
                'difficulty_systems' => $difficultySystems,
                'selected_sector' => $selectedSector,
                'selected_region' => $selectedRegion,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::create error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire : ' . $e->getMessage());
            return Response::redirect('/routes');
        }
    }

    /**
     * Affiche le formulaire de création de voie (version test sans authentification)
     */
    public function testCreate(Request $request): Response
    {
        try {
            // Récupérer les régions pour le sélecteur cascade
            $regions = $this->db->fetchAll(
                "SELECT r.id, r.name, c.name as country_name
             FROM climbing_regions r 
             LEFT JOIN climbing_countries c ON r.country_id = c.id 
             WHERE r.active = 1 
             ORDER BY c.name, r.name"
            );

            // Récupérer les secteurs
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.region_id, r.name as region_name 
             FROM climbing_sectors s 
             LEFT JOIN climbing_regions r ON s.region_id = r.id 
             WHERE s.active = 1 
             ORDER BY r.name, s.name"
            );

            $difficultySystems = $this->db->fetchAll("SELECT id, name FROM climbing_difficulty_systems ORDER BY name ASC");

            // Valeurs par défaut
            $route = [
                'active' => 1,
                'difficulty_system_id' => 1
            ];

            return $this->render('routes/form', [
                'title' => 'Créer une nouvelle voie',
                'route' => $route,
                'regions' => $regions,
                'sectors' => $sectors,
                'difficulty_systems' => $difficultySystems,
                'selected_sector' => null,
                'selected_region' => null,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            // Version fallback avec formulaire HTML simple
            $html = '<form method="post">
                <input type="hidden" name="csrf_token" value="test">
                <input type="text" name="name" placeholder="Nom de la voie" required>
                <textarea name="description" placeholder="Description"></textarea>
                <select name="sector_id" required>
                    <option value="">Sélectionner un secteur</option>
                    <option value="1">Secteur 1</option>
                </select>
                <input type="text" name="difficulty" placeholder="Difficulté">
                <select name="difficulty_system_id">
                    <option value="1">Système 1</option>
                </select>
                <input type="text" name="style" placeholder="Style">
                <input type="number" name="beauty" min="0" max="5" value="0">
                <input type="number" name="length" step="0.1" placeholder="Longueur (m)">
                <input type="text" name="equipment" placeholder="Équipement">
                <input type="text" name="rappel" placeholder="Rappel">
                <textarea name="comment" placeholder="Commentaire"></textarea>
                <input type="checkbox" name="active" value="1" checked>
                <button type="submit">Créer</button>
            </form>';
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * Enregistre une nouvelle voie
     */
    public function store(Request $request): Response
    {
        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/routes/create');
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['sector_id']) || empty($data['name'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return Response::redirect('/routes/create');
        }

        try {
            // Add the current user ID
            $data['created_by'] = $_SESSION['auth_user_id'] ?? $this->session->get('user_id') ?? 1;

            // Start transaction
            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/routes/create');
            }

            // Préparer les données pour insertion
            $routeData = [
                'sector_id' => (int)$data['sector_id'],
                'name' => $data['name'],
                'difficulty' => $data['difficulty'] ?? null,
                'difficulty_system_id' => (int)($data['difficulty_system_id'] ?? 1),
                'style' => $data['style'] ?? null,
                'beauty' => (int)($data['beauty'] ?? 0),
                'length' => !empty($data['length']) ? (float)$data['length'] : null,
                'equipment' => $data['equipment'] ?? null,
                'rappel' => $data['rappel'] ?? null,
                'comment' => $data['comment'] ?? null,
                'active' => isset($data['active']) ? 1 : 1,
                'created_by' => $data['created_by'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Générer le numéro de voie automatiquement
            $maxNumber = $this->db->fetchOne(
                "SELECT MAX(number) as max_num FROM climbing_routes WHERE sector_id = ?",
                [$routeData['sector_id']]
            );
            $routeData['number'] = ((int)($maxNumber['max_num'] ?? 0)) + 1;

            // Insérer la voie
            $routeId = $this->db->insert('climbing_routes', $routeData);

            if (!$routeId) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la création de la voie');
                return Response::redirect('/routes/create');
            }

            // Traiter le média si présent
            $mediaFile = $_FILES['media_file'] ?? null;
            if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name']) && $mediaFile['error'] === UPLOAD_ERR_OK) {
                try {
                    $this->mediaService->uploadMedia($mediaFile, [
                        'title' => $data['media_title'] ?? $data['name'],
                        'description' => "Image pour la voie: {$data['name']}",
                        'is_public' => 1,
                        'media_type' => 'image',
                        'entity_type' => 'route',
                        'entity_id' => $routeId,
                        'relationship_type' => $data['media_relationship_type'] ?? 'main'
                    ], $data['created_by']);
                } catch (\Exception $e) {
                    error_log('Erreur upload image route: ' . $e->getMessage());
                }
            }

            if (!$this->db->commit()) {
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            $this->session->flash('success', 'Voie créée avec succès !');
            return Response::redirect('/routes/' . $routeId);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('RouteController::store error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création : ' . $e->getMessage());
            return Response::redirect('/routes/create');
        }
    }


    /**
     * Affiche le formulaire d'édition d'une voie
     */
    public function edit(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID de la voie non spécifié');
            return Response::redirect('/routes');
        }

        try {
            $route = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ? AND active = 1", [(int) $id]);

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Récupérer les régions pour le sélecteur cascade
            $regions = $this->db->fetchAll(
                "SELECT r.id, r.name, c.name as country_name
             FROM climbing_regions r 
             LEFT JOIN climbing_countries c ON r.country_id = c.id 
             WHERE r.active = 1 
             ORDER BY c.name, r.name"
            );

            // Récupérer les secteurs
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.region_id, r.name as region_name 
             FROM climbing_sectors s 
             LEFT JOIN climbing_regions r ON s.region_id = r.id 
             WHERE s.active = 1 
             ORDER BY r.name, s.name"
            );

            $difficultySystems = $this->db->fetchAll("SELECT id, name FROM climbing_difficulty_systems ORDER BY name ASC");

            // Récupérer le secteur actuel et sa région
            $currentSector = null;
            $currentRegion = null;
            if ($route['sector_id']) {
                $currentSector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$route['sector_id']]);
                if ($currentSector && $currentSector['region_id']) {
                    $currentRegion = $this->db->fetchOne("SELECT * FROM climbing_regions WHERE id = ?", [$currentSector['region_id']]);
                    // Ajouter l'ID de région à la route pour le formulaire
                    $route['region_id'] = $currentSector['region_id'];
                }
            }

            // Récupérer les médias associés
            $media = $this->mediaService->getMediaForEntity('route', (int) $id);

            return $this->render('routes/form', [
                'title' => 'Modifier la voie ' . $route['name'],
                'route' => $route,
                'regions' => $regions,
                'sectors' => $sectors,
                'difficulty_systems' => $difficultySystems,
                'selected_sector' => $currentSector,
                'selected_region' => $currentRegion,
                'media' => $media,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::edit error: ' . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/routes');
        }
    }

    /**
     * Met à jour une voie
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID de la voie non spécifié');
            return Response::redirect('/routes');
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/routes/' . $id . '/edit');
        }

        // Get form data
        $data = $request->request->all();

        // Basic validation
        if (empty($data['sector_id']) || empty($data['name'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return Response::redirect('/routes/' . $id . '/edit');
        }

        try {
            // IMPORTANT: Obtenir l'ID utilisateur directement de $_SESSION
            $data['updated_by'] = $_SESSION['auth_user_id'] ?? $this->session->get('user_id') ?? 1;

            // Begin transaction
            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/routes/' . $id . '/edit');
            }

            // Mise à jour principale de la table climbing_routes
            $updateData = [
                'sector_id' => (int)$data['sector_id'],
                'name' => $data['name'],
                'difficulty' => $data['difficulty'] ?? null,
                'difficulty_system_id' => (int)($data['difficulty_system_id'] ?? 1),
                'style' => $data['style'] ?? null,
                'beauty' => (int)($data['beauty'] ?? 0),
                'length' => !empty($data['length']) ? (float)$data['length'] : null,
                'equipment' => $data['equipment'] ?? null,
                'rappel' => $data['rappel'] ?? null,
                'comment' => $data['comment'] ?? null,
                'active' => isset($data['active']) ? 1 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $data['updated_by']
            ];

            // Mise à jour directe via Database
            $success = $this->db->update('climbing_routes', $updateData, 'id = ?', [(int)$id]);

            if (!$success) {
                throw new \Exception("Échec de la mise à jour de la voie");
            }

            // Traitement des médias
            try {
                $mediaFile = $_FILES['media_file'] ?? null;
                if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name']) && $mediaFile['error'] === UPLOAD_ERR_OK) {
                    $mediaTitle = $data['media_title'] ?? null;
                    $relationshipType = $data['media_relationship_type'] ?? 'gallery';

                    $mediaId = $this->mediaService->uploadMedia($mediaFile, [
                        'title' => $mediaTitle ?? $data['name'],
                        'description' => "Image pour la voie: {$data['name']}",
                        'is_public' => 1,
                        'media_type' => 'image',
                        'entity_type' => 'route',
                        'entity_id' => (int)$id,
                        'relationship_type' => $relationshipType
                    ], $data['updated_by']);

                    if ($mediaId && $relationshipType === 'main') {
                        $this->db->update(
                            'climbing_media_relationships',
                            ['relationship_type' => 'gallery'],
                            'entity_type = ? AND entity_id = ? AND relationship_type = ? AND media_id != ?',
                            ['route', (int)$id, 'main', $mediaId]
                        );
                    }
                }
            } catch (\Exception $e) {
                error_log("RouteUpdate: Erreur lors du traitement de l'image: " . $e->getMessage());
            }

            // Commit de la transaction
            if (!$this->db->commit()) {
                error_log("RouteUpdate: Échec commit transaction");
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            error_log("RouteUpdate: Mise à jour réussie de la voie #" . $id);
            $this->session->flash('success', 'Voie mise à jour avec succès');
            return Response::redirect('/routes/' . $id);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("RouteUpdate - Exception: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return Response::redirect('/routes/' . $id . '/edit');
        }
    }

    /**
     * Supprime une voie
     */
    public function delete(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID de la voie non spécifié');
            return Response::redirect('/routes');
        }

        // Check if it's a POST request with confirmation
        if ($request->getMethod() !== 'POST') {
            try {
                $route = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ? AND active = 1", [(int) $id]);

                if (!$route) {
                    $this->session->flash('error', 'Voie non trouvée');
                    return Response::redirect('/routes');
                }

                // Get ascents count
                $ascentsCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM user_ascents WHERE route_id = ?", [(int) $id])['count'] ?? 0;
                $mediaCount = count($this->mediaService->getMediaForEntity('route', (int) $id));

                // Show confirmation page
                return $this->render('routes/delete', [
                    'title' => 'Supprimer la voie ' . $route['name'],
                    'route' => $route,
                    'ascentsCount' => $ascentsCount,
                    'mediaCount' => $mediaCount,
                    'csrf_token' => $this->createCsrfToken()
                ]);
            } catch (\Exception $e) {
                $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return Response::redirect('/routes');
            }
        }

        // It's a POST request, proceed with deletion

        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/routes/' . $id . '/delete');
        }

        try {
            // Vérifier si la voie existe
            $route = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ? AND active = 1", [(int) $id]);
            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            $sectorId = $route['sector_id'];

            if (!$this->db->beginTransaction()) {
                error_log("RouteDelete: Erreur démarrage transaction");
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/routes/' . $id . '/delete');
            }

            // Supprimer les ascensions associées
            $this->db->delete('user_ascents', 'route_id = ?', [(int)$id]);

            // Supprimer les commentaires
            $this->db->delete('comments', 'route_id = ?', [(int)$id]);

            // Récupérer et supprimer les relations média
            $mediaRelations = $this->db->fetchAll("SELECT media_id FROM climbing_media_relationships WHERE entity_type = 'route' AND entity_id = ?", [(int)$id]);
            foreach ($mediaRelations as $relation) {
                $this->db->delete('climbing_media_annotations', 'media_id = ?', [(int)$relation['media_id']]);
                $this->db->delete('climbing_media_relationships', 'media_id = ?', [(int)$relation['media_id']]);
                $this->db->delete('climbing_media_tags', 'media_id = ?', [(int)$relation['media_id']]);
                $this->db->delete('climbing_media', 'id = ?', [(int)$relation['media_id']]);
            }

            // Supprimer la voie elle-même
            $success = $this->db->delete('climbing_routes', 'id = ?', [(int)$id]);

            if (!$success) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la suppression de la voie');
                return Response::redirect('/routes/' . $id . '/delete');
            }

            if (!$this->db->commit()) {
                error_log("RouteDelete: Échec commit transaction");
                throw new \Exception("Échec lors de la suppression finale");
            }

            $this->session->flash('success', 'Voie supprimée avec succès');
            return Response::redirect('/sectors/' . $sectorId);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("RouteDelete: Exception: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression de la voie: ' . $e->getMessage());
            return Response::redirect('/routes/' . $id . '/delete');
        }
    }

    /**
     * Affiche le formulaire de log d'ascension
     */
    public function logAscent(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            if (!$id) {
                $this->session->flash('error', 'ID de voie invalide');
                return Response::redirect('/routes');
            }

            // Vérification de l'authentification
            if (!$this->session->get('auth_user_id')) {
                $this->session->flash('error', 'Vous devez être connecté pour logger une ascension');
                return Response::redirect('/login');
            }

            // Récupérer les détails de la voie
            $route = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name, reg.name as region_name 
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id 
                 WHERE r.id = ? AND r.active = 1",
                [$id]
            );

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            return $this->render('routes/log-ascent', [
                'route' => $route,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::logAscent error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/routes/' . $id);
        }
    }

    /**
     * Enregistre une ascension (AJAX)
     */
    public function recordAscent(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            return Response::json(['error' => 'ID de voie invalide'], 400);
        }

        try {
            if (!$this->session->get('auth_user_id')) {
                return Response::json(['error' => 'Vous devez être connecté'], 401);
            }

            $route = $this->db->fetchOne("SELECT * FROM climbing_routes WHERE id = ? AND active = 1", [(int) $id]);
            if (!$route) {
                return Response::json(['error' => 'Voie non trouvée'], 404);
            }

            $data = $request->request->all();

            // Validation basique
            if (empty($data['ascent_type']) || empty($data['ascent_date'])) {
                return Response::json(['error' => 'Type et date d\'ascension obligatoires'], 400);
            }

            $ascentData = [
                'user_id' => $this->session->get('auth_user_id'),
                'route_id' => (int) $id,
                'topo_item' => $route['legacy_topo_item'] ?? '',
                'route_name' => $route['name'],
                'difficulty' => $route['difficulty'] ?? '',
                'ascent_type' => $data['ascent_type'],
                'climbing_type' => $route['style'] ?? 'sport',
                'with_user' => $data['with_user'] ?? null,
                'ascent_date' => $data['ascent_date'],
                'quality_rating' => isset($data['quality_rating']) ? (int) $data['quality_rating'] : null,
                'difficulty_comment' => $data['difficulty_comment'] ?? null,
                'attempts' => isset($data['attempts']) ? (int) $data['attempts'] : 1,
                'comment' => $data['comment'] ?? null,
                'favorite' => isset($data['favorite']) ? 1 : 0,
                'style' => $data['style'] ?? null,
                'tags' => $data['tags'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $ascentId = $this->db->insert('user_ascents', $ascentData);

            if (!$ascentId) {
                return Response::json(['error' => 'Erreur lors de l\'enregistrement'], 500);
            }

            return Response::json([
                'success' => true,
                'message' => 'Ascension enregistrée avec succès',
                'ascent_id' => $ascentId
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::recordAscent error: ' . $e->getMessage());
            return Response::json(['error' => 'Erreur lors de l\'enregistrement'], 500);
        }
    }

    /**
     * Récupère les commentaires d'une voie (AJAX)
     */
    public function comments(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            return Response::json(['error' => 'ID de voie invalide'], 400);
        }

        try {
            $comments = $this->getRouteComments((int)$id);
            return Response::json([
                'success' => true,
                'comments' => $comments,
                'count' => count($comments)
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::comments error: ' . $e->getMessage());
            return Response::json(['error' => 'Erreur lors du chargement des commentaires'], 500);
        }
    }

    /**
     * Ajoute un commentaire (AJAX)
     */
    public function storeComment(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            return Response::json(['error' => 'ID de voie invalide'], 400);
        }

        try {
            if (!$this->session->get('auth_user_id')) {
                return Response::json(['error' => 'Vous devez être connecté'], 401);
            }

            $comment = trim($request->request->get('comment', ''));
            if (empty($comment)) {
                return Response::json(['error' => 'Le commentaire ne peut pas être vide'], 400);
            }

            $commentData = [
                'route_id' => (int) $id,
                'user_id' => $this->session->get('auth_user_id'),
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $commentId = $this->db->insert('comments', $commentData);

            if (!$commentId) {
                return Response::json(['error' => 'Erreur lors de l\'ajout'], 500);
            }

            $newComment = $this->db->fetchOne(
                "SELECT c.*, u.username, u.prenom, u.nom 
                 FROM comments c 
                 LEFT JOIN users u ON c.user_id = u.id 
                 WHERE c.id = ?",
                [$commentId]
            );

            return Response::json([
                'success' => true,
                'comment' => $newComment,
                'message' => 'Commentaire ajouté avec succès'
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::storeComment error: ' . $e->getMessage());
            return Response::json(['error' => 'Erreur lors de l\'ajout du commentaire'], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Récupère les commentaires d'une voie
     */
    private function getRouteComments(int $routeId): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, u.username, u.prenom, u.nom 
             FROM comments c 
             LEFT JOIN users u ON c.user_id = u.id 
             WHERE c.route_id = ? 
             ORDER BY c.created_at DESC",
            [$routeId]
        );
    }

    /**
     * Récupère les voies similaires
     */
    private function getSimilarRoutes(array $route, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM climbing_routes 
             WHERE sector_id = ? AND id != ? AND active = 1
             ORDER BY ABS(beauty - ?) ASC, name ASC 
             LIMIT ?",
            [$route['sector_id'], $route['id'], (int)($route['beauty'] ?? 0), $limit]
        );
    }

    /**
     * Récupère les statistiques d'ascension
     */
    private function getAscentStatistics(int $routeId): array
    {
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_ascents,
                AVG(quality_rating) as avg_rating,
                COUNT(CASE WHEN ascent_type = 'flash' THEN 1 END) as flash_count,
                COUNT(CASE WHEN ascent_type = 'onsight' THEN 1 END) as onsight_count,
                COUNT(CASE WHEN ascent_type = 'redpoint' THEN 1 END) as redpoint_count
             FROM user_ascents 
             WHERE route_id = ?",
            [$routeId]
        );

        return $stats ?: [
            'total_ascents' => 0,
            'avg_rating' => 0,
            'flash_count' => 0,
            'onsight_count' => 0,
            'redpoint_count' => 0
        ];
    }

    /**
     * Ajoute un commentaire (POST)
     */
    public function addComment(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID de voie invalide');
            return Response::redirect('/routes');
        }

        try {
            if (!$this->session->get('auth_user_id')) {
                $this->session->flash('error', 'Vous devez être connecté pour commenter');
                return Response::redirect('/login');
            }

            $comment = trim($request->request->get('comment', ''));
            if (empty($comment)) {
                $this->session->flash('error', 'Le commentaire ne peut pas être vide');
                return Response::redirect("/routes/$id");
            }

            $commentData = [
                'route_id' => (int) $id,
                'user_id' => $this->session->get('auth_user_id'),
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $commentId = $this->db->insert('comments', $commentData);

            if ($commentId) {
                $this->session->flash('success', 'Commentaire ajouté avec succès');
            } else {
                $this->session->flash('error', 'Erreur lors de l\'ajout du commentaire');
            }

            return Response::redirect("/routes/$id");
        } catch (\Exception $e) {
            error_log('RouteController::addComment error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de l\'ajout du commentaire');
            return Response::redirect("/routes/$id");
        }
    }

    /**
     * Bascule le statut favori d'une voie
     */
    public function toggleFavorite(Request $request): Response
    {
        $id = $request->attributes->get('id');
        $userId = $this->session->get('auth_user_id');

        if (!$id || !$userId) {
            return Response::json(['error' => 'Paramètres manquants'], 400);
        }

        try {
            // Vérifier si la voie est déjà en favoris
            $existingFavorite = $this->db->fetchOne(
                "SELECT id FROM user_favorites WHERE user_id = ? AND route_id = ?",
                [$userId, $id]
            );

            if ($existingFavorite) {
                // Supprimer des favoris
                $this->db->delete('user_favorites', 'user_id = ? AND route_id = ?', [$userId, $id]);
                return Response::json(['success' => true, 'action' => 'removed', 'message' => 'Retiré des favoris']);
            } else {
                // Ajouter aux favoris
                $this->db->insert('user_favorites', [
                    'user_id' => $userId,
                    'route_id' => $id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return Response::json(['success' => true, 'action' => 'added', 'message' => 'Ajouté aux favoris']);
            }
        } catch (\Exception $e) {
            error_log('RouteController::toggleFavorite error: ' . $e->getMessage());
            return Response::json(['error' => 'Erreur lors de la modification'], 500);
        }
    }

    /**
     * Retire une voie des favoris
     */
    public function removeFavorite(Request $request): Response
    {
        $id = $request->attributes->get('id');
        $userId = $this->session->get('auth_user_id');

        if (!$id || !$userId) {
            return Response::json(['error' => 'Paramètres manquants'], 400);
        }

        try {
            $deleted = $this->db->delete('user_favorites', 'user_id = ? AND route_id = ?', [$userId, $id]);
            
            if ($deleted) {
                return Response::json(['success' => true, 'message' => 'Retiré des favoris']);
            } else {
                return Response::json(['error' => 'Favori non trouvé'], 404);
            }
        } catch (\Exception $e) {
            error_log('RouteController::removeFavorite error: ' . $e->getMessage());
            return Response::json(['error' => 'Erreur lors de la suppression'], 500);
        }
    }

    // ========== API METHODS ==========

    /**
     * API: Liste des voies avec pagination
     */
    public function apiIndex(Request $request): Response
    {
        try {
            $limit = min((int)($request->query->get('limit') ?? 100), 500);
            $offset = (int)($request->query->get('offset') ?? 0);
            $sectorId = $request->query->get('sector_id');
            
            $sql = "SELECT r.id, r.name, r.difficulty, r.created_at,
                           s.name as sector_name, s.id as sector_id,
                           reg.name as region_name, reg.id as region_id
                    FROM climbing_routes r
                    LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                    LEFT JOIN climbing_regions reg ON s.region_id = reg.id  
                    WHERE r.active = 1";
            
            $params = [];
            
            if ($sectorId) {
                $sql .= " AND r.sector_id = ?";
                $params[] = (int)$sectorId;
            }
            
            $sql .= " ORDER BY r.name ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $routes = $this->db->fetchAll($sql, $params);
            
            // Compter le total pour la pagination
            $countSql = "SELECT COUNT(*) as total FROM climbing_routes r WHERE r.active = 1";
            $countParams = [];
            
            if ($sectorId) {
                $countSql .= " AND r.sector_id = ?";
                $countParams[] = (int)$sectorId;
            }
            
            $total = $this->db->fetchOne($countSql, $countParams)['total'] ?? 0;
            
            return Response::json([
                'success' => true,
                'data' => $routes,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::apiIndex error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors du chargement des voies'
            ], 500);
        }
    }

    /**
     * API: Recherche de voies
     */
    public function apiSearch(Request $request): Response
    {
        try {
            $query = $request->query->get('q', '');
            $sectorId = $request->query->get('sector_id');
            $difficulty = $request->query->get('difficulty');
            $minBeauty = $request->query->get('min_beauty');
            $limit = min((int)($request->query->get('limit') ?? 50), 200);
            
            if (empty($query) && !$sectorId && !$difficulty && !$minBeauty) {
                return Response::json([
                    'success' => false,
                    'error' => 'Au moins un critère de recherche est requis'
                ], 400);
            }
            
            $sql = "SELECT r.id, r.name, r.difficulty,
                           s.name as sector_name, s.id as sector_id,
                           reg.name as region_name
                    FROM climbing_routes r
                    LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                    LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                    WHERE r.active = 1";
            
            $params = [];
            $conditions = [];
            
            if (!empty($query)) {
                $conditions[] = "(r.name LIKE ? OR s.name LIKE ?)";
                $searchTerm = '%' . $query . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($sectorId) {
                $conditions[] = "r.sector_id = ?";
                $params[] = (int)$sectorId;
            }
            
            if ($difficulty) {
                $conditions[] = "r.difficulty = ?";
                $params[] = $difficulty;
            }
            
            // Beauty filter removed - column doesn't exist
            // if ($minBeauty !== null) {
            //     $conditions[] = "r.beauty >= ?";
            //     $params[] = (int)$minBeauty;
            // }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY r.name ASC LIMIT ?";
            $params[] = $limit;
            
            $routes = $this->db->fetchAll($sql, $params);
            
            return Response::json([
                'success' => true,
                'data' => $routes,
                'query' => $query,
                'results_count' => count($routes)
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::apiSearch error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }
}
