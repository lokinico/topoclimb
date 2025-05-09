<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RouteService;
use App\Services\MediaService;
use App\Services\SectorService;
use App\Models\Route;

class RouteController extends BaseController
{
    protected RouteService $routeService;
    protected MediaService $mediaService;
    protected SectorService $sectorService;
    
    public function __construct(
        Request $request,
        Response $response,
        Session $session,
        View $view,
        RouteService $routeService,
        MediaService $mediaService,
        SectorService $sectorService
    ) {
        parent::__construct($request, $response, $session, $view);
        $this->routeService = $routeService;
        $this->mediaService = $mediaService;
        $this->sectorService = $sectorService;
    }
    
    /**
     * Affiche la liste des voies
     */
    public function index(): Response
    {
        // Récupère les paramètres de filtrage
        $filters = $this->request->getAllQuery();
        $page = (int) $this->request->getQuery('page', 1);
        $perPage = (int) $this->request->getQuery('per_page', 30);
        
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
     */
    public function show(int $id): Response
    {
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
     */
    public function create(): Response
    {
        // Vérifie les permissions
        $this->authorize('create-route');
        
        // Récupère le secteur si fourni
        $sectorId = (int) $this->request->getQuery('sector_id', 0);
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
     */
    public function store(): Response
    {
        // Vérifie les permissions
        $this->authorize('create-route');
        
        // Valide les données
        $data = $this->validate($this->request->getAllPost(), [
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
        $mediaFiles = $this->request->getAllFiles()['media'] ?? [];
        
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
     */
    public function edit(int $id): Response
    {
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
     */
    public function update(int $id): Response
    {
        $route = $this->routeService->getRoute($id);
        
        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }
        
        // Vérifie les permissions
        $this->authorize('update-route', $route);
        
        // Valide les données
        $data = $this->validate($this->request->getAllPost(), [
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
        $mediaFiles = $this->request->getAllFiles()['media'] ?? [];
        
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
     */
    public function delete(int $id): Response
    {
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
     */
    public function recordAscent(int $id): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!auth()->check()) {
            $this->flash('error', 'Vous devez être connecté pour enregistrer une ascension');
            return $this->redirect('/login');
        }
        
        $route = $this->routeService->getRoute($id);
        
        if (!$route) {
            $this->flash('error', 'Voie non trouvée');
            return $this->redirect('/routes');
        }
        
        // Valide les données
        $data = $this->validate($this->request->getAllPost(), [
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
            $data['user_id'] = auth()->id();
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