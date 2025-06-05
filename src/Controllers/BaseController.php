<?php
// src/Controllers/BaseController.php

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
use TopoclimbCH\Core\Security\CsrfManager;

abstract class BaseController
{
    protected View $view;
    protected Session $session;
    protected ?Auth $auth = null;
    protected CsrfManager $csrfManager;

    public function __construct(View $view, Session $session, CsrfManager $csrfManager)
    {
        $this->view = $view;
        $this->session = $session;
        $this->csrfManager = $csrfManager;

        // Initialiser Auth depuis le conteneur si disponible
        try {
            if (Container::getInstance() && Container::getInstance()->has(Auth::class)) {
                $this->auth = Container::getInstance()->get(Auth::class);
            }
        } catch (\Exception $e) {
            error_log('Auth non initialisé dans BaseController: ' . $e->getMessage());
        }
    }

    /**
     * Render a view with data
     */
    protected function render(string $view, array $data = []): Response
    {
        $response = new Response();

        // Ajouter automatiquement des données globales
        $globalData = [
            'flashes' => $this->session->getFlashes(),
            'csrf_token' => $this->csrfManager->getToken(), // Token CSRF automatiquement disponible
            'app' => [
                'debug' => env('APP_DEBUG', false),
                'environment' => env('APP_ENV', 'production'),
                'version' => env('APP_VERSION', '1.0.0')
            ]
        ];

        // Ajouter l'utilisateur authentifié si disponible
        if ($this->auth && $this->auth->check()) {
            $globalData['auth_user'] = $this->auth->user();
        }

        // Fusionner avec les données fournies
        $data = array_merge($globalData, $data);

        // Assurez-vous que l'extension .twig est ajoutée
        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        $content = $this->view->render($view, $data);
        $response->setContent($content);

        // Configurer la mise en cache selon l'environnement
        if (env('APP_ENV') === 'production') {
            $response->setPublic();
            $response->setMaxAge(60);
            $response->setSharedMaxAge(120);
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store', true);
        }

        return $response;
    }

    /**
     * Redirect to a route
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Return a JSON response
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Create a CSRF token
     * @deprecated Utiliser $this->csrfManager->getToken() directement
     */
    protected function createCsrfToken(): string
    {
        return $this->csrfManager->getToken();
    }

    /**
     * Validate CSRF token - Version simplifiée utilisant CsrfManager
     */
    protected function validateCsrfToken($input = null): bool
    {
        $token = null;

        // Récupérer le token selon le type d'entrée
        if ($input instanceof Request) {
            $token = $this->csrfManager->getTokenFromRequest($input);
        } elseif (is_string($input)) {
            $token = $input;
        } else {
            // Fallback pour compatibilité
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        }

        if (empty($token)) {
            error_log('BaseController::validateCsrfToken - Token CSRF vide ou manquant');
            return false;
        }

        return $this->csrfManager->validateToken($token);
    }

    /**
     * Set a flash message
     */
    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    /**
     * Validate request data
     */
    protected function validate(array $data, array $rules): array
    {
        $validator = new Validator();

        if (!$validator->validate($data, $rules)) {
            $this->session->flash('errors', $validator->getErrors());
            $this->session->flash('old', $data);

            throw new ValidationException(
                "Validation failed: " . json_encode($validator->getErrors())
            );
        }

        return $data;
    }

    /**
     * Check if user has permission
     */
    protected function authorize(string $ability, $model = null): void
    {
        $container = Container::getInstance();
        if (!$container || !$container->has(Auth::class)) {
            throw new AuthorizationException("Service d'authentification non disponible");
        }

        $auth = $container->get(Auth::class);

        if (!$auth->check()) {
            throw new AuthorizationException("Authentification requise");
        }

        if (!$auth->can($ability, $model)) {
            throw new AuthorizationException("Action non autorisée: $ability");
        }
    }

    /**
     * Méthodes utilitaires pour CSRF
     */

    /**
     * Génère un nouveau token CSRF (utile après validation réussie)
     */
    protected function regenerateCsrfToken(): string
    {
        return $this->csrfManager->regenerateToken();
    }

    /**
     * Valide une requête complète (méthode + token)
     */
    protected function validateCsrfRequest(Request $request): bool
    {
        return $this->csrfManager->validateRequest($request);
    }

    /**
     * Retourne le HTML pour un champ caché CSRF
     */
    protected function csrfField(): string
    {
        return $this->csrfManager->getHiddenField();
    }
}
