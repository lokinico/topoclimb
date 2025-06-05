<?php
// src/Controllers/UserController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;
use TopoclimbCH\Services\UserService;
use TopoclimbCH\Services\AscentService;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Core\Security\CsrfManager;

class UserController extends BaseController
{
    /**
     * @var UserService
     */
    protected UserService $userService;

    /**
     * @var AscentService
     */
    protected AscentService $ascentService;

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
     * @param UserService $userService
     * @param AscentService $ascentService
     * @param AuthService $authService
     * @param Database $db
     */
    public function __construct(
        View $view,
        Session $session,
        UserService $userService,
        AscentService $ascentService,
        AuthService $authService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->userService = $userService;
        $this->ascentService = $ascentService;
        $this->authService = $authService;
        $this->db = $db;
    }

    /**
     * Affiche le profil de l'utilisateur connecté
     *
     * @param Request $request
     * @return Response
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

        // Récupère les voies favorites
        $favoriteRoutes = $this->userService->getUserFavoriteRoutes($userId);

        return $this->render('users/profile', [
            'user' => $user,
            'ascentStats' => $ascentStats,
            'recentAscents' => $recentAscents,
            'favoriteRoutes' => $favoriteRoutes,
            'title' => 'Mon profil'
        ]);
    }

    /**
     * Affiche le formulaire d'édition du profil
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

        $user = $this->authService->user();

        return $this->render('users/edit', [
            'user' => $user,
            'title' => 'Modifier mon profil',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Met à jour le profil utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour modifier votre profil');
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/profile/edit');
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

        // Vérifie si l'email est déjà utilisé par un autre utilisateur
        if ($data['mail'] !== $user->mail) {
            $existingUser = User::findByEmail($data['mail']);
            if ($existingUser && $existingUser->id !== $userId) {
                $this->flash('error', 'Cet email est déjà utilisé par un autre utilisateur');
                return $this->redirect('/profile/edit');
            }
        }

        // Vérifie si le nom d'utilisateur est déjà utilisé par un autre utilisateur
        if ($data['username'] !== $user->username) {
            $existingUser = User::findByUsername($data['username']);
            if ($existingUser && $existingUser->id !== $userId) {
                $this->flash('error', 'Ce nom d\'utilisateur est déjà utilisé par un autre utilisateur');
                return $this->redirect('/profile/edit');
            }
        }

        try {
            // Met à jour l'utilisateur
            $this->userService->updateUser($userId, $data);

            $this->flash('success', 'Profil mis à jour avec succès');
            return $this->redirect('/profile');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du profil: ' . $e->getMessage());
            return $this->redirect('/profile/edit');
        }
    }

    /**
     * Affiche le formulaire de changement de mot de passe
     *
     * @param Request $request
     * @return Response
     */
    public function changePasswordForm(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        return $this->render('users/change-password', [
            'title' => 'Changer de mot de passe',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Change le mot de passe de l'utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function changePassword(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour changer votre mot de passe');
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/profile/change-password');
        }

        $userId = $this->authService->id();
        $user = $this->authService->user();

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'current_password' => 'required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required'
        ]);

        // Vérifie que le mot de passe actuel est correct
        if (!$user->checkPassword($data['current_password'])) {
            $this->flash('error', 'Le mot de passe actuel est incorrect');
            return $this->redirect('/profile/change-password');
        }

        // Vérifie que les nouveaux mots de passe correspondent
        if ($data['password'] !== $data['password_confirmation']) {
            $this->flash('error', 'Les nouveaux mots de passe ne correspondent pas');
            return $this->redirect('/profile/change-password');
        }

        try {
            // Met à jour le mot de passe
            $this->userService->changePassword($userId, $data['password']);

            $this->flash('success', 'Mot de passe modifié avec succès');
            return $this->redirect('/profile');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors du changement de mot de passe: ' . $e->getMessage());
            return $this->redirect('/profile/change-password');
        }
    }

    /**
     * Affiche la vue des préférences utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function preferences(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->session->set('intended_url', $request->getPathInfo());
            $this->flash('error', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirect('/login');
        }

        $userId = $this->authService->id();

        // Récupère les préférences utilisateur
        $preferences = $this->userService->getUserPreferences($userId);

        // Récupère les systèmes de difficulté disponibles
        $difficultySystems = $this->db->fetchAll("SELECT id, name FROM climbing_difficulty_systems ORDER BY name ASC");

        return $this->render('users/preferences', [
            'preferences' => $preferences,
            'difficultySystems' => $difficultySystems,
            'title' => 'Mes préférences',
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Enregistre les préférences utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function savePreferences(Request $request): Response
    {
        // Vérifie que l'utilisateur est connecté
        if (!$this->authService->check()) {
            $this->flash('error', 'Vous devez être connecté pour modifier vos préférences');
            return $this->redirect('/login');
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/profile/preferences');
        }

        $userId = $this->authService->id();

        // Récupère les données
        $data = $request->request->all();

        try {
            // Enregistre les préférences
            $this->userService->saveUserPreferences($userId, $data);

            $this->flash('success', 'Préférences enregistrées avec succès');
            return $this->redirect('/profile/preferences');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de l\'enregistrement des préférences: ' . $e->getMessage());
            return $this->redirect('/profile/preferences');
        }
    }

    /**
     * Administration: Affiche la liste des utilisateurs
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-users');

        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 20);
        $search = $request->query->get('search', '');

        // Récupère les utilisateurs
        $users = $this->userService->getPaginatedUsers($search, $page, $perPage);

        return $this->render('admin/users/index', [
            'users' => $users,
            'search' => $search,
            'title' => 'Gestion des utilisateurs'
        ]);
    }

    /**
     * Administration: Affiche le formulaire d'édition d'un utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function adminEdit(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-users');

        $id = (int) $request->attributes->get('id');

        $user = $this->userService->getUser($id);

        if (!$user) {
            $this->flash('error', 'Utilisateur non trouvé');
            return $this->redirect('/admin/users');
        }

        return $this->render('admin/users/edit', [
            'user' => $user,
            'roles' => [
                '1' => 'Administrateur',
                '2' => 'Modérateur',
                '3' => 'Utilisateur'
            ],
            'title' => 'Modifier l\'utilisateur ' . $user->getFullNameAttribute(),
            'csrf_token' => $this->createCsrfToken()
        ]);
    }

    /**
     * Administration: Met à jour un utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function adminUpdate(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-users');

        $id = (int) $request->attributes->get('id');

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $user = $this->userService->getUser($id);

        if (!$user) {
            $this->flash('error', 'Utilisateur non trouvé');
            return $this->redirect('/admin/users');
        }

        // Valide les données
        $data = $this->validate($request->request->all(), [
            'nom' => 'required|max:255',
            'prenom' => 'required|max:255',
            'ville' => 'nullable|max:255',
            'mail' => 'required|email|max:255',
            'username' => 'required|max:100',
            'autorisation' => 'required|in:1,2,3'
        ]);

        // Vérifie si l'email est déjà utilisé par un autre utilisateur
        if ($data['mail'] !== $user->mail) {
            $existingUser = User::findByEmail($data['mail']);
            if ($existingUser && $existingUser->id !== $id) {
                $this->flash('error', 'Cet email est déjà utilisé par un autre utilisateur');
                return $this->redirect('/admin/users/' . $id . '/edit');
            }
        }

        // Vérifie si le nom d'utilisateur est déjà utilisé par un autre utilisateur
        if ($data['username'] !== $user->username) {
            $existingUser = User::findByUsername($data['username']);
            if ($existingUser && $existingUser->id !== $id) {
                $this->flash('error', 'Ce nom d\'utilisateur est déjà utilisé par un autre utilisateur');
                return $this->redirect('/admin/users/' . $id . '/edit');
            }
        }

        // Vérifie si le nouveau mot de passe est fourni
        if (!empty($request->request->get('password'))) {
            $password = $request->request->get('password');
            if (strlen($password) < 8) {
                $this->flash('error', 'Le mot de passe doit contenir au moins 8 caractères');
                return $this->redirect('/admin/users/' . $id . '/edit');
            }
            $data['password'] = $password;
        }

        try {
            // Met à jour l'utilisateur
            $this->userService->updateUser($id, $data);

            $this->flash('success', 'Utilisateur mis à jour avec succès');
            return $this->redirect('/admin/users');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage());
            return $this->redirect('/admin/users/' . $id . '/edit');
        }
    }

    /**
     * Administration: Supprime un utilisateur
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        // Vérifie les permissions
        $this->authorize('manage-users');

        $id = (int) $request->attributes->get('id');

        $user = $this->userService->getUser($id);

        if (!$user) {
            $this->flash('error', 'Utilisateur non trouvé');
            return $this->redirect('/admin/users');
        }

        // Vérifie qu'on ne supprime pas l'utilisateur connecté
        if ($id === $this->authService->id()) {
            $this->flash('error', 'Vous ne pouvez pas supprimer votre propre compte');
            return $this->redirect('/admin/users');
        }

        // Vérifie s'il s'agit d'une demande de confirmation
        if ($request->getMethod() !== 'POST') {
            return $this->render('admin/users/delete', [
                'user' => $user,
                'title' => 'Supprimer l\'utilisateur ' . $user->getFullNameAttribute(),
                'csrf_token' => $this->createCsrfToken()
            ]);
        }

        // Vérifie le token CSRF
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return $this->redirect('/admin/users/' . $id . '/delete');
        }

        try {
            // Supprime l'utilisateur
            $this->userService->deleteUser($user);

            $this->flash('success', 'Utilisateur supprimé avec succès');
            return $this->redirect('/admin/users');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage());
            return $this->redirect('/admin/users');
        }
    }
}
