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
use TopoclimbCH\Models\SectorExposure;
use TopoclimbCH\Exceptions\ServiceException;
use TopoclimbCH\Core\Filtering\SectorFilter;

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
            
            // Paginer les résultats filtrés
            $paginatedSectors = \TopoclimbCH\Models\Sector::filterAndPaginate(
                $filter,
                $page,
                $perPage,
                'name',
                'ASC'
            );
            
            // Récupérer les données pour les filtres
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $exposures = \TopoclimbCH\Models\Exposure::getAllSorted();
            $months = \TopoclimbCH\Models\Month::getAllSorted();
            
            return $this->render('sectors/index.twig', [
                'sectors' => $paginatedSectors,
                'filter' => $filter,
                'regions' => $regions,
                'exposures' => $exposures,
                'months' => $months,
                'currentUrl' => $request->getPathInfo()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement des secteurs: ' . $e->getMessage());
            return $this->render('sectors/index.twig', [
                'sectors' => [],
                'regions' => [],
                'exposures' => [],
                'months' => []
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
            $sector = $this->sectorService->getSectorById((int) $id);
            
            if (!$sector) {
                $this->session->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }
            
            // Get additional data
            $exposures = $this->sectorService->getSectorExposures((int) $id);
            $routes = $this->sectorService->getSectorRoutes((int) $id);
            $media = $this->sectorService->getSectorMedia((int) $id);
            
            return $this->render('sectors/show.twig', [
                'title' => $sector['name'],
                'sector' => $sector,
                'exposures' => $exposures,
                'media' => $media,
                'routes' => $routes
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement du secteur: ' . $e->getMessage());
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
            
            return $this->render('sectors/form.twig', [
                'title' => 'Créer un nouveau secteur',
                'sector' => [],
                'regions' => $regions,
                'books' => $books,
                'exposures' => $exposures,
                'currentExposures' => [],
                'primaryExposure' => null,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement du formulaire: ' . $e->getMessage());
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
        
        try {
            // Add the current user ID
            $data['created_by'] = $this->session->get('user_id');
            
            // Store the sector
            $sectorId = $this->sectorService->createSector($data);
            
            // Handle exposures if provided
            if (!empty($data['exposures'])) {
                $primaryExposure = $data['primary_exposure'] ?? null;
                SectorExposure::linkSectorToExposures($sectorId, $data['exposures'], $primaryExposure);
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
        } catch (ServiceException $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect('/sectors/create');
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log($e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors de la création du secteur');
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
            
            return $this->render('sectors/form.twig', [
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
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect('/sectors');
        }
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
        
        try {
            // Add the current user ID
            $data['updated_by'] = $this->session->get('user_id');
            
            // Update the sector
            $success = $this->sectorService->updateSector((int) $id, $data);
            
            // Handle exposures
            if (!empty($data['exposures'])) {
                $primaryExposure = $data['primary_exposure'] ?? null;
                SectorExposure::linkSectorToExposures((int) $id, $data['exposures'], $primaryExposure);
            } else {
                // Remove all exposures if none selected
                $this->db->delete('climbing_sector_exposures', "sector_id = ?", [(int) $id]);
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
        } catch (ServiceException $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect('/sectors/' . $id . '/edit');
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log($e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors de la mise à jour du secteur');
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
                
                // Show confirmation page
                return $this->render('sectors/delete.twig', [
                    'title' => 'Supprimer le secteur ' . $sector['name'],
                    'sector' => $sector,
                    'routesCount' => $routesCount,
                    'csrf_token' => $this->createCsrfToken()
                ]);
            } catch (\Exception $e) {
                $this->session->flash('error', 'Erreur: ' . $e->getMessage());
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
            $success = $this->sectorService->deleteSector((int) $id);
            
            if (!$success) {
                throw new ServiceException("Impossible de supprimer le secteur");
            }
            
            $this->session->flash('success', 'Secteur supprimé avec succès');
            return $this->redirect('/sectors');
        } catch (ServiceException $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect('/sectors/' . $id . '/delete');
        } catch (\Exception $e) {
            // Log the error
            error_log($e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors de la suppression du secteur');
            return $this->redirect('/sectors/' . $id . '/delete');
        }
    }
}