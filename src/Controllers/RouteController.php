<?php
// src/Controllers/RouteController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\RouteService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\AuthService;

class RouteController extends BaseController
{
    /**
     * @var RouteService
     */
    protected RouteService $routeService;

    /**
     * @var MediaService
     */
    protected MediaService $mediaService;

    /**
     * @var SectorService
     */
    protected SectorService $sectorService;

    /**
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     * @param RouteService $routeService
     * @param MediaService $mediaService
     * @param SectorService $sectorService
     * @param AuthService $authService
     */
    public function __construct(
        View $view,
        Session $session,
        RouteService $routeService,
        MediaService $mediaService,
        SectorService $sectorService,
        AuthService $authService
    ) {
        parent::__construct($view, $session);
        $this->routeService = $routeService;
        $this->mediaService = $mediaService;
        $this->sectorService = $sectorService;
        $this->authService = $authService;
    }

    /**
     * Affiche la liste des voies
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Créer le filtre à partir des paramètres de requête
        $filter = new \TopoclimbCH\Core\Filtering\RouteFilter($request->query->all());

        // Récupérer la page courante
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 30);

        // Paginer les résultats filtrés
        $paginatedRoutes = \TopoclimbCH\Models\Route::filterAndPaginate(
            $filter,
            $page,
            $perPage,
            'name',
            'ASC'
        );

        // DEBUG: Log the raw attributes of the first route (if any)
        $items = $paginatedRoutes->getItems();
        if (!empty($items) && isset($items[0])) {
            error_log('First route raw attributes: ' . print_r($items[0], true));
            if (method_exists($items[0], 'toArray')) {
                error_log('First route toArray: ' . print_r($items[0]->toArray(), true));
            }
        }

        // Récupérer les données pour les filtres
        $sectors = \TopoclimbCH\Models\Sector::active();
        $diffSystems = \TopoclimbCH\Models\DifficultySystem::getActiveSystems();

        return $this->render('routes/index', [
            'routes' => $paginatedRoutes,
            'filter' => $filter,
            'sectors' => $sectors,
            'diffSystems' => $diffSystems,
            'currentUrl' => $request->getPathInfo()
        ]);
    }

    /**
     * Affiche une voie spécifique - AVEC VALIDATION ID
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // AJOUT: Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        $route = $this->routeService->getRouteWithRelations($id, ['sector', 'media']);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        // Récupère les voies similaires (même secteur, difficulté proche)
        $similarRoutes = $this->routeService->getSimilarRoutes($route, 5);

        // Récupère les statistiques d'ascension pour cette voie
        $ascentStats = $this->routeService->getAscentStatistics($route);

        return $this->render('routes/show', [
            'route' => $route,
            'similarRoutes' => $similarRoutes,
            'ascentStats' => $ascentStats
        ]);
    }

    /**
     * Affiche le formulaire de création d'une voie
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('create-route');

        // Récupère le secteur si fourni
        $sectorId = (int) $request->query->get('sector_id', 0);
        $sector = $sectorId ? $this->sectorService->getSector($sectorId) : null;

        // Récupère les données pour les dropdown
        $sectors = $this->sectorService->getAllSectors();
        $difficultySystems = $this->routeService->getDifficultySystems();

        return $this->render('routes/create', [
            'sectors' => $sectors,
            'difficultySystems' => $difficultySystems,
            'sector' => $sector
        ]);
    }

    /**
     * Enregistre une nouvelle voie
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('create-route');

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'sector_id' => 'required|integer',
            'name' => 'required|max:255',
            'difficulty' => 'required|max:10',
            'difficulty_system_id' => 'required|integer',
            'beauty' => 'required|in:0,1,2,3,4,5',
            'style' => 'nullable|in:sport,trad,mix,boulder,aid,ice,other',
            'length' => 'nullable|numeric',
            'equipment' => 'nullable|in:poor,adequate,good,excellent',
            'rappel' => 'nullable|max:50',
            'comment' => 'nullable'
        ]);

        // Gère les fichiers uploadés
        $mediaFiles = $request->files->get('media') ?? [];

        try {
            // Crée la voie
            $route = $this->routeService->createRoute($data);

            // Traite les médias uploadés
            if (!empty($mediaFiles)) {
                $this->mediaService->handleRouteMediaUploads($route, $mediaFiles);
            }

            $this->flash('success', 'Voie créée avec succès');
            return $this->redirect('/routes/' . $route->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la création de la voie: ' . $e->getMessage());
            return $this->redirect('/routes/create?sector_id=' . $data['sector_id']);
        }
    }

    /**
     * Affiche le formulaire d'édition d'une voie - AVEC VALIDATION ID
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // AJOUT: Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        // Vérifie les permissions
        $this->authorize('update-route', $route);

        // Récupère les données pour les dropdown
        $sectors = $this->sectorService->getAllSectors();
        $difficultySystems = $this->routeService->getDifficultySystems();
        $media = $this->mediaService->getRouteMedia($route);

        return $this->render('routes/edit', [
            'route' => $route,
            'sectors' => $sectors,
            'difficultySystems' => $difficultySystems,
            'media' => $media
        ]);
    }

    /**
     * Met à jour une voie
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // AJOUT: Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        // Vérifie les permissions
        $this->authorize('update-route', $route);

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'sector_id' => 'required|integer',
            'name' => 'required|max:255',
            'difficulty' => 'required|max:10',
            'difficulty_system_id' => 'required|integer',
            'beauty' => 'required|in:0,1,2,3,4,5',
            'style' => 'nullable|in:sport,trad,mix,boulder,aid,ice,other',
            'length' => 'nullable|numeric',
            'equipment' => 'nullable|in:poor,adequate,good,excellent',
            'rappel' => 'nullable|max:50',
            'comment' => 'nullable'
        ]);

        // Gère les fichiers uploadés
        $mediaFiles = $request->files->get('media') ?? [];

        try {
            // Met à jour la voie
            $route = $this->routeService->updateRoute($route, $data);

            // Traite les médias uploadés
            if (!empty($mediaFiles)) {
                $this->mediaService->handleRouteMediaUploads($route, $mediaFiles);
            }

            $this->flash('success', 'Voie mise à jour avec succès');
            return $this->redirect('/routes/' . $route->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour de la voie: ' . $e->getMessage());
            return $this->redirect('/routes/' . $id . '/edit');
        }
    }

    /**
     * Supprime une voie
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // AJOUT: Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        // Vérifie les permissions
        $this->authorize('delete-route', $route);

        try {
            // Supprime la voie
            $this->routeService->deleteRoute($route);

            $this->flash('success', 'Voie supprimée avec succès');
            return $this->redirect('/sectors/' . $route->sector_id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression de la voie: ' . $e->getMessage());
            return $this->redirect('/routes/' . $id);
        }
    }

    /**
     * NOUVELLE MÉTHODE: Affiche le formulaire pour enregistrer une ascension
     *
     * @param Request $request
     * @return Response
     */
    public function logAscent(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour enregistrer une ascension');
            return $this->redirect('/login');
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        return $this->render('routes/log-ascent', [
            'route' => $route,
            'title' => 'Enregistrer une ascension - ' . $route->name
        ]);
    }

    /**
     * Enregistre une ascension pour une voie - AVEC VALIDATION ID
     *
     * @param Request $request
     * @return Response
     */
    public function recordAscent(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // AJOUT: Validation de l'ID
        if ($id <= 0) {
            $this->flash('error', 'ID de voie invalide');
            return $this->redirect('/routes');
        }

        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour enregistrer une ascension');
            return $this->redirect('/login');
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'ascent_type' => 'required|in:flash,onsight,redpoint,attempt',
            'ascent_date' => 'required|date',
            'quality_rating' => 'nullable|integer|min:0|max:5',
            'difficulty_comment' => 'nullable|in:easy,accurate,hard',
            'attempts' => 'nullable|integer|min:1',
            'comment' => 'nullable',
            'favorite' => 'nullable|boolean'
        ]);

        try {
            // Ajoute l'ID utilisateur
            $data['user_id'] = $this->authService->id();
            $data['route_id'] = $route->id;

            // Enregistre l'ascension
            $ascent = $this->routeService->recordAscent($data);

            $this->flash('success', 'Ascension enregistrée avec succès');
            return $this->redirect('/routes/' . $route->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de l\'enregistrement de l\'ascension: ' . $e->getMessage());
            return $this->redirect('/routes/' . $id . '/log-ascent');
        }
    }

    /**
     * NOUVELLE MÉTHODE: Affiche les commentaires d'une voie (pour AJAX)
     *
     * @param Request $request
     * @return Response
     */
    public function comments(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // Validation de l'ID
        if ($id <= 0) {
            return new Response(
                json_encode(['error' => 'ID de voie invalide']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            return new Response(
                json_encode(['error' => 'Voie non trouvée']),
                404,
                ['Content-Type' => 'application/json']
            );
        }

        // Récupérer les commentaires depuis la base de données
        try {
            $comments = $this->routeService->getRouteComments($id);

            return new Response(
                json_encode([
                    'success' => true,
                    'comments' => $comments,
                    'count' => count($comments)
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (\Exception $e) {
            error_log("Erreur récupération commentaires route $id: " . $e->getMessage());
            return new Response(
                json_encode(['error' => 'Erreur lors du chargement des commentaires']),
                500,
                ['Content-Type' => 'application/json']
            );
        }
    }

    /**
     * NOUVELLE MÉTHODE: Ajoute un commentaire à une voie
     *
     * @param Request $request
     * @return Response
     */
    public function storeComment(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        // Validation de l'ID
        if ($id <= 0) {
            return new Response(
                json_encode(['error' => 'ID de voie invalide']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            return new Response(
                json_encode(['error' => 'Vous devez être connecté pour commenter']),
                401,
                ['Content-Type' => 'application/json']
            );
        }

        $route = $this->routeService->getRoute($id);

        if (!$route) {
            return new Response(
                json_encode(['error' => 'Voie non trouvée']),
                404,
                ['Content-Type' => 'application/json']
            );
        }

        $comment = trim($request->request->get('comment', ''));

        if (empty($comment)) {
            return new Response(
                json_encode(['error' => 'Le commentaire ne peut pas être vide']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        try {
            $commentData = [
                'route_id' => $id,
                'user_id' => $this->authService->id(),
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $newComment = $this->routeService->addRouteComment($commentData);

            return new Response(
                json_encode([
                    'success' => true,
                    'comment' => $newComment,
                    'message' => 'Commentaire ajouté avec succès'
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (\Exception $e) {
            error_log("Erreur ajout commentaire route $id: " . $e->getMessage());
            return new Response(
                json_encode(['error' => 'Erreur lors de l\'ajout du commentaire']),
                500,
                ['Content-Type' => 'application/json']
            );
        }
    }
}
