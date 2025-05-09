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
    }

    /**
     * Check if routes are empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->routes);
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
                    
                    if ($controller && $action) {
                        $this->addRoute($method, $path, [
                            'controller' => $controller,
                            'action' => $action
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
        // Convert path to regex pattern
        $pattern = $this->pathToRegex($path);
        
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
        // Escape regex special chars
        $path = preg_quote($path, '/');
        
        // Convert {param} to named capture groups
        $path = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\}/', '(?P<$1>[^\/]+)', $path);
        
        // Convert {param?} to optional named capture groups
        $path = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\?\\\}/', '(?P<$1>[^\/]+)?', $path);
        
        return '/^' . $path . '$/';
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
        $routes = $this->routes[$method] ?? [];
        
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
            // Utiliser getPathInfo() systématiquement
            $path = $request->getPathInfo();
            
            // Resolve the route
            $route = $this->resolve($request->getMethod(), $path);
            
            // Add URL parameters to the request with type conversion
            foreach ($route['params'] as $key => $value) {
                // Conversion de type automatique
                if (is_numeric($value) && intval($value) == $value) {
                    $value = (int)$value;
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                $request->attributes->set($key, $value);
            }
            
            // Execute the route handler
            return $this->executeHandler($route['handler'], $request);
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
        // Support pour les fonctions/callables
        if (is_callable($handler)) {
            return $handler($request);
        }
        
        // Support pour les invokable controllers
        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return $handler($request);
        }
        
        // Support pour le format ['controller' => Class, 'action' => method]
        if (is_array($handler) && isset($handler['controller']) && isset($handler['action'])) {
            $controllerClass = $handler['controller'];
            $action = $handler['action'];
            
            // Obtenir l'instance du contrôleur depuis le conteneur
            $controller = $this->container->get($controllerClass);
            
            if (!method_exists($controller, $action)) {
                throw new \Exception("Action '$action' not found in controller '$controllerClass'");
            }
            
            // Exécuter l'action du contrôleur
            return $controller->$action($request);
        }
        
        throw new \Exception("Invalid route handler.");
    }
}