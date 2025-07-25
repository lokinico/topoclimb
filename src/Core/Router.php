<?php
// src/Core/Router.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Exceptions\RouteNotFoundException;
use TopoclimbCH\Core\Routing\AttributeRouteLoader;

class Router
{
    /**
     * Routes enregistrées
     *
     * @var array
     */
    private array $routes = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Router constructor
     *
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     */
    public function __construct(LoggerInterface $logger, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => []
        ];
    }

    /**
     * Check if routes are empty
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

    /**
     * Load routes from a file with caching support and PHP attributes
     *
     * @param string $file Path to routes file
     * @return Router
     */
    public function loadRoutes(string $file): self
    {
        $environment = $_ENV['APP_ENV'] ?? 'production';
        $cacheFile = BASE_PATH . '/cache/routes/routes.php';
        
        // In production, try to load cached routes first
        if ($environment === 'production' && file_exists($cacheFile)) {
            $cachedRoutes = require $cacheFile;
            if (is_array($cachedRoutes)) {
                $this->routes = $cachedRoutes;
                return $this;
            }
        }

        // Load routes from original file
        if (file_exists($file)) {
            $routes = require $file;

            if (is_array($routes)) {
                foreach ($routes as $route) {
                    $method = $route['method'] ?? 'GET';
                    $path = $route['path'] ?? '/';
                    $controller = $route['controller'] ?? '';
                    $action = $route['action'] ?? '';
                    $middlewares = $route['middlewares'] ?? [];

                    if ($controller && $action) {
                        $this->addRoute($method, $path, [
                            'controller' => $controller,
                            'action' => $action,
                            'middlewares' => $middlewares
                        ]);
                    }
                }
            }
        }

        // Load routes from PHP attributes
        $this->loadAttributeRoutes();

        // Cache routes in production
        if ($environment === 'production') {
            $this->cacheRoutes($cacheFile);
        }

        return $this;
    }

    /**
     * Load routes from PHP attributes
     */
    private function loadAttributeRoutes(): void
    {
        $loader = new AttributeRouteLoader();
        
        // Load from controllers directory
        $controllersDir = BASE_PATH . '/src/Controllers';
        $controllerNamespace = 'TopoclimbCH\\Controllers\\';
        
        $attributeRoutes = $loader->loadFromDirectory($controllersDir, $controllerNamespace);
        
        foreach ($attributeRoutes as $route) {
            $method = $route['method'] ?? 'GET';
            $path = $route['path'] ?? '/';
            $controller = $route['controller'] ?? '';
            $action = $route['action'] ?? '';
            $middlewares = $route['middlewares'] ?? [];

            if ($controller && $action) {
                $this->addRoute($method, $path, [
                    'controller' => $controller,
                    'action' => $action,
                    'middlewares' => $middlewares,
                    'name' => $route['name'] ?? '',
                    'source' => $route['source'] ?? 'attribute'
                ]);
            }
        }
    }

    /**
     * Cache the compiled routes for production use.
     */
    private function cacheRoutes(string $cacheFile): void
    {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheContent = "<?php\n\n// Cached routes generated on " . date('Y-m-d H:i:s') . "\n";
        $cacheContent .= "return " . var_export($this->routes, true) . ";\n";
        
        file_put_contents($cacheFile, $cacheContent);
    }

    /**
     * Add a route
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param array|callable $handler Route handler
     * @return Router
     */
    public function addRoute(string $method, string $path, array|callable $handler): self
    {
        $method = strtoupper($method);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        // Convert path to regex pattern
        $pattern = $this->pathToRegex($path);

        // Ensure middlewares array is set if not provided
        if (is_array($handler) && !isset($handler['middlewares'])) {
            $handler['middlewares'] = [];
        }

        // Register the route
        $this->routes[$method][$pattern] = [
            'path' => $path,
            'handler' => $handler
        ];

        return $this;
    }

    /**
     * Convert a path to a regex pattern
     *
     * @param string $path
     * @return string
     */
    private function pathToRegex(string $path): string
    {
        // Special case for root path
        if ($path === '/') {
            return '#^/$#';
        }

        // Escape regex special chars
        $path = preg_quote($path, '#');

        // Convert {param} to named capture groups
        $path = preg_replace('#\\\{([a-zA-Z0-9_]+)\\\}#', '(?P<$1>[^/]+)', $path);

        // Convert {param?} to optional named capture groups
        $path = preg_replace('#\\\{([a-zA-Z0-9_]+)\\\?\\\}#', '(?P<$1>[^/]+)?', $path);

        return '#^' . $path . '$#';
    }

    /**
     * Resolve a route based on HTTP method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array Resolved route information
     * @throws RouteNotFoundException If no route matches
     */
    public function resolve(string $method, string $path): array
    {
        // Normalize path by removing trailing slash (except for root path)
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        // Get routes for the HTTP method
        $method = strtoupper($method);
        $routes = $this->routes[$method] ?? [];

        if (empty($routes)) {
            throw new RouteNotFoundException("No routes defined for method: $method");
        }

        // Find a matching route
        foreach ($routes as $pattern => $route) {
            if (preg_match($pattern, $path, $matches)) {
                // Extract captured parameters
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $route['handler'],
                    'params' => $params
                ];
            }
        }

        // No route matches
        throw new RouteNotFoundException("Route not found for $method $path");
    }

    /**
     * Dispatch the request to the appropriate route
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        // Ensure path starts with /
        $path = $request->getPathInfo();
        if (empty($path)) {
            $path = '/';
        }

        // Resolve the route
        $route = $this->resolve($request->getMethod(), $path);

        // Add URL parameters to the request
        foreach ($route['params'] as $key => $value) {
            $request->attributes->set($key, $value);
        }

        // Get handler and middlewares
        $handler = $route['handler'];
        $middlewares = [];

        if (is_array($handler) && isset($handler['middlewares'])) {
            $middlewares = $handler['middlewares'];
        }

        // Execute the middleware stack and the route handler
        return $this->executeMiddlewareStack($middlewares, $handler, $request);
    }

    /**
     * Execute the middleware stack and then the route handler
     *
     * @param array $middlewares
     * @param mixed $handler
     * @param Request $request
     * @return Response
     */
    private function executeMiddlewareStack(array $middlewares, mixed $handler, Request $request): Response
    {
        // The final handler is always the route handler
        $stack = function (Request $request) use ($handler) {
            return $this->executeHandler($handler, $request);
        };

        // Build the middleware stack in reverse order (last middleware executes first)
        foreach (array_reverse($middlewares) as $middleware) {
            $middlewareInstance = $this->container->get($middleware);

            // Create a new stack that wraps the current stack with this middleware
            $stack = function (Request $request) use ($middlewareInstance, $stack) {
                return $middlewareInstance->handle($request, $stack);
            };
        }

        // Execute the complete middleware stack
        return $stack($request);
    }

    /**
     * Execute the route handler
     *
     * @param mixed $handler Route handler
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    private function executeHandler(mixed $handler, Request $request): Response
    {
        if (is_array($handler) && isset($handler['controller']) && isset($handler['action'])) {
            $controllerClass = $handler['controller'];
            $action = $handler['action'];

            try {
                // Approche simplifiée
                if (!$this->container->has($controllerClass)) {
                    // Essayer avec l'espace de noms complet
                    $fullControllerClass = 'TopoclimbCH\\Controllers\\' .
                        basename(str_replace('\\', '/', $controllerClass));

                    if ($this->container->has($fullControllerClass)) {
                        $controllerClass = $fullControllerClass;
                    } else {
                        throw new \Exception("Controller not found: $controllerClass");
                    }
                }

                $controller = $this->container->get($controllerClass);

                if (!method_exists($controller, $action)) {
                    throw new \Exception("Action '$action' not found in controller '$controllerClass'");
                }

                // Execute controller action
                return $controller->$action($request);
            } catch (\Exception $e) {
                $this->logger->error("Controller error: " . $e->getMessage());
                throw $e;
            }
        }

        throw new \Exception("Invalid route handler.");
    }
}
