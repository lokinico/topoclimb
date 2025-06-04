<?php
// src/Core/Router.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Routing\RouteGroup;
use TopoclimbCH\Core\Routing\RouteCache;
use TopoclimbCH\Core\Routing\UrlGenerator;
use TopoclimbCH\Exceptions\RouteNotFoundException;

class Router
{
    /**
     * Routes enregistrées organisées par méthode HTTP
     *
     * @var array
     */
    private array $routes = [];

    /**
     * Routes nommées pour génération d'URLs
     *
     * @var array
     */
    private array $namedRoutes = [];

    /**
     * Groupes de routes
     *
     * @var array
     */
    private array $groups = [];

    /**
     * Redirections permanentes
     *
     * @var array
     */
    private array $redirects = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var RouteCache
     */
    private RouteCache $cache;

    /**
     * @var UrlGenerator
     */
    private UrlGenerator $urlGenerator;

    /**
     * Configuration du routeur
     *
     * @var array
     */
    private array $config;

    /**
     * Domaine actuel
     *
     * @var string
     */
    private string $currentDomain = '';

    /**
     * Router constructor
     *
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     * @param array $config
     */
    public function __construct(LoggerInterface $logger, ContainerInterface $container, array $config = [])
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->config = array_merge([
            'cache_enabled' => $_ENV['APP_ENV'] === 'production',
            'cache_path' => sys_get_temp_dir() . '/routes.cache',
            'default_domain' => $_ENV['APP_URL'] ?? 'localhost',
            'api_version' => 'v1',
            'supported_domains' => [],
            'route_model_binding' => true
        ], $config);

        $this->cache = new RouteCache($this->config['cache_path']);
        $this->urlGenerator = new UrlGenerator($this);

        $this->initializeRoutes();
    }

    /**
     * Initialize route structure
     *
     * @return void
     */
    private function initializeRoutes(): void
    {
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => [],
            'OPTIONS' => [],
            'HEAD' => []
        ];
    }

    /**
     * Créer un groupe de routes
     *
     * @param array $attributes
     * @param callable $callback
     * @return RouteGroup
     */
    public function group(array $attributes, callable $callback): RouteGroup
    {
        $group = new RouteGroup($this, $attributes);

        // Exécuter le callback avec le groupe
        $callback($group);

        // Stocker le groupe pour référence
        $this->groups[] = $group;

        return $group;
    }

    /**
     * Ajouter une route GET
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function get(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    /**
     * Ajouter une route POST
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function post(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    /**
     * Ajouter une route PUT
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function put(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    /**
     * Ajouter une route DELETE
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function delete(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    /**
     * Ajouter une route PATCH
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function patch(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    /**
     * Ajouter une route pour toutes les méthodes
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function any(string $path, mixed $handler, array $options = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $options);
        }

        return $this;
    }

    /**
     * Ajouter une route avec méthodes spécifiques
     *
     * @param array $methods
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function match(array $methods, string $path, mixed $handler, array $options = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $options);
        }

        return $this;
    }

    /**
     * Ajouter une redirection
     *
     * @param string $from
     * @param string $to
     * @param int $status
     * @param array $options
     * @return self
     */
    public function redirect(string $from, string $to, int $status = 301, array $options = []): self
    {
        $pattern = $this->pathToRegex($from);

        $this->redirects[$pattern] = [
            'to' => $to,
            'status' => $status,
            'domain' => $options['domain'] ?? null,
            'https' => $options['https'] ?? false
        ];

        return $this;
    }

    /**
     * Ajouter une route permanente (pour URLs obsolètes)
     *
     * @param string $from
     * @param string $to
     * @param array $options
     * @return self
     */
    public function permanentRedirect(string $from, string $to, array $options = []): self
    {
        return $this->redirect($from, $to, 301, $options);
    }

    /**
     * Ajouter une route temporaire
     *
     * @param string $from
     * @param string $to
     * @param array $options
     * @return self
     */
    public function temporaryRedirect(string $from, string $to, array $options = []): self
    {
        return $this->redirect($from, $to, 302, $options);
    }

    /**
     * Ajouter une route avec tous les paramètres
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function addRoute(string $method, string $path, mixed $handler, array $options = []): self
    {
        $method = strtoupper($method);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        // Convertir le chemin en pattern regex
        $pattern = $this->pathToRegex($path, $options['constraints'] ?? []);

        // Créer la définition de route
        $routeDefinition = [
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $options['middlewares'] ?? [],
            'name' => $options['name'] ?? null,
            'domain' => $options['domain'] ?? null,
            'constraints' => $options['constraints'] ?? [],
            'defaults' => $options['defaults'] ?? [],
            'prefix' => $options['prefix'] ?? '',
            'namespace' => $options['namespace'] ?? '',
            'where' => $options['where'] ?? [],
            'condition' => $options['condition'] ?? null
        ];

        // Enregistrer la route
        $this->routes[$method][$pattern] = $routeDefinition;

        // Enregistrer le nom de route si fourni
        if ($routeDefinition['name']) {
            $this->namedRoutes[$routeDefinition['name']] = [
                'method' => $method,
                'pattern' => $pattern,
                'path' => $path,
                'handler' => $handler
            ];
        }

        return $this;
    }

    /**
     * Convertir un chemin en pattern regex avec contraintes
     *
     * @param string $path
     * @param array $constraints
     * @return string
     */
    private function pathToRegex(string $path, array $constraints = []): string
    {
        if ($path === '/') {
            return '#^/$#';
        }

        $path = preg_quote($path, '#');

        // Gérer les paramètres avec contraintes
        $path = preg_replace_callback('#\\\{([a-zA-Z0-9_]+)(\\\?)?(\\\})?#', function ($matches) use ($constraints) {
            $paramName = $matches[1];
            $optional = isset($matches[2]) && $matches[2] === '\?';

            // Appliquer la contrainte si définie
            if (isset($constraints[$paramName])) {
                $constraint = $constraints[$paramName];
            } else {
                // Contraintes par défaut
                $constraint = match ($paramName) {
                    'id' => '\d+',
                    'slug' => '[a-z0-9\-]+',
                    'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
                    default => '[^/]+'
                };
            }

            if ($optional) {
                return "(?P<{$paramName}>{$constraint})?";
            }

            return "(?P<{$paramName}>{$constraint})";
        }, $path);

        return '#^' . $path . '$#';
    }

    /**
     * Résoudre une route
     *
     * @param string $method
     * @param string $path
     * @param string|null $domain
     * @return array
     * @throws RouteNotFoundException
     */
    public function resolve(string $method, string $path, ?string $domain = null): array
    {
        // Vérifier le cache d'abord
        if ($this->config['cache_enabled']) {
            $cacheKey = $method . ':' . $path . ':' . ($domain ?? '');
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Normaliser le chemin
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        // Vérifier les redirections d'abord
        $redirect = $this->checkRedirects($path, $domain);
        if ($redirect) {
            return $redirect;
        }

        $method = strtoupper($method);
        $routes = $this->routes[$method] ?? [];

        if (empty($routes)) {
            throw new RouteNotFoundException("No routes defined for method: $method");
        }

        // Chercher une route correspondante
        foreach ($routes as $pattern => $route) {
            // Vérifier le domaine si spécifié
            if ($route['domain'] && $domain && !$this->matchDomain($route['domain'], $domain)) {
                continue;
            }

            // Vérifier les conditions si présentes
            if ($route['condition'] && !$this->evaluateCondition($route['condition'])) {
                continue;
            }

            if (preg_match($pattern, $path, $matches)) {
                // Extraire les paramètres capturés
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                // Appliquer les valeurs par défaut
                $params = array_merge($route['defaults'], $params);

                $result = [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middlewares' => $route['middlewares'],
                    'name' => $route['name'],
                    'route' => $route
                ];

                // Mettre en cache si activé
                if ($this->config['cache_enabled']) {
                    $this->cache->set($cacheKey, $result);
                }

                return $result;
            }
        }

        throw new RouteNotFoundException("Route not found for $method $path" . ($domain ? " on domain $domain" : ""));
    }

    /**
     * Vérifier les redirections
     *
     * @param string $path
     * @param string|null $domain
     * @return array|null
     */
    private function checkRedirects(string $path, ?string $domain = null): ?array
    {
        foreach ($this->redirects as $pattern => $redirect) {
            // Vérifier le domaine si spécifié
            if ($redirect['domain'] && $domain && $redirect['domain'] !== $domain) {
                continue;
            }

            if (preg_match($pattern, $path, $matches)) {
                $to = $redirect['to'];

                // Remplacer les paramètres dans l'URL de destination
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $to = str_replace('{' . $key . '}', $value, $to);
                    }
                }

                return [
                    'type' => 'redirect',
                    'to' => $to,
                    'status' => $redirect['status'],
                    'https' => $redirect['https']
                ];
            }
        }

        return null;
    }

    /**
     * Vérifier la correspondance de domaine
     *
     * @param string $routeDomain
     * @param string $requestDomain
     * @return bool
     */
    private function matchDomain(string $routeDomain, string $requestDomain): bool
    {
        // Support des wildcards
        if (str_contains($routeDomain, '*')) {
            $pattern = str_replace('*', '.*', preg_quote($routeDomain, '/'));
            return (bool) preg_match('/^' . $pattern . '$/', $requestDomain);
        }

        return $routeDomain === $requestDomain;
    }

    /**
     * Évaluer une condition de route
     *
     * @param callable|string $condition
     * @return bool
     */
    private function evaluateCondition(callable|string $condition): bool
    {
        if (is_callable($condition)) {
            return $condition();
        }

        // Conditions prédéfinies
        return match ($condition) {
            'development' => $_ENV['APP_ENV'] === 'development',
            'production' => $_ENV['APP_ENV'] === 'production',
            'testing' => $_ENV['APP_ENV'] === 'testing',
            'admin' => $this->container->get('auth')->hasRole('admin'),
            default => true
        };
    }

    /**
     * Dispatcher une requête
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $path = $request->getPathInfo() ?: '/';
        $domain = $request->getHost();

        try {
            $route = $this->resolve($request->getMethod(), $path, $domain);

            // Gérer les redirections
            if (isset($route['type']) && $route['type'] === 'redirect') {
                return new Response('', $route['status'], [
                    'Location' => $route['to']
                ]);
            }

            // Ajouter les paramètres d'URL à la requête
            foreach ($route['params'] as $key => $value) {
                $request->attributes->set($key, $value);
            }

            // Exécuter la pile de middlewares et le gestionnaire
            return $this->executeMiddlewareStack($route['middlewares'], $route['handler'], $request);
        } catch (RouteNotFoundException $e) {
            $this->logger->warning("Route not found: " . $e->getMessage());

            // Retourner une réponse 404
            return new Response('Not Found', 404);
        }
    }

    /**
     * Exécuter la pile de middlewares
     *
     * @param array $middlewares
     * @param mixed $handler
     * @param Request $request
     * @return Response
     */
    private function executeMiddlewareStack(array $middlewares, mixed $handler, Request $request): Response
    {
        $stack = function (Request $request) use ($handler) {
            return $this->executeHandler($handler, $request);
        };

        foreach (array_reverse($middlewares) as $middleware) {
            if (is_string($middleware)) {
                $middlewareInstance = $this->container->get($middleware);
            } else {
                $middlewareInstance = $middleware;
            }

            $stack = function (Request $request) use ($middlewareInstance, $stack) {
                return $middlewareInstance->handle($request, $stack);
            };
        }

        return $stack($request);
    }

    /**
     * Exécuter le gestionnaire de route
     *
     * @param mixed $handler
     * @param Request $request
     * @return Response
     */
    private function executeHandler(mixed $handler, Request $request): Response
    {
        if (is_array($handler) && isset($handler['controller']) && isset($handler['action'])) {
            $controllerClass = $handler['controller'];
            $action = $handler['action'];

            try {
                $controller = $this->container->get($controllerClass);

                if (!method_exists($controller, $action)) {
                    throw new \Exception("Action '$action' not found in controller '$controllerClass'");
                }

                return $controller->$action($request);
            } catch (\Exception $e) {
                $this->logger->error("Controller error: " . $e->getMessage());
                throw $e;
            }
        }

        if (is_callable($handler)) {
            return $handler($request);
        }

        throw new \Exception("Invalid route handler.");
    }

    /**
     * Charger les routes depuis un fichier (compatibilité ascendante)
     *
     * @param string $file
     * @return self
     */
    public function loadRoutes(string $file): self
    {
        if (file_exists($file)) {
            $routes = require $file;

            if (is_array($routes)) {
                foreach ($routes as $route) {
                    $method = $route['method'] ?? 'GET';
                    $path = $route['path'] ?? '/';
                    $controller = $route['controller'] ?? '';
                    $action = $route['action'] ?? '';
                    $middlewares = $route['middlewares'] ?? [];
                    $name = $route['name'] ?? null;

                    if ($controller && $action) {
                        $this->addRoute($method, $path, [
                            'controller' => $controller,
                            'action' => $action
                        ], [
                            'middlewares' => $middlewares,
                            'name' => $name
                        ]);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Charger les routes organisées par domaine
     *
     * @param string $directory
     * @return self
     */
    public function loadRoutesFromDirectory(string $directory): self
    {
        if (!is_dir($directory)) {
            return $this;
        }

        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $domain = basename($file, '.php');

            // Charger les routes du domaine
            $routes = require $file;

            if (is_callable($routes)) {
                // Les routes sont définies dans une closure
                $routes($this);
            } elseif (is_array($routes)) {
                // Format compatible ancien système
                $this->loadArrayRoutes($routes);
            }
        }

        return $this;
    }

    /**
     * Charger les routes depuis un tableau
     *
     * @param array $routes
     * @return void
     */
    private function loadArrayRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $method = $route['method'] ?? 'GET';
            $path = $route['path'] ?? '/';
            $handler = $route['handler'] ?? $route; // Compatibilité
            $options = array_diff_key($route, array_flip(['method', 'path', 'handler']));

            $this->addRoute($method, $path, $handler, $options);
        }
    }

    /**
     * Obtenir l'URL generator
     *
     * @return UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->urlGenerator;
    }

    /**
     * Générer une URL pour une route nommée
     *
     * @param string $name
     * @param array $params
     * @param bool $absolute
     * @return string
     */
    public function url(string $name, array $params = [], bool $absolute = false): string
    {
        return $this->urlGenerator->generate($name, $params, $absolute);
    }

    /**
     * Obtenir toutes les routes nommées
     *
     * @return array
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Vider le cache des routes
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Vérifier si le routeur est vide
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        foreach ($this->routes as $methodRoutes) {
            if (!empty($methodRoutes)) {
                return false;
            }
        }
        return true;
    }
}
