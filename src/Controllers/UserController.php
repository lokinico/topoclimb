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
    protected UserService $userService;
    protected AscentService $ascentService;
    protected AuthService $authService;
    protected Database $db;

    public function __construct(View $view, Session $session, CsrfManager $csrfManager)
    {
        parent::__construct($view, $session, $csrfManager);

        // Créer les services à la demande plutôt que par injection
        $this->db = Database::getInstance();
        $this->userService = new UserService($this->db);
        $this->ascentService = new AscentService($this->db);
        $this->authService = new AuthService($this->auth, $session, $this->db);
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
     * Affiche le formulaire d'édition du profil
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
            $existingUser = $this->userService->getUserByEmail($data['mail']);
            if ($existingUser && $existingUser->id !== $userId) {
                $this->flash('error', 'Cet email est déjà utilisé par un autre utilisateur');
                return $this->redirect('/profile/edit');
            }
        }

        // Vérifie si le nom d'utilisateur est déjà utilisé par un autre utilisateur
        if ($data['username'] !== $user->username) {
            $existingUser = $this->userService->getUserByUsername($data['username']);
            if ($existingUser && $existingUser->id !== $userId) {
                $this->flash('error', 'Ce nom d\'utilisateur est déjà utilisé par un autre utilisateur');
                return $this->redirect('/profile/edit');
            }
        }

        try {
            // Met à jour l'utilisateur
            $this->userService->updateProfile($user, $data);

            $this->flash('success', 'Profil mis à jour avec succès');
            return $this->redirect('/profile');
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur lors de la mise à jour du profil: ' . $e->getMessage());
            return $this->redirect('/profile/edit');
        }
    }
}
