<?php
// src/Controllers/BaseController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
// Changer l'import pour utiliser notre propre classe Response
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Container;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Validation\Validator;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;
use TopoclimbCH\Services\AuthService;

abstract class BaseController
{
    /**
     * @var View
     */
    protected View $view;

    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @var Auth|null
     */
    protected ?Auth $auth = null;

    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     */
    public function __construct(View $view, Session $session)
    {
        $this->view = $view;
        $this->session = $session;

        // Ne pas initialiser Auth directement, le récupérer du conteneur si disponible
        try {
            if (Container::getInstance() && Container::getInstance()->has(Auth::class)) {
                $this->auth = Container::getInstance()->get(Auth::class);
            }
        } catch (\Exception $e) {
            // Auth ne sera pas disponible mais ce n'est pas critique
            error_log('Auth non initialisé dans BaseController: ' . $e->getMessage());
        }
    }

    /**
     * Render a view with data
     *
     * @param string $view
     * @param array $data
     * @return Response
     */
    protected function render(string $view, array $data = []): Response
    {
        // Utiliser notre classe Response
        $response = new Response();

        // Ajouter automatiquement des données globales
        $globalData = [
            'flashes' => $this->session->getFlashes(),
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

        // Assurez-vous que l'extension .twig est ajoutée si elle n'est pas présente
        if (!str_ends_with($view, '.twig')) {
            $view .= '.twig';
        }

        $content = $this->view->render($view, $data);
        $response->setContent($content);

        // Configurer la mise en cache selon l'environnement
        if (env('APP_ENV') === 'production') {
            $response->setPublic();
            $response->setMaxAge(60); // 1 minute
            $response->setSharedMaxAge(120); // 2 minutes
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store', true);
        }

        return $response;
    }

    /**
     * Redirect to a route
     *
     * @param string $url
     * @param int $status
     * @return Response
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        // Utiliser notre classe Response statique redirect
        return Response::redirect($url, $status);
    }

    /**
     * Return a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @return Response
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        // Utiliser notre classe Response statique json
        return Response::json($data, $status);
    }

    /**
     * Create a CSRF token
     *
     * @return string
     */
    protected function createCsrfToken(): string
    {
        return $this->session->setCsrfToken();
    }

    /**
     * Validate CSRF token - Version unifiée et améliorée
     *
     * @param Request|string|null $input
     * @return bool
     */
    protected function validateCsrfToken($input = null): bool
    {
        $token = null;

        // Récupérer le token selon le type d'entrée
        if ($input instanceof Request) {
            // Essayer d'abord POST, puis query string
            $token = $input->request->get('csrf_token') ?? $input->query->get('csrf_token');
        } elseif (is_string($input)) {
            // Token fourni directement
            $token = $input;
        } else {
            // Chercher dans $_POST puis $_GET comme fallback
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        }

        // Vérifier que le token est présent
        if (empty($token)) {
            error_log('BaseController::validateCsrfToken - Token CSRF vide ou manquant');
            return false;
        }

        // Récupérer le token stocké en session
        $storedToken = $this->session->get('csrf_token');
        if (empty($storedToken)) {
            error_log('BaseController::validateCsrfToken - Token CSRF non trouvé en session');
            return false;
        }

        // Vérifier si nous sommes en cours de validation par le middleware
        if ($this->session->get('csrf_validation_in_progress', false)) {
            error_log('BaseController::validateCsrfToken - Validation déjà en cours par le middleware, accepté');
            return true;
        }

        // Validation sécurisée avec hash_equals
        $result = hash_equals($storedToken, $token);

        // Log détaillé pour déboguer
        error_log('BaseController::validateCsrfToken - Validation CSRF: ' . ($result ? 'succès' : 'échec') .
            ' (soumis: ' . substr($token, 0, 10) . '..., stocké: ' . substr($storedToken, 0, 10) . '...)');

        return $result;
    }

    /**
     * Set a flash message
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    /**
     * Validate request data
     *
     * @param array $data
     * @param array $rules
     * @throws ValidationException
     * @return array
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
     *
     * @param string $ability
     * @param mixed $model
     * @throws AuthorizationException
     * @return void
     */
    protected function authorize(string $ability, $model = null): void
    {
        // Vérifier que le conteneur est disponible
        $container = Container::getInstance();
        if (!$container || !$container->has(AuthService::class)) {
            throw new AuthorizationException("Service d'authentification non disponible");
        }

        $authService = $container->get(AuthService::class);
        if (!$authService->can($ability, $model)) {
            throw new AuthorizationException("Action non autorisée");
        }
    }
}
