<?php
// src/Core/Application.php - Application refactorisée

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
     * @var MiddlewareRegistry
     */
    private MiddlewareRegistry $middlewareRegistry;

    /**
     * @var array
     */
    private array $config;

    /**
     * @var bool
     */
    private bool $booted = false;

    /**
     * Application constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->container = $this->buildContainer();
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->middlewareRegistry = $this->container->get(MiddlewareRegistry::class);
        $this->router = $this->container->get(Router::class);
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

        // Enregistrer les services
        $this->registerServices();

        // Charger les routes
        $this->loadRoutes();

        // Enregistrer les middlewares globaux
        $this->registerGlobalMiddlewares();

        $this->booted = true;
    }

    /**
     * Charger la configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Charger les configurations depuis les fichiers
        $configPaths = [
            'app' => config_path('app.php'),
            'database' => config_path('database.php'),
            'routing' => config_path('routing.php'),
            'middleware' => config_path('middleware.php'),
        ];

        foreach ($configPaths as $key => $path) {
            if (file_exists($path)) {
                $this->config[$key] = require $path;
            }
        }
    }

    /**
     * Enregistrer les services dans le container
     *
     * @return void
     */
    private function registerServices(): void
    {
        // Les services sont déjà enregistrés via ContainerBuilder
        // Ici on peut ajouter des configurations spécifiques
    }

    /**
     * Charger les routes
     *
     * @return void
     */
    private function loadRoutes(): void
    {
        $routesPath = config_path('routes');

        if (is_dir($routesPath)) {
            // Nouveau système : charger depuis le répertoire routes/
            $this->router->loadRoutesFromDirectory($routesPath);
        } else {
            // Compatibilité ascendante : charger depuis le fichier unique
            $routesFile = config_path('routes.php');
            if (file_exists($routesFile)) {
                $this->router->loadRoutes($routesFile);
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
     * Construire le container DI
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
        $path = base_path($directory);

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
     * @return UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->container->get(UrlGenerator::class);
    }

    /**
     * Vérifier si on est en environnement de développement
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Vérifier si on est en environnement de production
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
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
};
