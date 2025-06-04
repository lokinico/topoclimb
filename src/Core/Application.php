<?php
// src/Core/Application.php - Version avec compatibilité ascendante

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Router;
use TopoclimbCH\Core\Routing\RouteCache;
use TopoclimbCH\Core\Routing\UrlGenerator;
use TopoclimbCH\Middleware\MiddlewareRegistry;
use TopoclimbCH\Exceptions\RouteNotFoundException;

class Application
{
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var Router
     */
    private Router $router;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var MiddlewareRegistry|null
     */
    private ?MiddlewareRegistry $middlewareRegistry = null;

    /**
     * @var array
     */
    private array $config;

    /**
     * @var bool
     */
    private bool $booted = false;

    /**
     * @var string
     */
    private string $environment;

    /**
     * Application constructor - Compatible avec ancien et nouveau format
     *
     * Nouveau format : new Application(['config' => 'array'])
     * Ancien format  : new Application($router, $logger, $container, $environment)
     */
    public function __construct(...$args)
    {
        if (count($args) === 1 && is_array($args[0])) {
            // Nouveau format avec array de configuration
            $this->initializeNew($args[0]);
        } elseif (count($args) >= 3) {
            // Ancien format avec arguments séparés (compatibilité ascendante)
            $this->initializeOld(...$args);
        } else {
            throw new \InvalidArgumentException(
                'Invalid arguments for Application constructor. ' .
                    'Use either new Application($config) or new Application($router, $logger, $container, $environment)'
            );
        }
    }

    /**
     * Initialisation avec le nouveau format
     *
     * @param array $config
     */
    private function initializeNew(array $config): void
    {
        $this->config = $config;
        $this->environment = $_ENV['APP_ENV'] ?? 'production';

        $this->container = $this->buildContainer();
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->router = $this->container->get(Router::class);

        if ($this->container->has(MiddlewareRegistry::class)) {
            $this->middlewareRegistry = $this->container->get(MiddlewareRegistry::class);
        }
    }

    /**
     * Initialisation avec l'ancien format (compatibilité)
     *
     * @param Router $router
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     * @param string $environment
     */
    private function initializeOld(Router $router, LoggerInterface $logger, ContainerInterface $container, string $environment = 'production'): void
    {
        $this->router = $router;
        $this->logger = $logger;
        $this->container = $container;
        $this->environment = $environment;
        $this->config = $this->loadDefaultConfig();

        // Essayer de récupérer le MiddlewareRegistry si disponible
        if ($this->container->has(MiddlewareRegistry::class)) {
            $this->middlewareRegistry = $this->container->get(MiddlewareRegistry::class);
        }
    }

    /**
     * Charger la configuration par défaut pour l'ancien format
     *
     * @return array
     */
    private function loadDefaultConfig(): array
    {
        return [
            'routing' => [
                'cache_enabled' => $this->environment === 'production',
                'cache_path' => sys_get_temp_dir() . '/routes.cache',
                'base_url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'force_https' => $this->environment === 'production',
                'api_version' => 'v1',
                'route_model_binding' => true
            ]
        ];
    }

    /**
     * Bootstrap l'application
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        // Charger la configuration
        $this->loadConfiguration();

        // Enregistrer les services (uniquement pour le nouveau format)
        if ($this->wasInitializedWithNewFormat()) {
            $this->registerServices();
        }

        // Charger les routes
        $this->loadRoutes();

        // Enregistrer les middlewares globaux
        $this->registerGlobalMiddlewares();

        $this->booted = true;
    }

    /**
     * Vérifier si l'application a été initialisée avec le nouveau format
     *
     * @return bool
     */
    private function wasInitializedWithNewFormat(): bool
    {
        // Si le container n'a pas les services de base, c'est l'ancien format
        return $this->container instanceof ContainerBuilder ||
            $this->container->has(Router::class);
    }

    /**
     * Charger la configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Essayer de charger les fichiers de configuration
        $configPaths = [
            'app' => $this->getConfigPath('app.php'),
            'database' => $this->getConfigPath('database.php'),
            'routing' => $this->getConfigPath('routing.php'),
            'middleware' => $this->getConfigPath('middleware.php'),
        ];

        foreach ($configPaths as $key => $path) {
            if (file_exists($path)) {
                $this->config[$key] = require $path;
            }
        }
    }

    /**
     * Obtenir le chemin vers un fichier de configuration
     *
     * @param string $filename
     * @return string
     */
    private function getConfigPath(string $filename): string
    {
        // Essayer plusieurs emplacements possibles
        $possiblePaths = [
            __DIR__ . '/../../config/' . $filename,
            dirname(dirname(__DIR__)) . '/config/' . $filename,
            $_SERVER['DOCUMENT_ROOT'] . '/../config/' . $filename
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Enregistrer les services dans le container (nouveau format uniquement)
     *
     * @return void
     */
    private function registerServices(): void
    {
        // Cette méthode est appelée uniquement pour le nouveau format
        // Les services sont déjà enregistrés via ContainerBuilder dans buildContainer()
    }

    /**
     * Charger les routes
     *
     * @return void
     */
    private function loadRoutes(): void
    {
        // Si le router n'a pas de routes et qu'on peut en charger
        if ($this->router->isEmpty()) {
            $routesPath = $this->getConfigPath('routes');

            if (is_dir($routesPath)) {
                // Nouveau système : charger depuis le répertoire routes/
                $this->router->loadRoutesFromDirectory($routesPath);
            } else {
                // Compatibilité ascendante : charger depuis le fichier unique
                $routesFile = $this->getConfigPath('routes.php');
                if (file_exists($routesFile)) {
                    $this->router->loadRoutes($routesFile);
                }
            }
        }

        // Optimiser le cache des routes en production
        if ($this->isProduction() && $this->router->isEmpty()) {
            $this->logger->warning('No routes loaded in production environment');
        }
    }

    /**
     * Enregistrer les middlewares globaux
     *
     * @return void
     */
    private function registerGlobalMiddlewares(): void
    {
        $globalMiddlewares = $this->config['middleware']['global'] ?? [];

        foreach ($globalMiddlewares as $middleware) {
            // Les middlewares globaux peuvent être ajoutés ici si nécessaire
            // Pour l'instant, ils sont gérés au niveau des routes
        }
    }

    /**
     * Construire le container DI (nouveau format uniquement)
     *
     * @return ContainerInterface
     */
    private function buildContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();

        // Enregistrer les services de base
        $this->registerCoreServices($builder);

        // Enregistrer les contrôleurs
        $this->registerControllers($builder);

        // Enregistrer les middlewares
        $this->registerMiddlewares($builder);

        // Compiler le container
        $builder->compile();

        return $builder;
    }

    /**
     * Enregistrer les services de base
     *
     * @param ContainerBuilder $builder
     * @return void
     */
    private function registerCoreServices(ContainerBuilder $builder): void
    {
        // Database
        $builder->register(Database::class, Database::class)
            ->setPublic(true);

        // Session
        $builder->register(Session::class, Session::class)
            ->setPublic(true);

        // View
        $builder->register(View::class, View::class)
            ->setPublic(true);

        // Logger
        $builder->register(LoggerInterface::class, \Monolog\Logger::class)
            ->setArguments(['topoclimb'])
            ->setPublic(true);

        // Router avec configuration
        $builder->register(Router::class, Router::class)
            ->setArguments([
                '$logger' => $builder->getDefinition(LoggerInterface::class),
                '$container' => $builder,
                '$config' => $this->config['routing'] ?? []
            ])
            ->setPublic(true);

        // Route Cache
        $builder->register(RouteCache::class, RouteCache::class)
            ->setArguments([
                '$cachePath' => $this->config['routing']['cache_path'] ?? sys_get_temp_dir() . '/routes.cache'
            ])
            ->setPublic(true);

        // URL Generator
        $builder->register(UrlGenerator::class, UrlGenerator::class)
            ->setArguments([
                '$router' => $builder->getDefinition(Router::class),
                '$config' => $this->config['routing'] ?? []
            ])
            ->setPublic(true);

        // Middleware Registry
        $builder->register(MiddlewareRegistry::class, MiddlewareRegistry::class)
            ->setArguments(['$container' => $builder])
            ->setPublic(true);
    }

    /**
     * Enregistrer les contrôleurs
     *
     * @param ContainerBuilder $builder
     * @return void
     */
    private function registerControllers(ContainerBuilder $builder): void
    {
        $controllerNamespaces = [
            'TopoclimbCH\\Controllers\\',
            'TopoclimbCH\\Controllers\\Admin\\',
            'TopoclimbCH\\Controllers\\Api\\V1\\',
            'TopoclimbCH\\Controllers\\Api\\V2\\',
        ];

        foreach ($controllerNamespaces as $namespace) {
            $this->autoRegisterClasses($builder, $namespace, 'src/Controllers/');
        }
    }

    /**
     * Enregistrer les middlewares
     *
     * @param ContainerBuilder $builder
     * @return void
     */
    private function registerMiddlewares(ContainerBuilder $builder): void
    {
        $this->autoRegisterClasses($builder, 'TopoclimbCH\\Middleware\\', 'src/Middleware/');
    }

    /**
     * Auto-enregistrer les classes d'un namespace
     *
     * @param ContainerBuilder $builder
     * @param string $namespace
     * @param string $directory
     * @return void
     */
    private function autoRegisterClasses(ContainerBuilder $builder, string $namespace, string $directory): void
    {
        $path = $this->getBasePath() . '/' . $directory;

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($path . '/', '', $file->getPathname());
                $className = $namespace . str_replace(['/', '.php'], ['\\', ''], $relativePath);

                if (class_exists($className)) {
                    $builder->register($className, $className)
                        ->setAutowired(true)
                        ->setPublic(true);
                }
            }
        }
    }

    /**
     * Obtenir le chemin de base de l'application
     *
     * @return string
     */
    private function getBasePath(): string
    {
        // Essayer plusieurs méthodes pour trouver le chemin de base
        if (defined('BASE_PATH')) {
            return BASE_PATH;
        }

        // Remonter depuis le fichier actuel
        return dirname(dirname(__DIR__));
    }

    /**
     * Gérer une requête HTTP
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $this->bootstrap();

            return $this->router->dispatch($request);
        } catch (RouteNotFoundException $e) {
            return $this->handleRouteNotFound($request, $e);
        } catch (\Exception $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Gérer les routes non trouvées
     *
     * @param Request $request
     * @param RouteNotFoundException $e
     * @return Response
     */
    private function handleRouteNotFound(Request $request, RouteNotFoundException $e): Response
    {
        $this->logger->warning('Route not found', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'message' => $e->getMessage()
        ]);

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new Response(
                json_encode(['error' => 'Route not found']),
                404,
                ['Content-Type' => 'application/json']
            );
        }

        // Essayer de charger un contrôleur d'erreur
        try {
            if ($this->container->has('TopoclimbCH\\Controllers\\ErrorController')) {
                $errorController = $this->container->get('TopoclimbCH\\Controllers\\ErrorController');
                return $errorController->notFound($request);
            }
        } catch (\Exception $controllerException) {
            $this->logger->error('Error controller failed', [
                'exception' => $controllerException->getMessage()
            ]);
        }

        return new Response('Not Found', 404);
    }

    /**
     * Gérer les exceptions
     *
     * @param Request $request
     * @param \Exception $e
     * @return Response
     */
    private function handleException(Request $request, \Exception $e): Response
    {
        $this->logger->error('Application exception', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri()
        ]);

        if ($this->isDevelopment()) {
            // En développement, afficher l'erreur complète
            if (class_exists('Whoops\\Run')) {
                $whoops = new \Whoops\Run();
                $whoops->allowQuit(false);
                $whoops->writeToOutput(false);

                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    $whoops->prependHandler(new \Whoops\Handler\JsonResponseHandler());
                } else {
                    $whoops->prependHandler(new \Whoops\Handler\PrettyPageHandler());
                }

                return new Response($whoops->handleException($e), 500);
            }
        }

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
            return new Response(
                json_encode(['error' => 'Internal Server Error']),
                500,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response('Internal Server Error', 500);
    }

    /**
     * Exécuter l'application
     *
     * @return void
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Obtenir le router
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Obtenir le container
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Obtenir l'URL generator
     *
     * @return UrlGenerator|null
     */
    public function getUrlGenerator(): ?UrlGenerator
    {
        try {
            if ($this->container->has(UrlGenerator::class)) {
                return $this->container->get(UrlGenerator::class);
            }
        } catch (\Exception $e) {
            $this->logger->warning('UrlGenerator not available: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtenir l'environnement
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Vérifier si on est en environnement de développement
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Vérifier si on est en environnement de production
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Démarrer l'application (méthode statique pour compatibilité)
     *
     * @param array $config
     * @return Application
     */
    public static function start(array $config = []): self
    {
        return new self($config);
    }
}
