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
        $regionId = $request->query->get('region_id');
        
        if ($regionId) {
            $sectors = $this->sectorService->getSectorsByRegion((int) $regionId);
            $title = 'Secteurs par région';
        } else {
            $sectors = $this->sectorService->getAllSectors();
            $title = 'Tous les secteurs';
        }
        
        return $this->render('sectors/index.php', [
            'title' => $title,
            'sectors' => $sectors,
            'currentRegionId' => $regionId
        ]);
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
        
        $sector = $this->sectorService->getSectorById((int) $id);
        
        if (!$sector) {
            $this->session->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        // Get additional data
        $exposures = $this->sectorService->getSectorExposures((int) $id);
        $routes = $this->sectorService->getSectorRoutes((int) $id);
        $media = $this->sectorService->getSectorMedia((int) $id);
        
        return $this->render('sectors/show.php', [
            'title' => $sector['name'],
            'sector' => $sector,
            'exposures' => $exposures,
            'media' => $media,
            'routes' => $routes
        ]);
    }
    
    /**
     * Display create sector form
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Get data for form selections
        $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
        $books = $this->db->fetchAll("SELECT id, name FROM climbing_books WHERE active = 1 ORDER BY name ASC");
        $exposures = $this->db->fetchAll("SELECT id, name, code FROM climbing_exposures ORDER BY sort_order ASC");
        
        return $this->render('sectors/create.php', [
            'title' => 'Créer un nouveau secteur',
            'regions' => $regions,
            'books' => $books,
            'exposures' => $exposures,
            'csrf_token' => $this->createCsrfToken()
        ]);
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
        
        // Add the current user ID
        $data['created_by'] = $this->session->get('user_id');
        
        // Store the sector
        $sectorId = $this->sectorService->createSector($data);
        
        if (!$sectorId) {
            $this->session->flash('error', 'Erreur lors de la création du secteur');
            return $this->redirect('/sectors/create');
        }
        
        // Handle exposures if provided
        if (!empty($data['exposures'])) {
            foreach ($data['exposures'] as $exposureId) {
                $isPrimary = isset($data['primary_exposure']) && $data['primary_exposure'] == $exposureId;
                $this->db->insert('climbing_sector_exposures', [
                    'sector_id' => $sectorId,
                    'exposure_id' => (int) $exposureId,
                    'is_primary' => $isPrimary ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
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
        
        $this->session->flash('success', 'Secteur créé avec succès');
        return $this->redirect('/sectors/' . $sectorId);
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
        
        $sector = $this->sectorService->getSectorById((int) $id);
        
        if (!$sector) {
            $this->session->flash('error', 'Secteur non trouvé');
            return $this->redirect('/sectors');
        }
        
        // Get data for form selections
        $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
        $books = $this->db->fetchAll("SELECT id, name FROM climbing_books WHERE active = 1 ORDER BY name ASC");
        $exposures = $this->db->fetchAll("SELECT id, name, code FROM climbing_exposures ORDER BY sort_order ASC");
        
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
        
        // Get media for this sector
        $media = $this->sectorService->getSectorMedia((int) $id);
        
        return $this->render('sectors/edit.php', [
            'title' => 'Modifier le secteur ' . $sector['name'],
            'sector' => $sector,
            'regions' => $regions,
            'books' => $books,
            'exposures' => $exposures,
            'currentExposures' => $currentExposures,
            'primaryExposure' => $primaryExposure,
            'media' => $media,
            'csrf_token' => $this->createCsrfToken()
        ]);
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
            return $this->redirect('/sectors');
        }
        
        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/sectors/' . $id . '/edit');
        }
        
        // Get form data
        $data = $request->request->all();
        
        // Basic validation
        if (empty($data['name']) || empty($data['code']) || empty($data['book_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires');
            return $this->redirect('/sectors/' . $id . '/edit');
        }
        
        // Add the current user ID
        $data['updated_by'] = $this->session->get('user_id');
        
        // Update the sector
        $success = $this->sectorService->updateSector((int) $id, $data);
        
        if (!$success) {
            $this->session->flash('error', 'Erreur lors de la mise à jour du secteur');
            return $this->redirect('/sectors/' . $id . '/edit');
        }
        
        // Handle exposures - first delete existing
        $this->db->delete('climbing_sector_exposures', "sector_id = ?", [(int) $id]);
        
        // Then add new ones
        if (!empty($data['exposures'])) {
            foreach ($data['exposures'] as $exposureId) {
                $isPrimary = isset($data['primary_exposure']) && $data['primary_exposure'] == $exposureId;
                $this->db->insert('climbing_sector_exposures', [
                    'sector_id' => (int) $id,
                    'exposure_id' => (int) $exposureId,
                    'is_primary' => $isPrimary ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        // Handle uploaded image if any
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $this->mediaService->uploadMedia($_FILES['image'], [
                'entity_type' => 'sector',
                'entity_id' => (int) $id,
                'relationship_type' => 'main',
                'title' => $data['name'],
                'is_public' => 1
            ], $this->session->get('user_id'));
        }
        
        $this->session->flash('success', 'Secteur mis à jour avec succès');
        return $this->redirect('/sectors/' . $id);
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
            $sector = $this->sectorService->getSectorById((int) $id);
            
            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }
            
            // Get routes count
            $routesCount = count($this->sectorService->getSectorRoutes((int) $id));
            
            // Show confirmation page
            return $this->render('sectors/delete.php', [
                'title' => 'Supprimer le secteur ' . $sector['name'],
                'sector' => $sector,
                'routesCount' => $routesCount,
                'csrf_token' => $this->createCsrfToken()
            ]);
        }
        
        // It's a POST request, proceed with deletion
        
        // Validate CSRF token
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/sectors/' . $id . '/delete');
        }
        
        $success = $this->sectorService->deleteSector((int) $id);
        
        if (!$success) {
            $this->session->flash('error', 'Erreur lors de la suppression du secteur');
            return $this->redirect('/sectors/' . $id . '/delete');
        }
        
        $this->session->flash('success', 'Secteur supprimé avec succès');
        return $this->redirect('/sectors');
    }
}