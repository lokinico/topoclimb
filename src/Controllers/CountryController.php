<?php
// src/Controllers/CountryController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Country;
use TopoclimbCH\Services\CountryService;
use TopoclimbCH\Services\AuthService;

class CountryController extends BaseController
{
    /**
     * @var CountryService
     */
    protected CountryService $countryService;
    
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
     * @param CountryService $countryService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        CountryService $countryService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->countryService = $countryService;
        $this->authService = $authService;
        $this->db = $db;
    }
    
    /**
     * Affiche la liste des pays
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $countries = $this->countryService->getAllCountries();
        
        return $this->render('countries/index', [
            'countries' => $countries,
            'title' => 'Tous les pays'
        ]);
    }
    
    /**
     * Affiche un pays spécifique
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        
        $country = $this->countryService->getCountryWithStatistics($id);
        
        if (!$country) {
            $this->flash('error', 'Pays non trouvé');
            return $this->redirect('/countries');
        }
        
        // Récupère les régions associées
        $regions = $this->countryService->getCountryRegions($id);
        
        return $this->render('countries/show', [
            'country' => $country,
            'regions' => $regions,
            'title' => $country->name
        ]);
    }
    
    /**
     * Affiche le formulaire de création d'un pays
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        return $this->render('countries/create', [
            'title' => 'Créer un nouveau pays',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Enregistre un nouveau pays
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
            return $this->redirect('/countries/create');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'name' => 'required|max:100',
            'code' => 'required|max:2',
            'description' => 'nullable',
            'active' => 'in:0,1'
        ]);
        
        try {
            // Crée le pays
            $country = $this->countryService->createCountry($data);
            
            $this->flash('success', 'Pays créé avec succès');
            return $this->redirect('/countries/' . $country->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la création du pays: ' . $e->getMessage());
            return $this->redirect('/countries/create');
        }
    }
    
    /**
     * Affiche le formulaire d'édition d'un pays
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $id = (int) $request->attributes->get('id');
        
        $country = $this->countryService->getCountry($id);
        
        if (!$country) {
            $this->flash('error', 'Pays non trouvé');
            return $this->redirect('/countries');
        }
        
        return $this->render('countries/edit', [
            'country' => $country,
            'title' => 'Modifier ' . $country->name,
            'csrf_token' => $this->createCsrfToken()
        ]);
    }
    
    /**
     * Met à jour un pays
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
            return $this->redirect('/countries/' . $id . '/edit');
        }
        
        $country = $this->countryService->getCountry($id);
        
        if (!$country) {
            $this->flash('error', 'Pays non trouvé');
            return $this->redirect('/countries');
        }
        
        // Valide les données
        $data = $this->validate($request->request->all(), [
            'name' => 'required|max:100',
            'code' => 'required|max:2',
            'description' => 'nullable',
            'active' => 'in:0,1'
        ]);
        
        try {
            // Met à jour le pays
            $country = $this->countryService->updateCountry($country, $data);
            
            $this->flash('success', 'Pays mis à jour avec succès');
            return $this->redirect('/countries/' . $country->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du pays: ' . $e->getMessage());
            return $this->redirect('/countries/' . $id . '/edit');
        }
    }
    
    /**
     * Supprime un pays
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');
        
        $id = (int) $request->attributes->get('id');
        
        $country = $this->countryService->getCountry($id);
        
        if (!$country) {
            $this->flash('error', 'Pays non trouvé');
            return $this->redirect('/countries');
        }
        
        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            // Vérifie si le pays contient des régions
            $regionsCount = count($this->countryService->getCountryRegions($id));
            
            return $this->render('countries/delete', [
                'country' => $country,
                'regionsCount' => $regionsCount,
                'title' => 'Supprimer ' . $country->name,
                'csrf_token' => $this->createCsrfToken()
            ]);
        }
        
        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/countries/' . $id . '/delete');
        }
        
        try {
            // Supprime le pays
            $this->countryService->deleteCountry($country);
            
            $this->flash('success', 'Pays supprimé avec succès');
            return $this->redirect('/countries');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression du pays: ' . $e->getMessage());
            return $this->redirect('/countries/' . $id);
        }
    }
}