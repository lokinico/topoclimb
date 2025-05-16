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
        // ================== IGNORER TEMPORAIREMENT LA VALIDATION CSRF ===================
        // $submittedToken = $request->request->get('csrf_token');
        // if (!$this->validateCsrfToken($submittedToken)) {
        //     $this->flash('error', 'Token CSRF invalide. Veuillez réessayer.');
        //     return $this->redirect('/login');
        // }
        // ================================================================================

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
     * Déconnexion de l'utilisateur avec gestion robuste des sessions
     *
     * @return Response
     */
    public function logout(): Response
    {
        error_log("AuthController::logout - Début du processus de déconnexion");

        try {
            // 1. Vérifier si l'utilisateur est connecté
            if (!$this->auth->check()) {
                error_log("AuthController::logout - Utilisateur non connecté");
                return $this->redirect('/');
            }

            // 2. Obtenir l'ID utilisateur pour le logging
            $userId = null;
            try {
                $userId = $this->auth->id();
                error_log("AuthController::logout - ID utilisateur: " . $userId);
            } catch (\Throwable $e) {
                error_log("AuthController::logout - Erreur récupération ID: " . $e->getMessage());
            }

            // 3. Déconnecter l'utilisateur via Auth
            $logoutSuccess = $this->auth->logout();
            error_log("AuthController::logout - Auth::logout(): " . ($logoutSuccess ? "succès" : "échec"));

            // 4. Stocker temporairement le message
            $successMessage = 'Vous avez été déconnecté avec succès';

            // 5. Détruire complètement la session actuelle
            $sessionDestroyed = $this->session->destroy();
            error_log("AuthController::logout - Session::destroy(): " . ($sessionDestroyed ? "succès" : "échec"));

            // 6. Démarrer une nouvelle session pour les messages flash
            $sessionRestarted = $this->session->restart();
            error_log("AuthController::logout - Session::restart(): " . ($sessionRestarted ? "succès" : "échec"));

            // 7. Définir le message flash dans la nouvelle session
            if ($sessionRestarted) {
                $this->session->flash('success', $successMessage);
                error_log("AuthController::logout - Message flash défini");
            } else {
                // Solution de secours si la session n'a pas pu être redémarrée
                $_SESSION['_flashes']['success'][] = $successMessage;
                error_log("AuthController::logout - Message flash défini manuellement");
            }

            // 8. Persister explicitement la session avant la redirection
            try {
                if ($sessionRestarted) {
                    $this->session->persist();
                    error_log("AuthController::logout - Session persistée");
                } else {
                    session_write_close();
                    error_log("AuthController::logout - Session fermée manuellement");
                }
            } catch (\Throwable $e) {
                error_log("AuthController::logout - Erreur persistence: " . $e->getMessage());
            }

            // 9. Créer et retourner la réponse
            error_log("AuthController::logout - Redirection vers la page d'accueil");
            return $this->redirect('/');
        } catch (\Throwable $e) {
            // Gérer toute exception non capturée
            error_log("AuthController::logout - Exception critique: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Solution de dernière chance
            try {
                // Nettoyer la session
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

                // Détruire et redémarrer
                session_destroy();
                session_start();

                // Message d'erreur en session
                $_SESSION['_flashes']['error'][] = 'Erreur lors de la déconnexion';
            } catch (\Throwable $innerException) {
                error_log("AuthController::logout - Échec solution de secours: " . $innerException->getMessage());
            }

            // Rediriger avec indicateur d'erreur
            return $this->redirect('/?logout_error=1');
        }
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

    /**
     * Validate CSRF token from string
     * 
     * @param string|null $token
     * @return bool
     */
    protected function validateCsrfToken(?string $token): bool
    {
        if (empty($token)) {
            error_log('Token CSRF vide');
            return false;
        }

        $storedToken = $this->session->get('csrf_token');
        if (empty($storedToken)) {
            error_log('Token CSRF non trouvé en session');
            return false;
        }

        // Vérifier si les tokens correspondent
        $result = hash_equals($storedToken, $token);
        error_log('Validation CSRF: ' . ($result ? 'succès' : 'échec') . ' (soumis: ' . substr($token, 0, 10) . '..., stocké: ' . substr($storedToken, 0, 10) . '...)');

        // Si la validation réussit, sauvegarder le token pour le protéger
        if ($result) {
            // Sauvegarder le token original
            $this->session->set('_original_csrf_token', $storedToken);

            // Installer un hook de fermeture qui sera exécuté à la fin du script
            register_shutdown_function(function () use ($storedToken) {
                if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== $storedToken) {
                    $_SESSION['csrf_token'] = $storedToken;
                    error_log("CSRF Token restauré en fin de script");
                }
            });
        }

        return $result;
    }

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
