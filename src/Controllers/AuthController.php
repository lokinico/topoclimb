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
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;

class AuthController extends BaseController
{
    /**
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * @var ValidationService
     */
    protected ValidationService $validationService;

    /**
     * Constructor - CORRIGÉ pour éviter les conflits de type
     *
     * @param View $view
     * @param Session $session
     * @param CsrfManager $csrfManager
     * @param Database $database
     */
    public function __construct(View $view, Session $session, CsrfManager $csrfManager, Database $database)
    {
        parent::__construct($view, $session, $csrfManager);

        // Utiliser la propriété héritée de BaseController
        $this->db = $database;

        // Initialiser Auth si pas déjà fait
        if (!$this->auth) {
            $this->auth = Auth::getInstance($session, $this->db);
        }

        // Initialiser les services
        $this->authService = new AuthService($this->auth, $session, $this->db);
        $this->validationService = new ValidationService();
    }

    /**
     * Affiche le formulaire de connexion - VERSION SÉCURISÉE
     */
    public function loginForm(): Response
    {
        try {
            if ($this->auth->check()) {
                return $this->redirect('/');
            }

            return $this->render('auth/login', [
                'title' => 'Connexion',
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire de connexion');
            return $this->render('auth/login', [
                'title' => 'Connexion',
                'csrf_token' => $this->createCsrfToken(),
                'error' => 'Une erreur est survenue'
            ]);
        }
    }
    /**
     * Traite la connexion - VERSION SÉCURISÉE
     */
    public function login(Request $request): Response
    {
        try {
            $this->requireCsrfToken($request);
            $this->checkRateLimit($request->getClientIp(), 'login', 5, 300);

            $credentials = $this->validateInput($request->request->all(), [
                'email' => 'required|email|max:255',
                'password' => 'required|min:1|max:255'
            ]);

            $remember = $request->request->get('remember') === '1';

            if ($this->authService->attempt($credentials['email'], $credentials['password'], $remember)) {
                $this->resetRateLimit($request->getClientIp(), 'login');
                $intendedUrl = $this->session->get('intended_url', '/');
                $this->session->remove('intended_url');

                $this->logAction('user_login_success', [
                    'user_id' => $this->auth->id(),
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]);

                $this->flash('success', 'Connexion réussie');
                return $this->redirect($intendedUrl);
            }

            $this->logAction('user_login_failed', [
                'email' => $credentials['email'],
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            $this->flash('error', 'Identifiants invalides');
            return $this->redirect('/login');
        } catch (ValidationException $e) {
            $this->flash('error', 'Données de connexion invalides');
            return $this->redirect('/login');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la connexion');
            $this->flash('error', 'Une erreur est survenue lors de la connexion');
            return $this->redirect('/login');
        }
    }

    /**
     * Déconnexion - VERSION SÉCURISÉE
     */
    public function logout(Request $request): Response
    {
        try {
            $userId = $this->auth->id();
            $this->auth->logout();

            $this->session->remove('auth_user_id');
            $this->session->remove('is_authenticated');

            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }

            $this->logAction('user_logout', [
                'user_id' => $userId,
                'ip' => $request->getClientIp()
            ]);

            $this->flash('success', 'Vous avez été déconnecté avec succès');
            return $this->redirect('/');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la déconnexion');
            $this->flash('error', 'Erreur lors de la déconnexion');
            return $this->redirect('/');
        }
    }

    /**
     * Formulaire d'inscription - VERSION SÉCURISÉE
     */
    public function registerForm(): Response
    {
        try {
            if ($this->auth && $this->auth->check()) {
                return $this->redirect('/');
            }

            return $this->render('auth/register', [
                'title' => 'Inscription',
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire d\'inscription');
            return $this->redirect('/');
        }
    }

    /**
     * Traite l'inscription - VERSION SÉCURISÉE
     */
    public function register(Request $request): Response
    {
        try {
            // Validation CSRF
            $this->requireCsrfToken($request);

            // Rate limiting - 3 inscriptions par heure par IP
            $this->checkRateLimit($request->getClientIp(), 'register', 3, 3600);

            // Validation des données
            $data = $this->validateInput($request->request->all(), [
                'nom' => 'required|string|min:2|max:100',
                'prenom' => 'required|string|min:2|max:100',
                'email' => 'required|email|max:255',
                'password' => 'required|min:12|max:255',
                'password_confirmation' => 'required',
                'username' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
                'ville' => 'nullable|string|max:100'
            ]);

            // Vérification de la correspondance des mots de passe
            if ($data['password'] !== $data['password_confirmation']) {
                throw new ValidationException('Les mots de passe ne correspondent pas');
            }

            // Validation avancée du mot de passe
            if (!$this->validatePasswordStrength($data['password'])) {
                throw new ValidationException('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial');
            }

            $result = $this->executeInTransaction(function () use ($data, $request) {
                // Vérification de l'unicité email/username
                $emailExists = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE mail = ?", [$data['email']]);
                if ($emailExists && $emailExists['count'] > 0) {
                    throw new ValidationException('Cet email est déjà utilisé');
                }

                $usernameExists = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE username = ?", [$data['username']]);
                if ($usernameExists && $usernameExists['count'] > 0) {
                    throw new ValidationException('Ce nom d\'utilisateur est déjà utilisé');
                }

                // Création de l'utilisateur
                $user = new User();
                $user->nom = $data['nom'];
                $user->prenom = $data['prenom'];
                $user->mail = $data['email'];
                $user->username = $data['username'];
                $user->ville = $data['ville'] ?? '';
                $user->password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $user->autorisation = '4'; // Nouveau membre (en attente)
                $user->save();

                // Logging
                $this->logAction('user_registered', [
                    'user_id' => $user->id,
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'ip' => $request->getClientIp()
                ]);

                return $user;
            });

            // Connexion automatique après inscription
            $this->auth->attempt($data['email'], $data['password']);

            $this->flash('success', 'Inscription réussie ! Votre compte est en attente de validation.');
            return $this->redirect('/pending');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/register');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de l\'inscription');
            $this->flash('error', 'Une erreur est survenue lors de l\'inscription');
            return $this->redirect('/register');
        }
    }

    /**
     * Formulaire mot de passe oublié - VERSION SÉCURISÉE
     */
    public function forgotPasswordForm(): Response
    {
        try {
            return $this->render('auth/forgot-password', [
                'title' => 'Mot de passe oublié',
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire');
            return $this->redirect('/login');
        }
    }

    /**
     * Traite la demande de réinitialisation - VERSION SÉCURISÉE
     */
    public function forgotPassword(Request $request): Response
    {
        try {
            // Validation CSRF
            $this->requireCsrfToken($request);

            // Rate limiting - 3 demandes par heure par IP
            $this->checkRateLimit($request->getClientIp(), 'forgot_password', 3, 3600);

            // Validation
            $data = $this->validateInput($request->request->all(), [
                'email' => 'required|email|max:255'
            ]);

            $this->authService->sendPasswordResetEmail($data['email']);

            // Logging (sans révéler si l'email existe)
            $this->logAction('password_reset_requested', [
                'email' => $data['email'],
                'ip' => $request->getClientIp()
            ]);

            // Message identique que l'email existe ou non (sécurité)
            $this->flash('success', 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation.');
            return $this->redirect('/login');
        } catch (ValidationException $e) {
            $this->flash('error', 'Adresse email invalide');
            return $this->redirect('/forgot-password');
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la demande de réinitialisation');
            $this->flash('error', 'Une erreur est survenue');
            return $this->redirect('/forgot-password');
        }
    }

    /**
     * Formulaire de réinitialisation avec token - VERSION SÉCURISÉE
     */
    public function resetPasswordForm(Request $request): Response
    {
        try {
            $token = $request->attributes->get('token');

            if (!$token || !$this->authService->validateResetToken($token)) {
                $this->flash('error', 'Lien de réinitialisation invalide ou expiré');
                return $this->redirect('/login');
            }

            return $this->render('auth/reset-password', [
                'title' => 'Réinitialiser le mot de passe',
                'token' => $token,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement du formulaire de réinitialisation');
            return $this->redirect('/login');
        }
    }

    /**
     * Traite la réinitialisation - VERSION SÉCURISÉE
     */
    public function resetPassword(Request $request): Response
    {
        try {
            // Validation CSRF
            $this->requireCsrfToken($request);

            // Validation des données
            $data = $this->validateInput($request->request->all(), [
                'token' => 'required|string',
                'password' => 'required|min:12|max:255',
                'password_confirmation' => 'required'
            ]);

            // Vérification correspondance mots de passe
            if ($data['password'] !== $data['password_confirmation']) {
                throw new ValidationException('Les mots de passe ne correspondent pas');
            }

            // Validation force du mot de passe
            if (!$this->validatePasswordStrength($data['password'])) {
                throw new ValidationException('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial');
            }

            if ($this->authService->resetPassword($data['token'], $data['password'])) {
                $this->logAction('password_reset_completed', [
                    'ip' => $request->getClientIp()
                ]);

                $this->flash('success', 'Mot de passe réinitialisé avec succès');
                return $this->redirect('/login');
            }

            throw new ValidationException('Token de réinitialisation invalide');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/reset-password?token=' . ($data['token'] ?? ''));
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors de la réinitialisation');
            $this->flash('error', 'Une erreur est survenue');
            return $this->redirect('/login');
        }
    }

    // ===== MÉTHODES PRIVÉES SÉCURISÉES =====

    /**
     * Vérifie le rate limiting
     */
    private function checkRateLimit(string $identifier, string $action, int $maxAttempts, int $timeWindow): void
    {
        $key = "rate_limit_{$action}_{$identifier}";
        $attempts = $this->session->get($key, ['count' => 0, 'timestamp' => time()]);

        // Reset si la fenêtre de temps est dépassée
        if (time() - $attempts['timestamp'] > $timeWindow) {
            $attempts = ['count' => 0, 'timestamp' => time()];
        }

        if ($attempts['count'] >= $maxAttempts) {
            $remainingTime = $timeWindow - (time() - $attempts['timestamp']);
            throw new ValidationException("Trop de tentatives. Réessayez dans " . ceil($remainingTime / 60) . " minutes.");
        }

        // Incrémenter le compteur
        $attempts['count']++;
        $this->session->set($key, $attempts);
    }

    /**
     * Remet à zéro le rate limit
     */
    private function resetRateLimit(string $identifier, string $action): void
    {
        $key = "rate_limit_{$action}_{$identifier}";
        $this->session->remove($key);
    }

    /**
     * Valide la force du mot de passe
     */
    private function validatePasswordStrength(string $password): bool
    {
        // Au moins 12 caractères, une majuscule, une minuscule, un chiffre, un caractère spécial
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/', $password) === 1;
    }

    /**
     * Test de connexion à la base de données (pour debugging)
     */
    public function testDatabase(): Response
    {
        try {
            if (!$this->db) {
                return $this->json(['success' => false, 'message' => 'Database non initialisée']);
            }

            $result = $this->db->fetchOne("SELECT 1 as test");
            return $this->json(['success' => true, 'message' => 'Connexion à la BDD réussie', 'result' => $result]);
        } catch (\Exception $e) {
            $this->handleError($e, 'Test de connexion BDD');
            return $this->json(['success' => false, 'message' => 'Erreur de connexion à la BDD']);
        }
    }
}
