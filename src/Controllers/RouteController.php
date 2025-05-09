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
        // Récupère les paramètres de filtrage
        $filters = $request->query->all();
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 30);
        
        // Récupère les secteurs pour le filtre
        $sectors = $this->sectorService->getAllSectors();
        
        // Récupère les voies filtrées et paginées
        $routes = $this->routeService->getPaginatedRoutes($filters, $page, $perPage);
        
        return $this->render('routes/index', [
            'routes' => $routes,
            'sectors' => $sectors,
            'filters' => $filters
        ]);
    }
    
    /**
     * Affiche une voie spécifique
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
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
     * Affiche le formulaire d'édition d'une voie
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
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
     * Enregistre une ascension pour une voie
     *
     * @param Request $request
     * @return Response
     */
    public function recordAscent(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
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
            return $this->redirect('/routes/' . $id);
        }
    }
}