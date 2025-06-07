<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Middleware\AuthMiddleware;
use TopoclimbCH\Middleware\AdminMiddleware;
use TopoclimbCH\Middleware\ModeratorMiddleware;
use TopoclimbCH\Middleware\PermissionMiddleware;
use TopoclimbCH\Middleware\CsrfMiddleware;

class Application
{
    private Router $router;
    private Database $db;
    private Session $session;
    private View $view;
    private Auth $auth;
    private CsrfManager $csrfManager;
    private array $middlewares = [];
    private bool $booted = false;

    public function __construct()
    {
        $this->bootstrap();
    }

    /**
     * Bootstrap de l'application
     */
    private function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        try {
            // Charger la configuration d'environnement
            $this->loadEnvironment();

            // Initialiser les services de base
            $this->initializeServices();

            // Enregistrer les middlewares
            $this->registerMiddlewares();

            // Charger les routes
            $this->loadRoutes();

            // Configurer la gestion d'erreurs
            $this->setupErrorHandling();

            $this->booted = true;

            error_log("Application: Bootstrap terminé avec succès");
        } catch (\Exception $e) {
            error_log("Application: Erreur lors du bootstrap: " . $e->getMessage());
            $this->handleBootstrapError($e);
        }
    }

    /**
     * Charge les variables d'environnement
     */
    private function loadEnvironment(): void
    {
        if (file_exists('.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->load();
        }
    }

    /**
     * Initialise les services principaux
     */
    private function initializeServices(): void
    {
        // Session
        $this->session = Session::getInstance();

        // Base de données
        $this->db = Database::getInstance();

        // Authentification
        $this->auth = Auth::getInstance($this->session, $this->db);

        // CSRF Manager
        $this->csrfManager = new CsrfManager($this->session);

        // Vue avec helpers
        $this->view = new View();

        // Router
        $this->router = new Router();

        error_log("Application: Services initialisés");
    }

    /**
     * Enregistre les middlewares disponibles
     */
    private function registerMiddlewares(): void
    {
        $this->middlewares = [
            'auth' => new AuthMiddleware($this->session, $this->db),
            'admin' => new AdminMiddleware($this->session, $this->db),
            'moderator' => new ModeratorMiddleware($this->session, $this->db),
            'permission' => new PermissionMiddleware($this->session, $this->db),
            'csrf' => new CsrfMiddleware($this->csrfManager, $this->session)
        ];

        error_log("Application: Middlewares enregistrés");
    }

    /**
     * Charge les routes depuis le fichier de configuration
     */
    private function loadRoutes(): void
    {
        $routes = require_once __DIR__ . '/../../config/routes.php';

        foreach ($routes as $route) {
            $method = $route['method'];
            $path = $route['path'];
            $controller = $route['controller'];
            $action = $route['action'];
            $middlewares = $route['middlewares'] ?? [];

            // Créer le handler avec middlewares
            $handler = $this->createHandler($controller, $action, $middlewares);

            // Enregistrer la route
            switch (strtoupper($method)) {
                case 'GET':
                    $this->router->get($path, $handler);
                    break;
                case 'POST':
                    $this->router->post($path, $handler);
                    break;
                case 'PUT':
                    $this->router->put($path, $handler);
                    break;
                case 'DELETE':
                    $this->router->delete($path, $handler);
                    break;
                default:
                    $this->router->any($path, $handler);
            }
        }

        error_log("Application: " . count($routes) . " routes chargées");
    }

    /**
     * Crée un handler avec middlewares pour une route
     */
    private function createHandler(string $controller, string $action, array $middlewares): callable
    {
        return function (Request $request) use ($controller, $action, $middlewares) {
            // Créer la chaîne de middlewares
            $pipeline = $this->createMiddlewarePipeline($middlewares);

            // Handler final (contrôleur)
            $finalHandler = function (Request $request) use ($controller, $action) {
                return $this->executeController($controller, $action, $request);
            };

            // Exécuter la pipeline
            return $this->executePipeline($pipeline, $request, $finalHandler);
        };
    }

    /**
     * Crée la pipeline de middlewares
     */
    private function createMiddlewarePipeline(array $middlewares): array
    {
        $pipeline = [];

        foreach ($middlewares as $middlewareKey => $middlewareParams) {
            // Format: ['middleware' => 'params'] ou juste 'middleware'
            if (is_string($middlewareKey)) {
                $middleware = $this->resolveMiddleware($middlewareKey, $middlewareParams);
            } else {
                $middleware = $this->resolveMiddleware($middlewareParams);
            }

            if ($middleware) {
                $pipeline[] = $middleware;
            }
        }

        return $pipeline;
    }

    /**
     * Résout un middleware par son nom
     */
    private function resolveMiddleware(string $middlewareName, $params = null): ?object
    {
        // Extraire le nom de classe si c'est un FQCN
        if (str_contains($middlewareName, '\\')) {
            $className = basename(str_replace('\\', '/', $middlewareName));
            $middlewareName = strtolower(str_replace('Middleware', '', $className));
        }

        if (isset($this->middlewares[$middlewareName])) {
            $middleware = $this->middlewares[$middlewareName];

            // Si c'est un PermissionMiddleware, passer les paramètres
            if ($middleware instanceof PermissionMiddleware && $params) {
                $middleware->setPermissions($params);
            }

            return $middleware;
        }

        error_log("Application: Middleware '$middlewareName' non trouvé");
        return null;
    }

    /**
     * Exécute la pipeline de middlewares
     */
    private function executePipeline(array $pipeline, Request $request, callable $finalHandler): Response
    {
        if (empty($pipeline)) {
            return $finalHandler($request);
        }

        $middleware = array_shift($pipeline);

        return $middleware->handle($request, function ($request) use ($pipeline, $finalHandler) {
            return $this->executePipeline($pipeline, $request, $finalHandler);
        });
    }

    /**
     * Exécute un contrôleur
     */
    private function executeController(string $controller, string $action, Request $request): Response
    {
        try {
            // Vérifier que la classe existe
            if (!class_exists($controller)) {
                throw new \Exception("Contrôleur '$controller' non trouvé");
            }

            // Instancier le contrôleur avec les dépendances
            $instance = $this->instantiateController($controller);

            // Vérifier que la méthode existe
            if (!method_exists($instance, $action)) {
                throw new \Exception("Action '$action' non trouvée dans '$controller'");
            }

            // Injecter les paramètres d'URL dans la requête
            $this->injectRouteParams($request);

            // Exécuter l'action
            $result = $instance->$action($request);

            // Convertir le résultat en Response si nécessaire
            if (!$result instanceof Response) {
                if (is_string($result)) {
                    $result = new Response($result);
                } elseif (is_array($result)) {
                    $result = Response::json($result);
                } else {
                    $result = new Response('');
                }
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Application: Erreur contrôleur - " . $e->getMessage());
            return $this->handleControllerError($e);
        }
    }

    /**
     * Instancie un contrôleur avec injection de dépendances
     */
    private function instantiateController(string $controller): object
    {
        try {
            $reflection = new \ReflectionClass($controller);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return new $controller();
            }

            $params = [];
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                if ($type && !$type->isBuiltin()) {
                    $typeName = $type->getName();

                    // Injection des services
                    switch ($typeName) {
                        case View::class:
                            $params[] = $this->view;
                            break;
                        case Session::class:
                            $params[] = $this->session;
                            break;
                        case Database::class:
                            $params[] = $this->db;
                            break;
                        case Auth::class:
                            $params[] = $this->auth;
                            break;
                        case CsrfManager::class:
                            $params[] = $this->csrfManager;
                            break;
                        default:
                            // Tenter d'instancier automatiquement
                            if (class_exists($typeName)) {
                                $params[] = new $typeName();
                            } else {
                                $params[] = null;
                            }
                    }
                } else {
                    $params[] = null;
                }
            }

            return $reflection->newInstanceArgs($params);
        } catch (\Exception $e) {
            error_log("Application: Erreur instanciation contrôleur - " . $e->getMessage());
            return new $controller();
        }
    }

    /**
     * Injecte les paramètres de route dans la requête
     */
    private function injectRouteParams(Request $request): void
    {
        // Cette méthode sera appelée par le router pour injecter les paramètres d'URL
        // comme {id}, {slug}, etc.
    }

    /**
     * Configure la gestion d'erreurs
     */
    private function setupErrorHandling(): void
    {
        if ($_ENV['APP_ENV'] === 'development') {
            // En développement, afficher les erreurs détaillées
            ini_set('display_errors', 1);
            error_reporting(E_ALL);

            // Utiliser Whoops si disponible
            if (class_exists(\Whoops\Run::class)) {
                $whoops = new \Whoops\Run();
                $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
                $whoops->register();
            }
        } else {
            // En production, cacher les erreurs
            ini_set('display_errors', 0);
            error_reporting(0);
        }
    }

    /**
     * Gère les erreurs de bootstrap
     */
    private function handleBootstrapError(\Exception $e): void
    {
        if ($_ENV['APP_ENV'] === 'development') {
            die("Erreur de bootstrap: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        } else {
            die("Erreur de démarrage de l'application. Veuillez contacter l'administrateur.");
        }
    }

    /**
     * Gère les erreurs de contrôleur
     */
    private function handleControllerError(\Exception $e): Response
    {
        if ($_ENV['APP_ENV'] === 'development') {
            return new Response(
                "Erreur contrôleur: " . $e->getMessage() . "\n" . $e->getTraceAsString(),
                500
            );
        } else {
            return $this->view->renderError(500, "Une erreur interne est survenue");
        }
    }

    /**
     * Traite une requête HTTP
     */
    public function handle(Request $request): Response
    {
        try {
            // Vérifier si l'utilisateur est banni et rediriger
            if ($this->auth->check() && $this->auth->isBanned()) {
                $currentPath = $request->getPathInfo();
                if (!in_array($currentPath, ['/banned', '/logout'])) {
                    return Response::redirect('/banned');
                }
            }

            // Résoudre la route
            $response = $this->router->resolve($request);

            return $response;
        } catch (\Exception $e) {
            error_log("Application: Erreur traitement requête - " . $e->getMessage());

            if ($e instanceof \TopoclimbCH\Exceptions\RouteNotFoundException) {
                return $this->view->renderError(404, "Page non trouvée");
            }

            return $this->view->renderError(500, "Erreur interne du serveur");
        }
    }

    /**
     * Lance l'application
     */
    public function run(): void
    {
        try {
            $request = Request::createFromGlobals();
            $response = $this->handle($request);
            $response->send();
        } catch (\Exception $e) {
            error_log("Application: Erreur fatale - " . $e->getMessage());

            if ($_ENV['APP_ENV'] === 'development') {
                die("Erreur fatale: " . $e->getMessage());
            } else {
                die("Service temporairement indisponible");
            }
        }
    }

    /**
     * Méthodes d'accès aux services
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
    public function getDatabase(): Database
    {
        return $this->db;
    }
    public function getSession(): Session
    {
        return $this->session;
    }
    public function getView(): View
    {
        return $this->view;
    }
    public function getAuth(): Auth
    {
        return $this->auth;
    }
    public function getCsrfManager(): CsrfManager
    {
        return $this->csrfManager;
    }

    /**
     * Point d'entrée principal
     */
    public static function start(): void
    {
        $app = new self();
        $app->run();
    }
}
