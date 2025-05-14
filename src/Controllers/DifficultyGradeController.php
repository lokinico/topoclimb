<?php
// src/Controllers/DifficultyGradeController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\DifficultyGrade;
use TopoclimbCH\Services\DifficultyService;
use TopoclimbCH\Services\AuthService;

class DifficultyGradeController extends BaseController
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
     * Affiche la liste des grades pour un système de difficulté
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $systemId = (int) $request->attributes->get('system_id');
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        $grades = $this->difficultyService->getSystemGrades($systemId);
        
        return $this->render('difficulty/grades/index', [
            'system' => $system,
            'grades' => $grades,
            'title' => 'Grades du système ' . $system->name
        ]);
    }
    
    /**
     * Affiche le formulaire de création d'un grade
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $systemId = (int) $request->attributes->get('system_id');
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        return $this->render('difficulty/grades/create', [
            'system' => $system,
            'title' => 'Ajouter un grade au système ' . $system->name,
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Enregistre un nouveau grade
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $systemId = (int) $request->attributes->get('system_id');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades/create');
        }
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'value' => 'required|max:10',
            'numerical_value' => 'required|numeric',
            'sort_order' => 'required|numeric'
        ]);
        
        $data['system_id'] = $systemId;
        
        try {
            // Crée le grade
            $grade = $this->difficultyService->createGrade($data);
            
            $this->flash('success', 'Grade créé avec succès');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la création du grade: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades/create');
        }
    }
    
    /**
     * Affiche le formulaire d'édition d'un grade
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $systemId = (int) $request->attributes->get('system_id');
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        $grade = $this->difficultyService->getGrade($id);
        
        if (!$grade || $grade->system_id != $systemId) {
            $this->flash('error', 'Grade non trouvé dans ce système');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        }
        
        return $this->render('difficulty/grades/edit', [
            'system' => $system,
            'grade' => $grade,
            'title' => 'Modifier le grade ' . $grade->value,
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour un grade
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $systemId = (int) $request->attributes->get('system_id');
        $id = (int) $request->attributes->get('id');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades/' . $id . '/edit');
        }
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        $grade = $this->difficultyService->getGrade($id);
        
        if (!$grade || $grade->system_id != $systemId) {
            $this->flash('error', 'Grade non trouvé dans ce système');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'value' => 'required|max:10',
            'numerical_value' => 'required|numeric',
            'sort_order' => 'required|numeric'
        ]);
        
        try {
            // Met à jour le grade
            $grade = $this->difficultyService->updateGrade($grade, $data);
            
            $this->flash('success', 'Grade mis à jour avec succès');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du grade: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades/' . $id . '/edit');
        }
    }
    
    /**
     * Supprime un grade
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $systemId = (int) $request->attributes->get('system_id');
        $id = (int) $request->attributes->get('id');
        
        $system = $this->difficultyService->getSystem($systemId);
        
        if (!$system) {
            $this->flash('error', 'Système de difficulté non trouvé');
            return $this->redirect('/difficulty/systems');
        }
        
        $grade = $this->difficultyService->getGrade($id);
        
        if (!$grade || $grade->system_id != $systemId) {
            $this->flash('error', 'Grade non trouvé dans ce système');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        }
        
        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            // Vérifie si le grade est utilisé par des voies
            $routesCount = $this->difficultyService->getGradeRoutesCount($id);
            
            return $this->render('difficulty/grades/delete', [
                'system' => $system,
                'grade' => $grade,
                'routesCount' => $routesCount,
                'title' => 'Supprimer le grade ' . $grade->value,
                'csrf_token' => $this->createCsrfToken()
            ]);
        }
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades/' . $id . '/delete');
        }
        
        try {
            // Supprime le grade
            $this->difficultyService->deleteGrade($grade);
            
            $this->flash('success', 'Grade supprimé avec succès');
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression du grade: ' . $e->getMessage());
            return $this->redirect('/difficulty/systems/' . $systemId . '/grades');
        }
    }
}