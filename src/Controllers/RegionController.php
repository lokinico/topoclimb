<?php
// src/Controllers/RegionController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\CountryService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Core\Security\CsrfManager;

class RegionController extends BaseController
{
    /**
     * @var RegionService
     */
    protected RegionService $regionService;

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
     * @param RegionService $regionService
     * @param CountryService $countryService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        CountryService $countryService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->regionService = $regionService;
        $this->countryService = $countryService;
        $this->authService = $authService;
        $this->db = $db;
    }

    /**
     * Affiche la liste des régions
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $countryId = $request->query->get('country_id');

        if ($countryId) {
            $regions = $this->regionService->getRegionsByCountry((int) $countryId);
            $country = $this->countryService->getCountry((int) $countryId);
            $title = 'Régions de ' . ($country ? $country->name : 'pays inconnu');
        } else {
            $regions = $this->regionService->getAllRegions();
            $title = 'Toutes les régions';
        }

        // Récupérer tous les pays pour le filtre
        $countries = $this->countryService->getAllCountries();

        return $this->render('regions/index', [
            'regions' => $regions,
            'countries' => $countries,
            'currentCountryId' => $countryId,
            'title' => $title
        ]);
    }

    /**
     * Affiche une région spécifique
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        $region = $this->regionService->getRegionWithRelations($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        // Récupérer les secteurs de cette région
        $sectors = $this->regionService->getRegionSectors($id);

        // Récupérer les statistiques de la région
        $stats = $this->regionService->getRegionStatistics($id);

        return $this->render('regions/show', [
            'region' => $region,
            'sectors' => $sectors,
            'stats' => $stats,
            'title' => $region->name
        ]);
    }

    /**
     * Affiche le formulaire de création d'une région
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');

        // Récupère les pays pour le dropdown
        $countries = $this->countryService->getAllCountries();

        return $this->render('regions/create', [
            'countries' => $countries,
            'title' => 'Créer une nouvelle région',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Enregistre une nouvelle région
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
            return $this->redirect('/regions/create');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'country_id' => 'required|numeric',
            'name' => 'required|max:100',
            'description' => 'nullable',
            'active' => 'in:0,1'
        ]);

        // Ajoute l'utilisateur courant comme créateur
        if ($this->authService->check()) {
            $data['created_by'] = $this->authService->id();
        }

        try {
            // Crée la région
            $region = $this->regionService->createRegion($data);

            $this->flash('success', 'Région créée avec succès');
            return $this->redirect('/regions/' . $region->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la création de la région: ' . $e->getMessage());
            return $this->redirect('/regions/create');
        }
    }

    /**
     * Affiche le formulaire d'édition d'une région
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');

        $id = (int) $request->attributes->get('id');

        $region = $this->regionService->getRegion($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        // Récupère les pays pour le dropdown
        $countries = $this->countryService->getAllCountries();

        return $this->render('regions/edit', [
            'region' => $region,
            'countries' => $countries,
            'title' => 'Modifier ' . $region->name,
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Met à jour une région
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
            return $this->redirect('/regions/' . $id . '/edit');
        }

        $region = $this->regionService->getRegion($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'country_id' => 'required|numeric',
            'name' => 'required|max:100',
            'description' => 'nullable',
            'active' => 'in:0,1'
        ]);

        // Ajoute l'utilisateur courant comme modificateur
        if ($this->authService->check()) {
            $data['updated_by'] = $this->authService->id();
        }

        try {
            // Met à jour la région
            $region = $this->regionService->updateRegion($region, $data);

            $this->flash('success', 'Région mise à jour avec succès');
            return $this->redirect('/regions/' . $region->id);
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour de la région: ' . $e->getMessage());
            return $this->redirect('/regions/' . $id . '/edit');
        }
    }

    /**
     * Supprime une région
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-climbing-data');

        $id = (int) $request->attributes->get('id');

        $region = $this->regionService->getRegion($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            // Vérifie si la région contient des secteurs
            $sectorsCount = count($this->regionService->getRegionSectors($id));

            return $this->render('regions/delete', [
                'region' => $region,
                'sectorsCount' => $sectorsCount,
                'title' => 'Supprimer ' . $region->name,
                'csrf_token' => $this->createCsrfToken()
            ]);
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/regions/' . $id . '/delete');
        }

        try {
            // Supprime la région
            $this->regionService->deleteRegion($region);

            $this->flash('success', 'Région supprimée avec succès');
            return $this->redirect('/regions');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression de la région: ' . $e->getMessage());
            return $this->redirect('/regions/' . $id);
        }
    }
}
