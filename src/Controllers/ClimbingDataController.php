<?php
// src/Controllers/ClimbingDataController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Services\ClimbingDataService;
use TopoclimbCH\Services\AuthService;

class ClimbingDataController extends BaseController
{
    /**
     * @var ClimbingDataService
     */
    protected ClimbingDataService $climbingDataService;
    
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
     * @param ClimbingDataService $climbingDataService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        ClimbingDataService $climbingDataService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->climbingDataService = $climbingDataService;
        $this->authService = $authService;
        $this->db = $db;
    }
    
    /**
     * Affiche la liste des expositions
     *
     * @param Request $request
     * @return Response
     */
    public function exposures(Request $request): Response
    {
        $exposures = $this->climbingDataService->getAllExposures();
        
        return $this->render('climbing-data/exposures/index', [
            'exposures' => $exposures,
            'title' => 'Expositions'
        ]);
    }
    
    /**
     * Gère les expositions (admin)
     *
     * @param Request $request
     * @return Response
     */
    public function manageExposures(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $exposures = $this->climbingDataService->getAllExposures();
        
        return $this->render('climbing-data/exposures/manage', [
            'exposures' => $exposures,
            'title' => 'Gérer les expositions',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour les expositions (admin)
     *
     * @param Request $request
     * @return Response
     */
    public function updateExposures(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/admin/climbing-data/exposures');
        }
        
        $exposuresData = $request->request->get('exposures', []);
        
        try {
            // Met à jour les expositions
            $this->climbingDataService->updateExposures($exposuresData);
            
            $this->flash('success', 'Expositions mises à jour avec succès');
            return $this->redirect('/admin/climbing-data/exposures');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour des expositions: ' . $e->getMessage());
            return $this->redirect('/admin/climbing-data/exposures');
        }
    }
    
    /**
     * Affiche la liste des mois pour la saisonnalité
     *
     * @param Request $request
     * @return Response
     */
    public function months(Request $request): Response
    {
        $months = $this->climbingDataService->getAllMonths();
        
        return $this->render('climbing-data/months/index', [
            'months' => $months,
            'title' => 'Mois pour la saisonnalité'
        ]);
    }
    
    /**
     * Gère les mois (admin)
     *
     * @param Request $request
     * @return Response
     */
    public function manageMonths(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $months = $this->climbingDataService->getAllMonths();
        
        return $this->render('climbing-data/months/manage', [
            'months' => $months,
            'title' => 'Gérer les mois',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour les mois (admin)
     *
     * @param Request $request
     * @return Response
     */
    public function updateMonths(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/admin/climbing-data/months');
        }
        
        $monthsData = $request->request->get('months', []);
        
        try {
            // Met à jour les mois
            $this->climbingDataService->updateMonths($monthsData);
            
            $this->flash('success', 'Mois mis à jour avec succès');
            return $this->redirect('/admin/climbing-data/months');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour des mois: ' . $e->getMessage());
            return $this->redirect('/admin/climbing-data/months');
        }
    }
    
    /**
     * Gère la saisonnalité d'un secteur
     *
     * @param Request $request
     * @return Response
     */
    public function manageSectorMonths(Request $request): Response
    {
        $sectorId = (int) $request->attributes->get('sector_id');
        
        // Vérifie les permissions
        $this->authorize('update-sector');
        
        // Récupère le secteur
        $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$sectorId]);
        
        if (!$sector) {
            $this->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        // Récupère tous les mois
        $months = $this->climbingDataService->getAllMonths();
        
        // Récupère la matrice de qualité actuelle
        $qualityMatrix = $this->climbingDataService->getSectorMonthsMatrix($sectorId);
        
        return $this->render('climbing-data/sectors/months', [
            'sector' => $sector,
            'months' => $months,
            'qualityMatrix' => $qualityMatrix,
            'qualityOptions' => [
                'excellent' => 'Excellent',
                'good' => 'Bon',
                'average' => 'Moyen',
                'poor' => 'Mauvais',
                'avoid' => 'À éviter'
            ],
            'title' => 'Gérer la saisonnalité de ' . $sector['name'],
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour la saisonnalité d'un secteur
     *
     * @param Request $request
     * @return Response
     */
    public function updateSectorMonths(Request $request): Response
    {
        $sectorId = (int) $request->attributes->get('sector_id');
        
        // Vérifie les permissions
        $this->authorize('update-sector');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/sectors/' . $sectorId . '/months');
        }
        
        // Récupère le secteur
        $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$sectorId]);
        
        if (!$sector) {
            $this->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        $monthsData = $request->request->get('months', []);
        
        try {
            // Met à jour la saisonnalité
            $this->climbingDataService->updateSectorMonths($sectorId, $monthsData);
            
            $this->flash('success', 'Saisonnalité mise à jour avec succès');
            return $this->redirect('/sectors/' . $sectorId);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour de la saisonnalité: ' . $e->getMessage());
            return $this->redirect('/sectors/' . $sectorId . '/months');
        }
    }
    
    /**
     * Gère les expositions d'un secteur
     *
     * @param Request $request
     * @return Response
     */
    public function manageSectorExposures(Request $request): Response
    {
        $sectorId = (int) $request->attributes->get('sector_id');
        
        // Vérifie les permissions
        $this->authorize('update-sector');
        
        // Récupère le secteur
        $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$sectorId]);
        
        if (!$sector) {
            $this->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        // Récupère toutes les expositions
        $exposures = $this->climbingDataService->getAllExposures();
        
        // Récupère les expositions actuelles du secteur
        $sectorExposures = $this->climbingDataService->getSectorExposures($sectorId);
        $currentExposures = array_column($sectorExposures, 'exposure_id');
        $primaryExposure = null;
        
        foreach ($sectorExposures as $exposure) {
            if (isset($exposure['is_primary']) && $exposure['is_primary']) {
                $primaryExposure = $exposure['exposure_id'];
                break;
            }
        }
        
        return $this->render('climbing-data/sectors/exposures', [
            'sector' => $sector,
            'exposures' => $exposures,
            'currentExposures' => $currentExposures,
            'primaryExposure' => $primaryExposure,
            'title' => 'Gérer les expositions de ' . $sector['name'],
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour les expositions d'un secteur
     *
     * @param Request $request
     * @return Response
     */
    public function updateSectorExposures(Request $request): Response
    {
        $sectorId = (int) $request->attributes->get('sector_id');
        
        // Vérifie les permissions
        $this->authorize('update-sector');
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/sectors/' . $sectorId . '/exposures');
        }
        
        // Récupère le secteur
        $sector = $this->db->fetchOne("SELECT * FROM climbing_sectors WHERE id = ?", [$sectorId]);
        
        if (!$sector) {
            $this->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        $exposureIds = $request->request->get('exposures', []);
        $primaryExposure = $request->request->get('primary_exposure');
        
        try {
            // Met à jour les expositions
            $this->climbingDataService->updateSectorExposures($sectorId, $exposureIds, $primaryExposure);
            
            $this->flash('success', 'Expositions mises à jour avec succès');
            return $this->redirect('/sectors/' . $sectorId);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour des expositions: ' . $e->getMessage());
            return $this->redirect('/sectors/' . $sectorId . '/exposures');
        }
    }
}