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
use TopoclimbCH\Core\Security\CsrfManager;

class RegionController extends BaseController
{
    protected RegionService $regionService;
    protected Database $db;

    public function __construct(View $view, Session $session, CsrfManager $csrfManager)
    {
        parent::__construct($view, $session, $csrfManager);

        // Créer les services à la demande
        $this->db = Database::getInstance();
        $this->regionService = new RegionService($this->db);
    }

    /**
     * Affiche la liste des régions
     */
    public function index(Request $request): Response
    {
        $countryId = $request->query->get('country_id');

        if ($countryId) {
            $regions = $this->regionService->getRegionsByCountry((int) $countryId);
            // Pour l'instant, récupérer le nom du pays directement depuis la base
            $countryResult = $this->db->query("SELECT name FROM climbing_countries WHERE id = ?", [(int) $countryId]);
            $countryName = $countryResult[0]['name'] ?? 'pays inconnu';
            $title = 'Régions de ' . $countryName;
        } else {
            $regions = $this->regionService->getAllRegions();
            $title = 'Toutes les régions';
        }

        // Récupérer tous les pays pour le filtre
        $countries = $this->db->query("SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name");

        return $this->render('regions/index', [
            'regions' => $regions,
            'countries' => $countries,
            'currentCountryId' => $countryId,
            'title' => $title
        ]);
    }

    /**
     * Affiche une région spécifique
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
     */
    public function create(Request $request): Response
    {
        // Vérifie les permissions
        try {
            $this->authorize('manage-climbing-data');
        } catch (\Exception $e) {
            $this->flash('error', 'Vous n\'avez pas les permissions nécessaires');
            return $this->redirect('/regions');
        }

        // Récupère les pays pour le dropdown
        $countries = $this->db->query("SELECT * FROM climbing_countries WHERE active = 1 ORDER BY name");

        return $this->render('regions/create', [
            'countries' => $countries,
            'title' => 'Créer une nouvelle région',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Enregistre une nouvelle région
     */
    public function store(Request $request): Response
    {
        // Vérifie les permissions
        try {
            $this->authorize('manage-climbing-data');
        } catch (\Exception $e) {
            $this->flash('error', 'Vous n\'avez pas les permissions nécessaires');
            return $this->redirect('/regions');
        }

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
        if ($this->auth && $this->auth->check()) {
            $data['created_by'] = $this->auth->id();
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
}
