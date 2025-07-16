<?php
// src/Controllers/UserController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Models\User;
use TopoclimbCH\Models\UserAscent;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Services\UserService;
use TopoclimbCH\Services\AscentService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Core\Security\CsrfManager;

class UserController extends BaseController
{
    protected UserService $userService;
    protected AscentService $ascentService;
    protected AuthService $authService;

    public function __construct(View $view, Session $session, CsrfManager $csrfManager, ?Auth $auth = null, ?Database $db = null)
    {
        parent::__construct($view, $session, $csrfManager, $db, $auth);

        // Créer les services à la demande plutôt que par injection
        $this->db = $db ?? Database::getInstance();
        $this->userService = new UserService($this->db);
        $this->ascentService = new AscentService($this->db);
        
        // Utiliser l'Auth passé en paramètre ou créer un nouveau
        $authInstance = $auth ?? new Auth($session, $this->db);
        $this->authService = new AuthService($authInstance, $session, $this->db, new \TopoclimbCH\Services\Mailer($this->db));
    }

    /**
     * Affiche le profil de l'utilisateur connecté
     */
    public function profile(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $userId = $this->authService->id();
        $user = $this->authService->user();

        // Récupère les statistiques d'ascension
        $ascentStats = $this->ascentService->getUserStats($userId);

        // Récupère les ascensions récentes
        $recentAscents = $this->ascentService->getUserRecentAscents($userId, 5);

        // Récupère les voies favorites (simplifiées pour l'instant)
        $favoriteRoutes = $this->userService->getUserAscents($userId, ['favorite' => 1], 10);

        return $this->render('users/profile', [
            'user' => $user,
            'ascentStats' => $ascentStats,
            'recentAscents' => $recentAscents,
            'favoriteRoutes' => $favoriteRoutes,
            'title' => 'Mon profil'
        ]);
    }

    /**
     * Affiche la page des ascensions de l'utilisateur
     */
    public function ascents(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $userId = $this->authService->id();
        $user = $this->authService->user();

        // Vérifier les permissions selon le rôle
        $userRole = (int)($user->autorisation ?? 4);
        if (!in_array($userRole, [0, 1, 2, 3])) {
            $this->flash('error', 'Vous n\'avez pas accès à cette page.');
            return $this->redirect('/pending');
        }

        $page = (int) $request->query->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Filtres
        $filters = [
            'difficulty' => $request->query->get('difficulty'),
            'ascent_type' => $request->query->get('ascent_type'),
            'climbing_type' => $request->query->get('climbing_type'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'search' => $request->query->get('search')
        ];

        // Récupération des ascensions avec filtres
        $ascents = $this->getUserAscentsWithFilters($userId, $filters, $page, $perPage);

        // Données pour les filtres
        $difficulties = $this->getUserDifficulties($userId);
        $ascentTypes = UserAscent::ASCENT_TYPES;
        $climbingTypes = UserAscent::CLIMBING_TYPES;

        // Statistiques
        $ascentStats = $this->ascentService->getUserStats($userId);

        return $this->render('users/ascents', [
            'ascents' => $ascents['data'],
            'pagination' => $ascents['pagination'],
            'filters' => $filters,
            'difficulties' => $difficulties,
            'ascentTypes' => $ascentTypes,
            'climbingTypes' => $climbingTypes,
            'ascentStats' => $ascentStats,
            'title' => 'Mes ascensions'
        ]);
    }

    /**
     * Affiche la page des favoris de l'utilisateur
     */
    public function favorites(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $userId = $this->authService->id();
        $user = $this->authService->user();

        // Vérifier les permissions selon le rôle
        $userRole = (int)($user->autorisation ?? 4);
        if (!in_array($userRole, [0, 1, 2, 3])) {
            $this->flash('error', 'Vous n\'avez pas accès à cette page.');
            return $this->redirect('/pending');
        }

        $page = (int) $request->query->get('page', 1);
        $perPage = 20;

        // Filtres
        $filters = [
            'difficulty' => $request->query->get('difficulty'),
            'style' => $request->query->get('style'),
            'region' => $request->query->get('region'),
            'search' => $request->query->get('search')
        ];

        // Récupération des favoris
        $favorites = $this->getUserFavoritesWithFilters($userId, $filters, $page, $perPage);

        // Données pour les filtres
        $difficulties = $this->getUserFavoriteDifficulties($userId);
        $styles = ['sport', 'trad', 'boulder', 'multipitch', 'alpine'];
        $regions = $this->getActiveRegions();

        return $this->render('users/favorites', [
            'favorites' => $favorites['data'],
            'pagination' => $favorites['pagination'],
            'filters' => $filters,
            'difficulties' => $difficulties,
            'styles' => $styles,
            'regions' => $regions,
            'title' => 'Mes favoris'
        ]);
    }

    /**
     * Affiche la page des paramètres utilisateur
     */
    public function settings(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $user = $this->authService->user();

        return $this->render('users/settings', [
            'user' => $user,
            'title' => 'Paramètres du compte',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Page d'attente pour les nouveaux membres
     */
    public function pending(Request $request): Response
    {
        if (!$this->authService->check()) {
            return $this->redirect('/login');
        }

        $user = $this->authService->user();
        $userRole = (int)($user->autorisation ?? 4);

        // Si l'utilisateur n'est pas en attente, rediriger
        if ($userRole !== 4) {
            return $this->redirect('/profile');
        }

        return $this->render('users/pending', [
            'user' => $user,
            'title' => 'Compte en attente',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Page pour les utilisateurs bannis
     */
    public function banned(Request $request): Response
    {
        return $this->render('users/banned', [
            'title' => 'Compte suspendu'
        ]);
    }

    /**
     * Met à jour le profil utilisateur
     */
    public function updateProfile(Request $request): Response
    {
        if (!$this->authService->check()) {
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/settings');
        }

        $userId = $this->authService->id();
        $user = $this->authService->user();

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'nom' => 'required|max:255',
            'prenom' => 'required|max:255',
            'ville' => 'nullable|max:255',
            'mail' => 'required|email|max:255',
            'username' => 'required|max:100'
        ]);

        try {
            // Met à jour l'utilisateur
            $this->userService->updateProfile($user, $data);
            $this->flash('success', 'Profil mis à jour avec succès');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du profil: ' . $e->getMessage());
        }

        return $this->redirect('/settings');
    }

    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(Request $request): Response
    {
        if (!$this->authService->check()) {
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/settings');
        }

        $user = $this->authService->user();
        $data = $request->request->all();

        // Validation
        if (empty($data['current_password']) || empty($data['new_password']) || empty($data['new_password_confirmation'])) {
            $this->flash('error', 'Tous les champs sont requis');
            return $this->redirect('/settings');
        }

        // Vérifier le mot de passe actuel
        if (!password_verify($data['current_password'], $user->password)) {
            $this->flash('error', 'Mot de passe actuel incorrect');
            return $this->redirect('/settings');
        }

        // Vérifier que les nouveaux mots de passe correspondent
        if ($data['new_password'] !== $data['new_password_confirmation']) {
            $this->flash('error', 'Les nouveaux mots de passe ne correspondent pas');
            return $this->redirect('/settings');
        }

        try {
            // Mettre à jour le mot de passe
            $this->userService->updatePassword($user, $data['new_password']);
            $this->flash('success', 'Mot de passe mis à jour avec succès');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du mot de passe');
        }

        return $this->redirect('/settings');
    }

    /**
     * API - Toggle favori
     */
    public function toggleFavorite(Request $request): Response
    {
        if (!$this->authService->check()) {
            return Response::json(['error' => 'Non autorisé'], 401);
        }

        $userId = $this->authService->id();
        $ascentId = $request->attributes->get('id');

        try {
            $result = $this->ascentService->toggleFavorite($userId, $ascentId);
            return Response::json([
                'success' => true,
                'favorite' => $result
            ]);
        } catch (\Exception $e) {
            return Response::json(['error' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    /**
     * API - Supprimer une ascension
     */
    public function deleteAscent(Request $request): Response
    {
        if (!$this->authService->check()) {
            return Response::json(['error' => 'Non autorisé'], 401);
        }

        $userId = $this->authService->id();
        $ascentId = $request->attributes->get('id');

        try {
            $this->ascentService->deleteUserAscent($userId, $ascentId);
            return Response::json(['success' => true]);
        } catch (\Exception $e) {
            return Response::json(['error' => 'Erreur lors de la suppression'], 500);
        }
    }

    /**
     * Récupère les ascensions avec filtres et pagination
     */
    private function getUserAscentsWithFilters(int $userId, array $filters, int $page, int $perPage): array
    {
        $query = "SELECT ua.*, r.name as route_name, s.name as sector_name, reg.name as region_name 
                  FROM user_ascents ua 
                  LEFT JOIN climbing_routes r ON ua.route_id = r.id 
                  LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                  LEFT JOIN climbing_regions reg ON s.region_id = reg.id 
                  WHERE ua.user_id = ?";

        $params = [$userId];

        // Appliquer les filtres
        if (!empty($filters['difficulty'])) {
            $query .= " AND ua.difficulty = ?";
            $params[] = $filters['difficulty'];
        }
        if (!empty($filters['ascent_type'])) {
            $query .= " AND ua.ascent_type = ?";
            $params[] = $filters['ascent_type'];
        }
        if (!empty($filters['climbing_type'])) {
            $query .= " AND ua.climbing_type = ?";
            $params[] = $filters['climbing_type'];
        }
        if (!empty($filters['date_from'])) {
            $query .= " AND ua.ascent_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND ua.ascent_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $query .= " AND ua.route_name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        // Compter le total
        $countQuery = str_replace('SELECT ua.*, r.name as route_name, s.name as sector_name, reg.name as region_name', 'SELECT COUNT(*)', $query);
        $total = $this->db->query($countQuery, $params)->fetchColumn();

        // Ajouter pagination
        $query .= " ORDER BY ua.ascent_date DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $result = $this->db->query($query, $params)->fetchAll();

        // Créer des objets UserAscent avec détails
        $ascents = [];
        foreach ($result as $row) {
            $ascent = new UserAscent($row);

            // Ajouter les détails des entités liées
            if ($row['route_name']) {
                $ascent->route_details = (object)['name' => $row['route_name']];
            }
            if ($row['sector_name']) {
                $ascent->sector_details = (object)['name' => $row['sector_name']];
            }
            if ($row['region_name']) {
                $ascent->region_details = (object)['name' => $row['region_name']];
            }

            $ascents[] = $ascent;
        }

        $lastPage = ceil($total / $perPage);

        return [
            'data' => $ascents,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Récupère les favoris avec filtres et pagination
     */
    private function getUserFavoritesWithFilters(int $userId, array $filters, int $page, int $perPage): array
    {
        $query = "SELECT ua.*, r.name as route_name, s.name as sector_name, reg.name as region_name 
                  FROM user_ascents ua 
                  LEFT JOIN climbing_routes r ON ua.route_id = r.id 
                  LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                  LEFT JOIN climbing_regions reg ON s.region_id = reg.id 
                  WHERE ua.user_id = ? AND ua.favorite = 1";

        $params = [$userId];

        // Appliquer les filtres
        if (!empty($filters['difficulty'])) {
            $query .= " AND ua.difficulty = ?";
            $params[] = $filters['difficulty'];
        }
        if (!empty($filters['style'])) {
            $query .= " AND ua.climbing_type = ?";
            $params[] = $filters['style'];
        }
        if (!empty($filters['region'])) {
            $query .= " AND reg.id = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['search'])) {
            $query .= " AND ua.route_name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        // Compter le total
        $countQuery = str_replace('SELECT ua.*, r.name as route_name, s.name as sector_name, reg.name as region_name', 'SELECT COUNT(*)', $query);
        $total = $this->db->query($countQuery, $params)->fetchColumn();

        // Ajouter pagination
        $query .= " ORDER BY ua.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $result = $this->db->query($query, $params)->fetchAll();

        // Créer des objets UserAscent avec détails
        $favorites = [];
        foreach ($result as $row) {
            $favorite = new UserAscent($row);

            // Ajouter les détails des entités liées
            if ($row['route_name']) {
                $favorite->route_details = (object)['name' => $row['route_name']];
            }
            if ($row['sector_name']) {
                $favorite->sector_details = (object)['name' => $row['sector_name']];
            }
            if ($row['region_name']) {
                $favorite->region_details = (object)['name' => $row['region_name']];
            }

            $favorites[] = $favorite;
        }

        $lastPage = ceil($total / $perPage);

        return [
            'data' => $favorites,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Récupère les difficultés des ascensions de l'utilisateur
     */
    private function getUserDifficulties(int $userId): array
    {
        $query = "SELECT DISTINCT difficulty FROM user_ascents WHERE user_id = ? ORDER BY difficulty";
        $result = $this->db->query($query, [$userId])->fetchAll(\PDO::FETCH_COLUMN);
        return $result;
    }

    /**
     * Récupère les difficultés des favoris de l'utilisateur
     */
    private function getUserFavoriteDifficulties(int $userId): array
    {
        $query = "SELECT DISTINCT difficulty FROM user_ascents WHERE user_id = ? AND favorite = 1 ORDER BY difficulty";
        $result = $this->db->query($query, [$userId])->fetchAll(\PDO::FETCH_COLUMN);
        return $result;
    }

    /**
     * Récupère les régions actives
     */
    private function getActiveRegions(): array
    {
        $query = "SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name";
        $result = $this->db->query($query)->fetchAll();

        $regions = [];
        foreach ($result as $row) {
            $regions[] = (object)$row;
        }

        return $regions;
    }
}
