<?php
// src/Controllers/RouteController.php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Services\RouteService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\DifficultySystem;
use TopoclimbCH\Core\Filtering\RouteFilter;
use Symfony\Component\HttpFoundation\Request;

class RouteController extends BaseController
{
    protected RouteService $routeService;
    protected MediaService $mediaService;
    protected SectorService $sectorService;
    protected AuthService $authService;
    protected Database $db;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RouteService $routeService,
        MediaService $mediaService,
        SectorService $sectorService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->routeService = $routeService;
        $this->mediaService = $mediaService;
        $this->sectorService = $sectorService;
        $this->authService = $authService;
        $this->db = $db;
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

            // Utiliser le service pour récupérer les routes paginées
            $paginatedRoutes = $this->routeService->getRoutesWithFilters($filter, $page, $perPage);

            // Récupérer les données pour les filtres
            $sectors = $this->getSectorsForFilter();
            $diffSystems = $this->getDifficultySystemsForFilter();

            return $this->render('routes/index', [
                'routes' => $paginatedRoutes,
                'filter' => $filter,
                'sectors' => $sectors,
                'diffSystems' => $diffSystems,
                'currentUrl' => $request->getPathInfo()
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::index error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement des voies');
            return Response::redirect('/');
        }
    }

    /**
     * Affiche une voie spécifique
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            $this->session->flash('error', 'ID de voie invalide');
            return Response::redirect('/routes');
        }

        try {
            // Récupérer la voie avec ses relations
            $route = $this->routeService->getRouteById($id);

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Récupérer les informations supplémentaires
            $sector = $this->sectorService->getSectorById($route->sector_id);
            $media = $this->getRouteMedia($id);
            $comments = $this->getRouteComments($id);
            $similarRoutes = $this->getSimilarRoutes($route, 5);
            $ascentStats = $this->getAscentStatistics($id);

            return $this->render('routes/show', [
                'route' => $route,
                'sector' => $sector,
                'media' => $media,
                'comments' => $comments,
                'similarRoutes' => $similarRoutes,
                'ascentStats' => $ascentStats
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::show error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement de la voie');
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
            if (!$this->authService->check()) {
                $this->session->flash('error', 'Vous devez être connecté pour créer une voie');
                return Response::redirect('/login');
            }

            // Récupérer les données pour le formulaire
            $sectors = $this->getSectorsForDropdown();
            $difficultySystems = $this->getDifficultySystemsForDropdown();

            // Vérifier si un secteur spécifique est passé en paramètre
            $sectorId = $request->query->get('sector_id');
            $selectedSector = null;
            if ($sectorId) {
                $selectedSector = $this->sectorService->getSectorById((int)$sectorId);
            }

            // Créer un token CSRF
            $csrfToken = $this->csrfManager->generateToken();

            return $this->render('routes/form', [
                'route' => null, // Nouveau
                'sectors' => $sectors,
                'difficulty_systems' => $difficultySystems,
                'selected_sector' => $selectedSector,
                'csrf_token' => $csrfToken,
                'title' => 'Créer une nouvelle voie'
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::create error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/routes');
        }
    }

    /**
     * Enregistre une nouvelle voie
     */
    public function store(Request $request): Response
    {
        try {
            // Vérification des permissions
            if (!$this->authService->check()) {
                $this->session->flash('error', 'Vous devez être connecté pour créer une voie');
                return Response::redirect('/login');
            }

            // Valider le token CSRF
            if (!$this->csrfManager->validateToken($request->request->get('csrf_token'))) {
                $this->session->flash('error', 'Token de sécurité invalide');
                return Response::redirect('/routes/create');
            }

            // Récupérer et valider les données
            $data = $this->validateRouteData($request->request->all());

            // Ajouter les métadonnées
            $data['created_by'] = $this->authService->id();
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            // Créer la voie via le service
            $route = $this->routeService->createRoute($data);

            if (!$route) {
                throw new \Exception('Erreur lors de la création de la voie');
            }

            // Gérer l'upload d'images
            $this->handleMediaUploads($request, $route->id);

            $this->session->flash('success', 'Voie créée avec succès !');
            return Response::redirect('/routes/' . $route->id);
        } catch (\Exception $e) {
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
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            $this->session->flash('error', 'ID de voie invalide');
            return Response::redirect('/routes');
        }

        try {
            $route = $this->routeService->getRouteById($id);

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Vérifier les permissions
            if (!$this->canEditRoute($route)) {
                $this->session->flash('error', 'Vous n\'avez pas les permissions pour modifier cette voie');
                return Response::redirect('/routes/' . $id);
            }

            // Récupérer les données pour le formulaire
            $sectors = $this->getSectorsForDropdown();
            $difficultySystems = $this->getDifficultySystemsForDropdown();
            $media = $this->getRouteMedia($id);

            return $this->render('routes/form', [
                'route' => $route,
                'sectors' => $sectors,
                'difficulty_systems' => $difficultySystems,
                'media' => $media,
                'csrf_token' => $this->csrfManager->generateToken(),
                'title' => 'Modifier ' . $route->name
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::edit error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/routes/' . $id);
        }
    }

    /**
     * Met à jour une voie
     */
    public function update(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            $this->session->flash('error', 'ID de voie invalide');
            return Response::redirect('/routes');
        }

        try {
            $route = $this->routeService->getRouteById($id);

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Vérifier les permissions
            if (!$this->canEditRoute($route)) {
                $this->session->flash('error', 'Vous n\'avez pas les permissions pour modifier cette voie');
                return Response::redirect('/routes/' . $id);
            }

            // Valider le token CSRF
            if (!$this->csrfManager->validateToken($request->request->get('csrf_token'))) {
                $this->session->flash('error', 'Token de sécurité invalide');
                return Response::redirect('/routes/' . $id . '/edit');
            }

            // Valider les données
            $data = $this->validateRouteData($request->request->all());
            $data['updated_at'] = date('Y-m-d H:i:s');

            // Mettre à jour via le service
            $updatedRoute = $this->routeService->updateRoute($id, $data);

            if (!$updatedRoute) {
                throw new \Exception('Erreur lors de la mise à jour');
            }

            // Gérer l'upload d'images
            $this->handleMediaUploads($request, $id);

            $this->session->flash('success', 'Voie mise à jour avec succès');
            return Response::redirect('/routes/' . $id);
        } catch (\Exception $e) {
            error_log('RouteController::update error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            return Response::redirect('/routes/' . $id . '/edit');
        }
    }

    /**
     * Supprime une voie
     */
    public function delete(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            $this->session->flash('error', 'ID de voie invalide');
            return Response::redirect('/routes');
        }

        try {
            $route = $this->routeService->getRouteById($id);

            if (!$route) {
                $this->session->flash('error', 'Voie non trouvée');
                return Response::redirect('/routes');
            }

            // Vérifier les permissions
            if (!$this->canDeleteRoute($route)) {
                $this->session->flash('error', 'Vous n\'avez pas les permissions pour supprimer cette voie');
                return Response::redirect('/routes/' . $id);
            }

            $sectorId = $route->sector_id;

            // Supprimer via le service
            $this->routeService->deleteRoute($id);

            $this->session->flash('success', 'Voie supprimée avec succès');
            return Response::redirect('/sectors/' . $sectorId);
        } catch (\Exception $e) {
            error_log('RouteController::delete error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression');
            return Response::redirect('/routes/' . $id);
        }
    }

    /**
     * Enregistre une ascension
     */
    public function recordAscent(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'ID de voie invalide'], 400);
        }

        try {
            if (!$this->authService->check()) {
                return $this->jsonResponse(['error' => 'Vous devez être connecté'], 401);
            }

            $route = $this->routeService->getRouteById($id);
            if (!$route) {
                return $this->jsonResponse(['error' => 'Voie non trouvée'], 404);
            }

            $data = $this->validateAscentData($request->request->all());
            $data['user_id'] = $this->authService->id();
            $data['route_id'] = $id;

            $ascent = $this->routeService->recordAscent($data);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Ascension enregistrée avec succès',
                'ascent' => $ascent
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::recordAscent error: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Erreur lors de l\'enregistrement'], 500);
        }
    }

    /**
     * Récupère les commentaires d'une voie (AJAX)
     */
    public function comments(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'ID de voie invalide'], 400);
        }

        try {
            $comments = $this->getRouteComments($id);
            return $this->jsonResponse([
                'success' => true,
                'comments' => $comments,
                'count' => count($comments)
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::comments error: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Erreur lors du chargement des commentaires'], 500);
        }
    }

    /**
     * Ajoute un commentaire (AJAX)
     */
    public function storeComment(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'ID de voie invalide'], 400);
        }

        try {
            if (!$this->authService->check()) {
                return $this->jsonResponse(['error' => 'Vous devez être connecté'], 401);
            }

            $comment = trim($request->request->get('comment', ''));
            if (empty($comment)) {
                return $this->jsonResponse(['error' => 'Le commentaire ne peut pas être vide'], 400);
            }

            $commentData = [
                'route_id' => $id,
                'user_id' => $this->authService->id(),
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $newComment = $this->addRouteComment($commentData);

            return $this->jsonResponse([
                'success' => true,
                'comment' => $newComment,
                'message' => 'Commentaire ajouté avec succès'
            ]);
        } catch (\Exception $e) {
            error_log('RouteController::storeComment error: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Erreur lors de l\'ajout du commentaire'], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    private function validateRouteData(array $data): array
    {
        $validated = [];

        // Validation des champs obligatoires
        if (empty($data['sector_id']) || !is_numeric($data['sector_id'])) {
            throw new \Exception('Le secteur est obligatoire');
        }
        $validated['sector_id'] = (int) $data['sector_id'];

        if (empty($data['name'])) {
            throw new \Exception('Le nom de la voie est obligatoire');
        }
        $validated['name'] = trim($data['name']);

        // Validation des champs optionnels
        $validated['difficulty'] = $data['difficulty'] ?? null;
        $validated['difficulty_system_id'] = (int) ($data['difficulty_system_id'] ?? 1);
        $validated['style'] = $data['style'] ?? null;
        $validated['beauty'] = (int) ($data['beauty'] ?? 0);
        $validated['length'] = !empty($data['length']) ? (float) $data['length'] : null;
        $validated['equipment'] = $data['equipment'] ?? null;
        $validated['rappel'] = $data['rappel'] ?? null;
        $validated['comment'] = $data['comment'] ?? null;
        $validated['active'] = 1;

        return $validated;
    }

    private function validateAscentData(array $data): array
    {
        $validated = [];

        if (empty($data['ascent_type'])) {
            throw new \Exception('Le type d\'ascension est obligatoire');
        }
        $validated['ascent_type'] = $data['ascent_type'];

        if (empty($data['ascent_date'])) {
            throw new \Exception('La date d\'ascension est obligatoire');
        }
        $validated['ascent_date'] = $data['ascent_date'];

        $validated['quality_rating'] = isset($data['quality_rating']) ? (int) $data['quality_rating'] : null;
        $validated['difficulty_comment'] = $data['difficulty_comment'] ?? null;
        $validated['attempts'] = isset($data['attempts']) ? (int) $data['attempts'] : 1;
        $validated['comment'] = $data['comment'] ?? null;
        $validated['favorite'] = isset($data['favorite']) ? 1 : 0;

        return $validated;
    }

    private function getSectorsForDropdown(): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.name, r.name as region_name 
             FROM climbing_sectors s 
             LEFT JOIN climbing_regions r ON s.region_id = r.id 
             WHERE s.active = 1 
             ORDER BY r.name, s.name"
        );
    }

    private function getSectorsForFilter(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name FROM climbing_sectors WHERE active = 1 ORDER BY name"
        );
    }

    private function getDifficultySystemsForDropdown(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name FROM climbing_difficulty_systems ORDER BY name"
        );
    }

    private function getDifficultySystemsForFilter(): array
    {
        return $this->getDifficultySystemsForDropdown();
    }

    private function getRouteMedia(int $routeId): array
    {
        return $this->db->fetchAll(
            "SELECT m.* FROM climbing_media m 
             JOIN climbing_media_relationships mr ON m.id = mr.media_id 
             WHERE mr.entity_type = 'route' AND mr.entity_id = ? 
             ORDER BY mr.sort_order, m.created_at",
            [$routeId]
        );
    }

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

    private function addRouteComment(array $data): array
    {
        $id = $this->db->insert('comments', $data);

        return $this->db->fetchOne(
            "SELECT c.*, u.username, u.prenom, u.nom 
             FROM comments c 
             LEFT JOIN users u ON c.user_id = u.id 
             WHERE c.id = ?",
            [$id]
        );
    }

    private function getSimilarRoutes(object $route, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM climbing_routes 
             WHERE sector_id = ? AND id != ? 
             ORDER BY ABS(beauty - ?) ASC, name ASC 
             LIMIT ?",
            [$route->sector_id, $route->id, $route->beauty, $limit]
        );
    }

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

    private function handleMediaUploads(Request $request, int $routeId): void
    {
        $files = $request->files->get('media', []);

        foreach ($files as $file) {
            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                try {
                    $this->mediaService->uploadRouteMedia($file, $routeId, [
                        'title' => 'Route media',
                        'is_public' => 1
                    ]);
                } catch (\Exception $e) {
                    error_log('Erreur upload media route: ' . $e->getMessage());
                }
            }
        }
    }

    private function canEditRoute(object $route): bool
    {
        if (!$this->authService->check()) {
            return false;
        }

        $user = $this->authService->user();

        // Admin/modérateur peuvent tout modifier
        if (in_array($user->autorisation, ['1', '2'])) {
            return true;
        }

        // Créateur peut modifier sa voie
        return $route->created_by == $user->id;
    }

    private function canDeleteRoute(object $route): bool
    {
        if (!$this->authService->check()) {
            return false;
        }

        $user = $this->authService->user();

        // Seuls admin/modérateur peuvent supprimer
        return in_array($user->autorisation, ['1', '2']);
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        return new Response(
            json_encode($data),
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}
