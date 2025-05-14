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
use TopoclimbCH\Core\Validation\Validator;

class AuthController extends BaseController
{
    /**
     * @var Auth
     */
    protected Auth $auth;
    
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
    }
    
    public function loginForm(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }
        
        // Générer et passer le token CSRF à la vue
        $csrfToken = $this->createCsrfToken();
        
        return $this->render('auth/login', [
            'csrf_token' => $csrfToken
        ]);
    }
    
    public function login(Request $request): Response
    {
        $credentials = $request->request->all();
        
        // Validation
        $validator = new Validator($credentials);
        $validator->rule('required', ['email', 'password']);
        
        if (!$validator->validate()) {
            $this->session->flash('errors', $validator->errors());
            $this->session->flash('old', $credentials);
            return $this->redirect('/login');
        }
        
        // Remember me
        $remember = isset($credentials['remember']) && $credentials['remember'] === '1';
        
        // Tentative de connexion
        if (!$this->auth->attempt($credentials['email'], $credentials['password'], $remember)) {
            $this->flash('error', 'Identifiants invalides');
            $this->session->flash('old', ['email' => $credentials['email']]);
            return $this->redirect('/login');
        }
        
        // Récupération de l'URL intentionnelle si disponible
        $intendedUrl = $this->session->get('intended_url', '/');
        $this->session->remove('intended_url');
        
        $this->flash('success', 'Vous êtes maintenant connecté');
        return $this->redirect($intendedUrl);
    }
    
    public function logout(): Response
    {
        $this->auth->logout();
        $this->flash('success', 'Vous avez été déconnecté');
        return $this->redirect('/');
    }
    
    public function registerForm(): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }
        
        return $this->render('auth/register');
    }
    
    public function register(Request $request): Response
    {
        $data = $request->request->all();
        
        // Validation
        $validator = new Validator($data);
        $validator->rule('required', ['nom', 'prenom', 'email', 'password', 'password_confirmation', 'username']);
        $validator->rule('email', 'email');
        $validator->rule('equals', 'password_confirmation', 'password');
        $validator->rule('lengthMin', 'password', 8);
        
        // Vérification des conflits d'email/username
        if (User::where('mail', $data['email'])->first()) {
            $validator->addError('email', 'Cet email est déjà utilisé');
        }
        
        if (User::where('username', $data['username'])->first()) {
            $validator->addError('username', 'Ce nom d\'utilisateur est déjà utilisé');
        }
        
        if (!$validator->validate()) {
            $this->session->flash('errors', $validator->errors());
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
        return $this->render('auth/forgot-password');
    }
    
    public function forgotPassword(Request $request): Response
    {
        $email = $request->request->get('email');
        
        $validator = new Validator(['email' => $email]);
        $validator->rule('required', 'email');
        $validator->rule('email', 'email');
        
        if (!$validator->validate()) {
            $this->session->flash('errors', $validator->errors());
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
        
        return $this->render('auth/reset-password', [
            'token' => $token
        ]);
    }
    
    public function resetPassword(Request $request): Response
    {
        $data = $request->request->all();
        $token = $data['token'] ?? '';
        
        $validator = new Validator($data);
        $validator->rule('required', ['token', 'password', 'password_confirmation']);
        $validator->rule('equals', 'password_confirmation', 'password');
        $validator->rule('lengthMin', 'password', 8);
        
        if (!$validator->validate()) {
            $this->session->flash('errors', $validator->errors());
            return $this->redirect('/reset-password?token=' . $token);
        }
        
        if ($this->authService->resetPassword($token, $data['password'])) {
            $this->flash('success', 'Votre mot de passe a été réinitialisé avec succès');
            return $this->redirect('/login');
        }
        
        $this->flash('error', 'Une erreur est survenue lors de la réinitialisation');
        return $this->redirect('/reset-password?token=' . $token);
    }
}