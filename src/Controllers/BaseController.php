<?php
// src/Controllers/BaseController.php - VERSION SÉCURISÉE

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Container;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Validation\Validator;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;
use TopoclimbCH\Exceptions\SecurityException;

abstract class BaseController
{
    protected View $view;
    protected Session $session;
    protected ?Auth $auth = null;
    protected CsrfManager $csrfManager;
    protected ?Database $db = null;

    public function __construct(
        View $view, 
        Session $session, 
        CsrfManager $csrfManager,
        ?Database $db = null,
        ?Auth $auth = null
    ) {
        $this->view = $view;
        $this->session = $session;
        $this->csrfManager = $csrfManager;
        $this->db = $db;
        $this->auth = $auth;

        // Fallback to Container if dependencies not injected (for backward compatibility)
        if (!$this->db || !$this->auth) {
            try {
                if (!$this->db && Container::getInstance() && Container::getInstance()->has(Database::class)) {
                    $this->db = Container::getInstance()->get(Database::class);
                }
                if (!$this->auth && Container::getInstance() && Container::getInstance()->has(Auth::class)) {
                    $this->auth = Container::getInstance()->get(Auth::class);
                }
            } catch (\Exception $e) {
                error_log('Erreur initialisation BaseController: ' . $e->getMessage());
            }
        }
    }

    /**
     * Gestion sécurisée des erreurs
     */
    protected function handleError(\Exception $e, string $context = ''): void
    {
        $errorId = uniqid('err_');
        $message = $context ? "{$context}: {$e->getMessage()}" : $e->getMessage();

        error_log("[$errorId] $message");
        error_log("[$errorId] Stack trace: " . $e->getTraceAsString());

        // En développement, afficher l'erreur
        if (env('APP_DEBUG', false)) {
            throw $e;
        }

        // En production, message générique
        $this->flash('error', 'Une erreur est survenue. Référence: ' . $errorId);
    }

    /**
     * Validation sécurisée des entrées utilisateur
     */
    protected function validateInput(array $data, array $rules): array
    {
        $validator = new Validator();

        // Nettoyer les données d'entrée
        $cleanData = $this->sanitizeInput($data);

        if (!$validator->validate($cleanData, $rules)) {
            $this->session->flash('errors', $validator->getErrors());
            $this->session->flash('old', $cleanData);

            throw new ValidationException(
                "Validation échouée: " . json_encode($validator->getErrors())
            );
        }

        return $cleanData;
    }

    /**
     * Nettoyage des données d'entrée
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Nettoyer les chaînes
                $value = trim($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

                // Limiter la longueur pour éviter les attaques DoS
                if (strlen($value) > 65535) {
                    $value = substr($value, 0, 65535);
                }
            } elseif (is_array($value)) {
                // Récursif pour les tableaux
                $value = $this->sanitizeInput($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Vérification sécurisée des permissions
     */
    protected function requireAuth(string $message = 'Authentification requise'): void
    {
        if (!$this->auth || !$this->auth->check()) {
            $this->session->set('intended_url', $_SERVER['REQUEST_URI'] ?? '/');
            $this->flash('error', $message);
            throw new AuthorizationException($message);
        }
    }

    /**
     * Vérification des rôles utilisateur
     */
    protected function requireRole(array $allowedRoles, string $message = 'Permissions insuffisantes'): void
    {
        $this->requireAuth();

        $userRole = (int)($this->auth->user()->autorisation ?? 5);

        if (!in_array($userRole, $allowedRoles)) {
            $this->flash('error', $message);
            throw new AuthorizationException($message);
        }
    }

    /**
     * Validation CSRF sécurisée
     */
    protected function requireCsrfToken(Request $request): void
    {
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            throw new SecurityException('Token CSRF invalide');
        }
    }

    /**
     * Gestion sécurisée des transactions
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        if (!$this->db) {
            throw new \RuntimeException('Base de données non disponible');
        }

        try {
            if (!$this->db->beginTransaction()) {
                throw new \RuntimeException('Impossible de démarrer la transaction');
            }

            $result = $callback();

            if (!$this->db->commit()) {
                throw new \RuntimeException('Échec du commit de la transaction');
            }

            return $result;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Validation sécurisée des IDs
     */
    protected function validateId(mixed $id, string $context = 'ID'): int
    {
        if (!$id || !is_numeric($id) || (int)$id <= 0) {
            throw new ValidationException("$context invalide");
        }

        return (int)$id;
    }

    /**
     * Vérification d'existence d'entité
     */
    protected function requireEntity(mixed $entity, string $message = 'Entité non trouvée'): mixed
    {
        if (!$entity) {
            $this->flash('error', $message);
            throw new ValidationException($message);
        }
        return $entity;
    }

    /**
     * Rendu sécurisé des vues
     */
    protected function render(string $view, array $data = []): Response
    {
        try {
            $response = new Response();

            // Données globales sécurisées
            $globalData = [
                'flashes' => $this->session->getFlashes(),
                'csrf_token' => $this->csrfManager->getToken(),
                'app' => [
                    'debug' => env('APP_DEBUG', false),
                    'environment' => env('APP_ENV', 'production'),
                    'version' => env('APP_VERSION', '1.0.0')
                ]
            ];

            // Ajouter l'utilisateur authentifié de manière sécurisée
            if ($this->auth && $this->auth->check()) {
                $user = $this->auth->user();
                $globalData['auth_user'] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'autorisation' => $user->autorisation
                ];
            }

            // Nettoyer les données avant le rendu
            $cleanData = $this->sanitizeViewData(array_merge($globalData, $data));

            // Assurer l'extension .twig
            if (!str_ends_with($view, '.twig')) {
                $view .= '.twig';
            }

            $content = $this->view->render($view, $cleanData);
            $response->setContent($content);

            // Headers de sécurité
            $this->setSecurityHeaders($response);

            return $response;
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur de rendu de vue');
            throw $e;
        }
    }

    /**
     * Nettoyage des données pour les vues
     */
    protected function sanitizeViewData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Pas d'échappement ici car Twig le fait automatiquement
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeViewData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Headers de sécurité
     */
    protected function setSecurityHeaders(Response $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (env('APP_ENV') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Redirection sécurisée
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        // Valider l'URL pour éviter les redirections malveillantes
        if (!$this->isValidRedirectUrl($url)) {
            $url = '/';
        }

        return Response::redirect($url, $status);
    }

    /**
     * Validation des URLs de redirection
     */
    protected function isValidRedirectUrl(string $url): bool
    {
        // URLs relatives acceptées
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // URLs absolues uniquement pour notre domaine
        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        $allowedHost = parse_url(env('APP_URL', ''), PHP_URL_HOST);
        return isset($parsed['host']) && $parsed['host'] === $allowedHost;
    }

    /**
     * Réponse JSON sécurisée
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        // S'assurer que les données sont sérialisables en JSON
        try {
            json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $data = ['error' => 'Données non sérialisables'];
            $status = 500;
        }

        $response = Response::json($data, $status);
        $this->setSecurityHeaders($response);

        return $response;
    }

    /**
     * Validation CSRF améliorée
     */
    protected function validateCsrfToken($input = null): bool
    {
        try {
            $token = null;

            if ($input instanceof Request) {
                $token = $this->csrfManager->getTokenFromRequest($input);
            } elseif (is_string($input)) {
                $token = $input;
            } else {
                $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
            }

            if (empty($token)) {
                error_log('BaseController::validateCsrfToken - Token CSRF manquant');
                return false;
            }

            return $this->csrfManager->validateToken($token);
        } catch (\Exception $e) {
            error_log('BaseController::validateCsrfToken - Erreur: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Message flash sécurisé
     */
    protected function flash(string $type, string $message): void
    {
        // Limiter les types de messages autorisés
        $allowedTypes = ['success', 'error', 'warning', 'info'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'info';
        }

        // Limiter la longueur du message
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000) . '...';
        }

        $this->session->flash($type, $message);
    }

    /**
     * Token CSRF pour les vues
     */
    protected function createCsrfToken(): string
    {
        return $this->csrfManager->getToken();
    }

    /**
     * Validation stricte des permissions
     */
    protected function authorize(string $ability, $model = null): void
    {
        if (!$this->auth || !$this->auth->check()) {
            throw new AuthorizationException("Authentification requise");
        }

        // Ajouter ici la logique de permissions spécifique à votre application
        // En fonction du rôle utilisateur et de l'action demandée

        $userRole = (int)($this->auth->user()->autorisation ?? 5);

        // Exemple de logique de permissions
        $permissions = [
            'manage-users' => [0, 1], // Admin et modérateur
            'manage-content' => [0, 1, 2], // Admin, modérateur, éditeur
            'create-content' => [0, 1, 2, 3], // Tous sauf bannés et nouveaux
            'view-content' => [0, 1, 2, 3] // Tous les utilisateurs validés
        ];

        if (!isset($permissions[$ability]) || !in_array($userRole, $permissions[$ability])) {
            throw new AuthorizationException("Action non autorisée: $ability");
        }
    }

    /**
     * Logging sécurisé des actions
     */
    protected function logAction(string $action, array $context = []): void
    {
        $logData = [
            'action' => $action,
            'user_id' => $this->auth?->id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        error_log('ACTION_LOG: ' . json_encode($logData));
    }
}
