<?php
// src/Controllers/UserAscentController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\UserAscent;
use TopoclimbCH\Services\AscentService;
use TopoclimbCH\Services\RouteService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Core\Security\CsrfManager;

class UserAscentController extends BaseController
{
    /**
     * @var AscentService
     */
    protected AscentService $ascentService;

    /**
     * @var RouteService
     */
    protected RouteService $routeService;

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
     * @param AscentService $ascentService
     * @param RouteService $routeService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        AscentService $ascentService,
        RouteService $routeService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->ascentService = $ascentService;
        $this->routeService = $routeService;
        $this->authService = $authService;
        $this->db = $db;
    }

    /**
     * Affiche la liste des ascensions de l'utilisateur connecté
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        // Récupère les paramètres de filtrage
        $filters = $request->query->all();
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 50);

        // Récupère les ascensions
        $userId = $this->authService->id();
        $ascents = $this->ascentService->getUserAscents($userId, $filters, $page, $perPage);

        // Récupère les statistiques d'ascension
        $stats = $this->ascentService->getUserStats($userId);

        return $this->render('ascents/index', [
            'ascents' => $ascents,
            'stats' => $stats,
            'filters' => $filters,
            'ascentTypes' => UserAscent::ASCENT_TYPES,
            'climbingTypes' => UserAscent::CLIMBING_TYPES,
            'title' => 'Mes ascensions'
        ]);
    }

    /**
     * Affiche le profil d'ascensions d'un utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function profile(Request $request): Response
    {
        $userId = (int) $request->attributes->get('id');

        // Récupère l'utilisateur
        $user = $this->authService->getUserById($userId);

        if (!$user) {
            $this->flash('error', 'Utilisateur non trouvé');
            return $this->redirect('/');
        }

        // Récupère les paramètres de filtrage
        $filters = $request->query->all();
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 30);

        // Récupère les ascensions publiques de l'utilisateur
        $ascents = $this->ascentService->getUserPublicAscents($userId, $filters, $page, $perPage);

        // Récupère les statistiques publiques d'ascension
        $stats = $this->ascentService->getUserPublicStats($userId);

        return $this->render('ascents/profile', [
            'user' => $user,
            'ascents' => $ascents,
            'stats' => $stats,
            'filters' => $filters,
            'ascentTypes' => UserAscent::ASCENT_TYPES,
            'climbingTypes' => UserAscent::CLIMBING_TYPES,
            'title' => 'Profil de ' . $user->getFullNameAttribute()
        ]);
    }

    /**
     * Affiche le formulaire d'enregistrement d'une ascension
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        // Récupère la voie si fournie
        $routeId = (int) $request->query->get('route_id', 0);
        $route = $routeId ? $this->routeService->getRoute($routeId) : null;

        return $this->render('ascents/create', [
            'route' => $route,
            'ascentTypes' => UserAscent::ASCENT_TYPES,
            'climbingTypes' => UserAscent::CLIMBING_TYPES,
            'difficultyComments' => [
                'easy' => 'Plus facile que la cotation',
                'accurate' => 'Cotation correcte',
                'hard' => 'Plus difficile que la cotation'
            ],
            'title' => 'Enregistrer une ascension',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Enregistre une nouvelle ascension
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour enregistrer une ascension');
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/ascents/create');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'route_id' => 'required|numeric',
            'ascent_type' => 'required|in:' . implode(',', array_keys(UserAscent::ASCENT_TYPES)),
            'climbing_type' => 'required|in:' . implode(',', array_keys(UserAscent::CLIMBING_TYPES)),
            'ascent_date' => 'required|date',
            'quality_rating' => 'nullable|numeric|min:0|max:5',
            'difficulty_comment' => 'nullable|in:easy,accurate,hard',
            'attempts' => 'nullable|numeric|min:1',
            'comment' => 'nullable',
            'favorite' => 'in:0,1'
        ]);

        // Ajoute l'ID utilisateur
        $data['user_id'] = $this->authService->id();

        try {
            // Vérifie que la voie existe
            $route = $this->routeService->getRoute($data['route_id']);
            if (!$route) {
                throw new \Exception('La voie spécifiée n\'existe pas');
            }

            // Complète les données de la voie
            $data['route_name'] = $route->name;
            $data['difficulty'] = $route->difficulty;
            $data['topo_item'] = $route->legacy_topo_item;

            // Enregistre l'ascension
            $ascent = $this->ascentService->createAscent($data);

            $this->flash('success', 'Ascension enregistrée avec succès');
            return $this->redirect('/ascents');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de l\'enregistrement de l\'ascension: ' . $e->getMessage());
            return $this->redirect('/ascents/create?route_id=' . $data['route_id']);
        }
    }

    /**
     * Affiche le formulaire d'édition d'une ascension
     *
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $id = (int) $request->attributes->get('id');

        // Récupère l'ascension
        $ascent = $this->ascentService->getAscent($id);

        if (!$ascent) {
            $this->flash('error', 'Ascension non trouvée');
            return $this->redirect('/ascents');
        }

        // Vérifie que l'utilisateur est bien le propriétaire
        if ($ascent->user_id != $this->authService->id()) {
            $this->flash('error', 'Vous n\'avez pas l\'autorisation de modifier cette ascension');
            return $this->redirect('/ascents');
        }

        // Récupère la voie associée
        $route = $this->routeService->getRoute($ascent->route_id);

        return $this->render('ascents/edit', [
            'ascent' => $ascent,
            'route' => $route,
            'ascentTypes' => UserAscent::ASCENT_TYPES,
            'climbingTypes' => UserAscent::CLIMBING_TYPES,
            'difficultyComments' => [
                'easy' => 'Plus facile que la cotation',
                'accurate' => 'Cotation correcte',
                'hard' => 'Plus difficile que la cotation'
            ],
            'title' => 'Modifier une ascension',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Met à jour une ascension
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour modifier une ascension');
            return $this->redirect('/login');
        }

        $id = (int) $request->attributes->get('id');

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/ascents/' . $id . '/edit');
        }

        // Récupère l'ascension
        $ascent = $this->ascentService->getAscent($id);

        if (!$ascent) {
            $this->flash('error', 'Ascension non trouvée');
            return $this->redirect('/ascents');
        }

        // Vérifie que l'utilisateur est bien le propriétaire
        if ($ascent->user_id != $this->authService->id()) {
            $this->flash('error', 'Vous n\'avez pas l\'autorisation de modifier cette ascension');
            return $this->redirect('/ascents');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'ascent_type' => 'required|in:' . implode(',', array_keys(UserAscent::ASCENT_TYPES)),
            'climbing_type' => 'required|in:' . implode(',', array_keys(UserAscent::CLIMBING_TYPES)),
            'ascent_date' => 'required|date',
            'quality_rating' => 'nullable|numeric|min:0|max:5',
            'difficulty_comment' => 'nullable|in:easy,accurate,hard',
            'attempts' => 'nullable|numeric|min:1',
            'comment' => 'nullable',
            'favorite' => 'in:0,1'
        ]);

        try {
            // Met à jour l'ascension
            $ascent = $this->ascentService->updateAscent($ascent, $data);

            $this->flash('success', 'Ascension mise à jour avec succès');
            return $this->redirect('/ascents');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour de l\'ascension: ' . $e->getMessage());
            return $this->redirect('/ascents/' . $id . '/edit');
        }
    }

    /**
     * Supprime une ascension
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour supprimer une ascension');
            return $this->redirect('/login');
        }

        $id = (int) $request->attributes->get('id');

        // Récupère l'ascension
        $ascent = $this->ascentService->getAscent($id);

        if (!$ascent) {
            $this->flash('error', 'Ascension non trouvée');
            return $this->redirect('/ascents');
        }

        // Vérifie que l'utilisateur est bien le propriétaire
        if ($ascent->user_id != $this->authService->id()) {
            $this->flash('error', 'Vous n\'avez pas l\'autorisation de supprimer cette ascension');
            return $this->redirect('/ascents');
        }

        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            return $this->render('ascents/delete', [
                'ascent' => $ascent,
                'title' => 'Supprimer une ascension',
                'csrf_token' => $this->createCsrfToken()
            ]);
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/ascents/' . $id . '/delete');
        }

        try {
            // Supprime l'ascension
            $this->ascentService->deleteAscent($ascent);

            $this->flash('success', 'Ascension supprimée avec succès');
            return $this->redirect('/ascents');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression de l\'ascension: ' . $e->getMessage());
            return $this->redirect('/ascents');
        }
    }

    /**
     * Exporte les ascensions de l'utilisateur au format CSV
     *
     * @param Request $request
     * @return Response
     */
    public function export(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour exporter vos ascensions');
            return $this->redirect('/login');
        }

        $userId = $this->authService->id();

        try {
            // Génère le CSV
            $csvData = $this->ascentService->exportUserAscentsCSV($userId);

            // Configure la réponse
            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="ascensions_' . date('Y-m-d') . '.csv"');
            $response->setContent($csvData);

            return $response;
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de l\'exportation des ascensions: ' . $e->getMessage());
            return $this->redirect('/ascents');
        }
    }
}
