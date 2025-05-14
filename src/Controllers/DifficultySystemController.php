<?php
// src/Controllers/DifficultySystemController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\DifficultySystem;
use TopoclimbCH\Services\DifficultyService;
use TopoclimbCH\Services\AuthService;

class DifficultySystemController extends BaseController
{
    /**
     * @var DifficultyService
     */
    protected DifficultyService $difficultyService;
    
    /**
     * @var AuthService
     */
    protected AuthService $authService;
    
    /**
     * @var Database
     */
    protected Database $db;
    
    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     * @param DifficultyService $difficultyService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        DifficultyService $difficultyService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->difficultyService = $difficultyService;
        $this->authService = $authService;
        $this->db = $db;
    }
    
    /**
     * Affiche la liste des systèmes de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $systems = $this->difficultyService->getAllSystems();
        
        return $this->render('difficulty/systems/index', [
            'systems' => $systems,
            'title' => 'Systèmes de difficulté'
        ]);
    }
    
    /**
     * Affiche un système de difficulté spécifique
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystemWithGrades($id);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        return $this->render('difficulty/systems/show', [
            'system' => $system,
            'grades' => $system->grades(),
            'title' => $system->name
        ]);
    }
    
    /**
     * Affiche le formulaire de création d'un système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        return $this->render('difficulty/systems/create', [
            'title' => 'Créer un nouveau système de difficulté',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Enregistre un nouveau système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/create');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'name' => 'required|max:50',
            'description' => 'nullable',
            'is_default' => 'in:0,1'
        ]);
        
        try {
            // Si ce système est défini comme par défaut, désactiver les autres
            if (isset($data['is_default']) && $data['is_default'] == 1) {
                $this->difficultyService->resetDefaultSystem();
            }
            
            // Crée le système
            $system = $this->difficultyService->createSystem($data);
            
            $this->flash('success', 'Système de difficulté créé avec succès');
            return $this->redirect('/difficulty/systems/' . $system->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la création du système: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/create');
        }
    }
    
    /**
     * Affiche le formulaire d'édition d'un système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystem($id);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        return $this->render('difficulty/systems/edit', [
            'system' => $system,
            'title' => 'Modifier ' . $system->name,
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour un système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $id = (int) $request->attributes->get('id');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/' . $id . '/edit');
        }
        
        $system = $this->difficultyService->getSystem($id);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'name' => 'required|max:50',
            'description' => 'nullable',
            'is_default' => 'in:0,1'
        ]);
        
        try {
            // Si ce système est défini comme par défaut, désactiver les autres
            if (isset($data['is_default']) && $data['is_default'] == 1) {
                $this->difficultyService->resetDefaultSystem();
            }
            
            // Met à jour le système
            $system = $this->difficultyService->updateSystem($system, $data);
            
            $this->flash('success', 'Système de difficulté mis à jour avec succès');
            return $this->redirect('/difficulty/systems/' . $system->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du système: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/' . $id . '/edit');
        }
    }
    
    /**
     * Supprime un système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystem($id);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            // Vérifie si le système est utilisé par des voies
            $routesCount = $this->difficultyService->getSystemRoutesCount($id);
            
            return $this->render('difficulty/systems/delete', [
                'system' => $system,
                'routesCount' => $routesCount,
                'title' => 'Supprimer ' . $system->name,
                'csrf_token' => $this->createCsrfToken()
            ]);
        }
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/' . $id . '/delete');
        }
        
        try {
            // Supprime le système
            $this->difficultyService->deleteSystem($system);
            
            $this->flash('success', 'Système de difficulté supprimé avec succès');
            return $this->redirect('/difficulty/systems');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression du système: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/' . $id);
        }
    }
    
    /**
     * Exporte un système de difficulté au format JSON
     *
     * @param Request $request
     * @return Response
     */
    public function export(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystemWithGrades($id);
        
        if (!$system) {
            return $this->json(['error' => 'Système de difficulté non trouvé'], 404);
        }
        
        $exportData = [
            'system' => [
                'name' => $system->name,
                'description' => $system->description
            ],
            'grades' => []
        ];
        
        foreach ($system->grades() as $grade) {
            $exportData['grades'][] = [
                'value' => $grade->value,
                'numerical_value' => $grade->numerical_value,
                'sort_order' => $grade->sort_order
            ];
        }
        
        return $this->json($exportData);
    }
    
    /**
     * Compare deux systèmes de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function compare(Request $request): Response
    {
        // Récupère tous les systèmes pour le formulaire
        $systems = $this->difficultyService->getAllSystems();
        
        $fromId = (int) $request->query->get('from_id', 0);
        $toId = (int) $request->query->get('to_id', 0);
        
        $comparisonTable = [];
        
        if ($fromId && $toId) {
            $fromSystem = $this->difficultyService->getSystemWithGrades($fromId);
            $toSystem = $this->difficultyService->getSystemWithGrades($toId);
            
            if ($fromSystem && $toSystem) {
                $comparisonTable = $this->difficultyService->compareSystemsTable($fromSystem, $toSystem);
            }
        }
        
        return $this->render('difficulty/systems/compare', [
            'title' => 'Comparer les systèmes de difficulté',
            'systems' => $systems,
            'fromId' => $fromId,
            'toId' => $toId,
            'comparisonTable' => $comparisonTable
        ]);
    }
}