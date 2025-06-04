<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\Auth;
use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;
use TopoclimbCH\Services\AuthService;
use TopoclimbCH\Services\ValidationService;

class AuthController extends BaseController
{
    /**
     * @var Auth
     * Modifier cette déclaration pour la rendre compatible avec BaseController
     */
    protected ?Auth $auth;

    /**
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * @var Database
     */
    protected Database $db;

    /**
     * @var ValidationService
     */
    protected ValidationService $validationService;

    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     * @param Database $db
     */
    public function __construct(View $view, Session $session, Database $db)
    {
        // Appeler le constructeur parent avec les paramètres requis
        parent::__construct($view, $session);

        // Initialiser les propriétés spécifiques à ce contrôleur
        $this->db = $db;
        $this->auth = Auth::getInstance($session, $db);
        $this->authService = new AuthService($this->auth, $session, $db);
        $this->validationService = new ValidationService();
    }

    /**
     * Affiche le formulaire de connexion
     *
     * @return Response
     */
    public function loginForm(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }

        // Utiliser le token existant si on est en train de préserver le token
        if ($this->session->has('_preserve_csrf')) {
            $csrfToken = $this->session->get('csrf_token');
            $this->session->remove('_preserve_csrf'); // Nettoyage
        } else {
            // Sinon, générer un nouveau token
            $csrfToken = $this->createCsrfToken();
        }

        return $this->render('auth/login', [
            'csrf_token' => $csrfToken
        ]);
    }

    public function login(Request $request): Response
    {
        $credentials = $request->request->all();

        // Ajouter des logs pour déboguer
        error_log('Tentative de connexion avec: ' . json_encode([
            'email' => $credentials['email'] ?? 'non fourni',
            'password_length' => isset($credentials['password']) ? strlen($credentials['password']) : 0
        ]));

        // Validation
        $rules = [
            'email' => 'required',
            'password' => 'required'
        ];

        $errors = $this->validationService->validate($credentials, $rules);

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'email' => $credentials['email'] ?? ''
            ]);
            return $this->redirect('/login');
        }

        // Remember me
        $remember = isset($credentials['remember']) && $credentials['remember'] === '1';

        // Tentative de connexion
        $loginSuccess = $this->auth->attempt($credentials['email'], $credentials['password'], $remember);
        error_log('Résultat de la tentative de connexion: ' . ($loginSuccess ? 'succès' : 'échec'));

        if ($loginSuccess) {
            // Récupération de l'URL intentionnelle
            $intendedUrl = $this->session->get('intended_url', '/');
            $this->session->remove('intended_url');

            // Conserver les données critiques en session
            $this->session->set('auth_user_id', $this->auth->id());
            $this->session->set('user_authenticated', true);

            // Message de succès
            $this->flash('success', 'Vous êtes maintenant connecté');

            // CRUCIAL: Persister la session
            $this->session->persist();

            // Log pour déboguer
            error_log('Authentification réussie. User ID en session: ' . $this->session->get('auth_user_id'));
            error_log('Redirection après connexion vers: ' . $intendedUrl);

            // Redirection avec envoi immédiat
            $response = Response::redirect($intendedUrl);
            $response->send();
            exit;
        }

        // Si échec de connexion
        $this->flash('error', 'Identifiants invalides');
        $this->session->flash('old', ['email' => $credentials['email'] ?? '']);
        return $this->redirect('/login');

        // Récupération de l'URL intentionnelle si disponible
        $intendedUrl = $this->session->get('intended_url', '/');
        $this->session->remove('intended_url');

        $this->flash('success', 'Vous êtes maintenant connecté');

        // Log de la redirection
        error_log('Redirection après connexion vers: ' . $intendedUrl);

        return $this->redirect($intendedUrl);
    }


    /**
     * Déconnexion de l'utilisateur avec une approche ultra-minimaliste
     * pour garantir le fonctionnement
     *
     * @return never
     */
    // Dans AuthController.php
    public function logout(): void
    {
        // IMPORTANT: Utiliser la méthode logout() de Auth qui gère tout correctement
        if ($this->auth) {
            $this->auth->logout();
        }

        // Nettoyer complètement la session
        $_SESSION = [];

        // Supprimer le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // CRUCIAL: Supprimer aussi le cookie remember_token
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);

        // Détruire la session
        session_destroy();

        // Message de confirmation et redirection
        session_start();
        $_SESSION['_flashes']['success'][] = 'Vous avez été déconnecté avec succès';
        session_write_close();

        // Redirection directe
        header('Location: /');
        exit;
    }

    public function registerForm(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }

        // Générer et passer le token CSRF à la vue
        $csrfToken = $this->createCsrfToken();

        return $this->render('auth/register', [
            'csrf_token' => $csrfToken
        ]);
    }

    public function register(Request $request): Response
    {
        $data = $request->request->all();

        // Validation
        $rules = [
            'nom' => 'required',
            'prenom' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required',
            'username' => 'required'
        ];

        $errors = $this->validationService->validate($data, $rules);

        // Vérification de la correspondance des mots de passe
        if (!$this->validationService->validateEquals($data, 'password', 'password_confirmation')) {
            $errors = $this->validationService->addError($errors, 'password_confirmation', 'Les mots de passe ne correspondent pas');
        }

        // Vérification des conflits d'email/username
        // Utiliser directement une requête SQL pour éviter les problèmes avec Model::where()
        $emailExists = $this->db->query("SELECT COUNT(*) as count FROM users WHERE mail = ?", [$data['email']])->fetch();
        if ($emailExists && $emailExists['count'] > 0) {
            $errors = $this->validationService->addError($errors, 'email', 'Cet email est déjà utilisé');
        }

        $usernameExists = $this->db->query("SELECT COUNT(*) as count FROM users WHERE username = ?", [$data['username']])->fetch();
        if ($usernameExists && $usernameExists['count'] > 0) {
            $errors = $this->validationService->addError($errors, 'username', 'Ce nom d\'utilisateur est déjà utilisé');
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', $data);
            return $this->redirect('/register');
        }

        // Création de l'utilisateur
        $user = new User();
        $user->nom = $data['nom'];
        $user->prenom = $data['prenom'];
        $user->mail = $data['email'];
        $user->username = $data['username'];
        $user->ville = $data['ville'] ?? '';
        $user->password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $user->autorisation = '3'; // Utilisateur standard
        $user->save();

        // Connexion automatique
        $this->auth->attempt($data['email'], $data['password']);

        $this->flash('success', 'Votre compte a été créé avec succès');
        return $this->redirect('/');
    }

    public function forgotPasswordForm(): Response
    {
        // Générer et passer le token CSRF à la vue
        $csrfToken = $this->createCsrfToken();

        return $this->render('auth/forgot-password', [
            'csrf_token' => $csrfToken
        ]);
    }

    public function forgotPassword(Request $request): Response
    {
        $email = $request->request->get('email');

        // Validation
        $rules = [
            'email' => 'required|email'
        ];

        $errors = $this->validationService->validate(['email' => $email], $rules);

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return $this->redirect('/forgot-password');
        }

        $this->authService->sendPasswordResetEmail($email);

        // Message identique que l'email existe ou non (sécurité contre l'énumération)
        $this->flash('success', 'Un email de réinitialisation a été envoyé si cette adresse est associée à un compte');
        return $this->redirect('/login');
    }

    public function resetPasswordForm(Request $request): Response
    {
        $token = $request->attributes->get('token');

        if (!$this->authService->validateResetToken($token)) {
            $this->flash('error', 'Ce lien de réinitialisation est invalide ou a expiré');
            return $this->redirect('/login');
        }

        // Générer et passer le token CSRF à la vue
        $csrfToken = $this->createCsrfToken();

        return $this->render('auth/reset-password', [
            'token' => $token,
            'csrf_token' => $csrfToken
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $data = $request->request->all();
        $token = $data['token'] ?? '';

        // Validation
        $rules = [
            'token' => 'required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required'
        ];

        $errors = $this->validationService->validate($data, $rules);

        // Vérification de la correspondance des mots de passe
        if (!$this->validationService->validateEquals($data, 'password', 'password_confirmation')) {
            $errors = $this->validationService->addError($errors, 'password_confirmation', 'Les mots de passe ne correspondent pas');
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return $this->redirect('/reset-password?token=' . $token);
        }

        if ($this->authService->resetPassword($token, $data['password'])) {
            $this->flash('success', 'Votre mot de passe a été réinitialisé avec succès');
            return $this->redirect('/login');
        }

        $this->flash('error', 'Une erreur est survenue lors de la réinitialisation');
        return $this->redirect('/reset-password?token=' . $token);
    }

    // SUPPRESSION DE LA MÉTHODE validateCsrfToken 
    // Elle sera héritée de BaseController

    /**
     * Méthode pour tester la connexion à la base de données
     */
    public function testDatabase(): Response
    {
        try {
            $result = $this->db->query("SELECT 1")->fetch();
            return Response::json(['success' => true, 'message' => 'Connexion à la BDD réussie']);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => 'Erreur de connexion: ' . $e->getMessage()]);
        }
    }
}
