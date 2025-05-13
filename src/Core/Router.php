<?php
// src/Core/Router.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Exceptions\RouteNotFoundException;

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
     * Load routes from a file
     *
     * @param string $file Path to routes file
     * @return Router
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
        
        return $this;
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
        // Support pour ['controller' => Class, 'action' => method] format
        if (is_array($handler) && isset($handler['controller']) && isset($handler['action'])) {
            $controllerClass = $handler['controller'];
            $action = $handler['action'];
            
            try {
                // Approche 1 : Vérification explicite de l'existence
                if (!$this->container->has($controllerClass)) {
                    throw new \Exception("Container does not have service: $controllerClass");
                }
                
                // Approche 2 : Tester les alternatives de nommage
                $controller = null;
                $alternatives = [
                    $controllerClass,                        // Nom complet (TopoclimbCH\Controllers\HomeController)
                    basename(str_replace('\\', '/', $controllerClass)), // Nom court (HomeController)
                    'TopoclimbCH\\Controllers\\' . basename(str_replace('\\', '/', $controllerClass)) // Reconstruction
                ];
                
                foreach ($alternatives as $alternative) {
                    if ($this->container->has($alternative)) {
                        $controller = $this->container->get($alternative);
                        break;
                    }
                }
                
                if ($controller === null) {
                    // Fallback : Instanciation directe si la classe existe
                    if (class_exists($controllerClass)) {
                        $view = $this->container->get(View::class);
                        $session = $this->container->get(Session::class);
                        $controller = new $controllerClass($view, $session);
                    } else {
                        throw new \Exception("Controller class not found: $controllerClass");
                    }
                }
                
                if (!method_exists($controller, $action)) {
                    throw new \Exception("Action '$action' not found in controller '$controllerClass'");
                }
                
                // Execute controller action
                return $controller->$action($request);
            } catch (\Exception $e) {
                // Log l'erreur pour le débogage
                $this->logger->error("Controller error: " . $e->getMessage());
                throw $e;
            }
        }
        
        throw new \Exception("Invalid route handler.");
    }
}