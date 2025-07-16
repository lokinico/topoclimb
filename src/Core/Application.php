<?php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Exceptions\RouteNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Application
{
    private Router $router;
    private LoggerInterface $logger;
    private Request $request;
    private ContainerInterface $container;
    private string $environment;

    /**
     * Application constructor - GARDE L'API ORIGINALE
     */
    public function __construct(
        Router $router,
        LoggerInterface $logger,
        ContainerInterface $container,
        string $environment = 'production'
    ) {
        $this->router = $router;
        $this->logger = $logger;
        $this->container = $container;
        $this->environment = $environment;
        $this->request = Request::createFromGlobals();

        // Bootstrap optionnel pour les nouveaux middlewares
        $this->setupMiddlewares();
    }

    /**
     * Configuration optionnelle des middlewares (nouveau)
     */
    private function setupMiddlewares(): void
    {
        try {
            // Enregistrer les middlewares dans le container s'ils n'existent pas déjà
            if (!$this->container->has('auth.middleware')) {
                // Les middlewares seront créés à la demande par le router
                error_log("Application: Middlewares setup completed");
            }
        } catch (\Exception $e) {
            error_log("Application: Warning - Middleware setup failed: " . $e->getMessage());
            // Ne pas faire planter l'app si les middlewares échouent
        }
    }

    /**
     * Handle the request and return a response - GARDE LA LOGIQUE ORIGINALE
     */
    public function handle(): Response
    {
        try {
            // Vérification optionnelle de l'utilisateur banni (nouveau)
            $this->checkBannedUser();

            return $this->router->dispatch($this->request);
        } catch (RouteNotFoundException $e) {
            return $this->createNotFoundResponse($e);
        } catch (\Throwable $e) {
            return $this->createErrorResponse($e);
        }
    }

    /**
     * Vérification utilisateur banni (nouveau, optionnel)
     */
    private function checkBannedUser(): void
    {
        try {
            // Vérifier si la session existe et si l'utilisateur est banni
            if (isset($_SESSION['auth_user_id']) && isset($_SESSION['user_banned']) && $_SESSION['user_banned']) {
                $currentPath = $this->request->getPathInfo();
                if (!in_array($currentPath, ['/banned', '/logout'])) {
                    // Redirection vers page banned si nécessaire
                    $response = new Response('', 302, ['Location' => '/banned']);
                    $response->send();
                    exit;
                }
            }
        } catch (\Exception $e) {
            // Ne pas faire planter l'app si la vérification échoue
            error_log("Application: Warning - Ban check failed: " . $e->getMessage());
        }
    }

    /**
     * Run the application - GARDE LA LOGIQUE ORIGINALE
     */
    public function run(): void
    {
        error_log("Application::run() started");
        $response = $this->handle();

        error_log("Response object created: " . get_class($response));
        error_log("Response status code: " . $response->getStatusCode());

        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            error_log("Sending response...");
            $response->send();
            exit(); // Arrêter l'exécution après l'envoi de la réponse
        } else {
            error_log("WARNING: Unknown response type: " . get_class($response));
            if (is_string($response)) {
                (new Response($response))->send();
            }
        }
    }

    /**
     * Create a 404 response - GARDE LA LOGIQUE ORIGINALE
     */
    private function createNotFoundResponse(\Throwable $e): Response
    {
        $this->logger->warning('Route not found', [
            'uri' => $this->request->getUri(),
            'message' => $e->getMessage()
        ]);

        try {
            $controller = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
            return $controller->notFound($this->request);
        } catch (\Throwable $e) {
            $response = new Response('Page not found', 404);
            $response->headers->set('Content-Type', 'text/html');
            $response->setContent('<h1>404 - Page Not Found</h1><p>The requested page could not be found.</p>');
            return $response;
        }
    }

    /**
     * Create a 500 error response - GARDE LA LOGIQUE ORIGINALE AMÉLIORÉE
     */
    private function createErrorResponse(\Throwable $e): Response
    {
        $this->logger->error('Application error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        try {
            if ($this->container->has(\TopoclimbCH\Controllers\ErrorController::class)) {
                $controller = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
                return $controller->serverError($this->request, $e);
            }

            $response = new Response('Internal Server Error', 500);
            $response->headers->set('Content-Type', 'text/html');
            $response->setContent('<h1>500 - Internal Server Error</h1>');

            if ($this->environment === 'development') {
                $response->setContent(
                    $response->getContent() .
                        '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
                        '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (line ' . $e->getLine() . ')</p>' .
                        '<h2>Stack Trace</h2>' .
                        '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                );
            }

            return $response;
        } catch (\Throwable $fallbackError) {
            $response = new Response('Internal Server Error', 500);
            $response->headers->set('Content-Type', 'text/html');
            $content = '<h1>500 - Internal Server Error</h1>';

            if ($this->environment === 'development') {
                $content .= '<p>Original error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                $content .= '<p>Error handler failed: ' . htmlspecialchars($fallbackError->getMessage()) . '</p>';
            }

            $response->setContent($content);
            return $response;
        }
    }

    // ===== NOUVELLES MÉTHODES POUR COMPATIBILITÉ FUTURE =====

    /**
     * Accès au router (nouveau)
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Accès au container (nouveau)
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Accès à l'environnement (nouveau)
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Vérification environnement développement (nouveau)
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Vérification environnement production (nouveau)
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Helper pour créer des middlewares à la volée (nouveau)
     */
    public function createMiddleware(string $type, array $params = []): ?object
    {
        try {
            switch ($type) {
                case 'auth':
                    if (class_exists(\TopoclimbCH\Middleware\AuthMiddleware::class)) {
                        return new \TopoclimbCH\Middleware\AuthMiddleware();
                    }
                    break;
                case 'admin':
                    if (class_exists(\TopoclimbCH\Middleware\AdminMiddleware::class)) {
                        return new \TopoclimbCH\Middleware\AdminMiddleware();
                    }
                    break;
                case 'csrf':
                    if (class_exists(\TopoclimbCH\Middleware\CsrfMiddleware::class)) {
                        return new \TopoclimbCH\Middleware\CsrfMiddleware();
                    }
                    break;
            }
        } catch (\Exception $e) {
            error_log("Application: Failed to create middleware '$type': " . $e->getMessage());
        }

        return null;
    }
}
