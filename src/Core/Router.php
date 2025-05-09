<?php

namespace TopoclimbCH\Core;

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
     * Paramètres d'URL extraits
     *
     * @var array
     */
    private array $params = [];
    
    /**
     * Namespace par défaut pour les contrôleurs
     *
     * @var string
     */
    private string $namespace = 'TopoclimbCH\\Controllers\\';

    /**
     * Ajoute une route pour la méthode GET
     *
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function get(string $path, string|callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Ajoute une route pour la méthode POST
     *
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function post(string $path, string|callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Ajoute une route pour la méthode PUT
     *
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function put(string $path, string|callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Ajoute une route pour la méthode DELETE
     *
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function delete(string $path, string|callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Ajoute une route pour toutes les méthodes HTTP
     *
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function any(string $path, string|callable $handler): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }

    /**
     * Ajoute une route
     *
     * @param string $method Méthode HTTP
     * @param string $path Chemin de la route
     * @param string|callable $handler Gestionnaire de la route (contrôleur@méthode ou callable)
     * @return Router
     */
    public function addRoute(string $method, string $path, string|callable $handler): self
    {
        // Convertit le chemin en expression régulière
        $pattern = $this->pathToRegex($path);
        
        // Enregistre la route
        $this->routes[$method][$pattern] = [
            'path' => $path,
            'handler' => $handler
        ];
        
        return $this;
    }

    /**
     * Convertit un chemin en expression régulière
     *
     * @param string $path Chemin de la route
     * @return string Expression régulière
     */
    private function pathToRegex(string $path): string
    {
        // Échappe les caractères spéciaux de regex
        $path = preg_quote($path, '/');
        
        // Convertit les paramètres {param} en groupes de capture nommés
        $path = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\}/', '(?P<$1>[^\/]+)', $path);
        
        // Convertit les paramètres {param?} en groupes de capture nommés optionnels
        $path = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\?\\\}/', '(?P<$1>[^\/]+)?', $path);
        
        return '/^' . $path . '$/';
    }

    /**
     * Résout une route en fonction de la méthode HTTP et du chemin
     *
     * @param string $method Méthode HTTP
     * @param string $path Chemin de la requête
     * @return array Information sur la route résolue
     * @throws RouteNotFoundException Si aucune route ne correspond
     */
    public function resolve(string $method, string $path): array
    {
        // Récupère les routes pour la méthode HTTP
        $routes = $this->routes[$method] ?? [];
        
        // Parcourt toutes les routes pour trouver une correspondance
        foreach ($routes as $pattern => $route) {
            if (preg_match($pattern, $path, $matches)) {
                // Extrait les paramètres capturés
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                
                $this->params = $params;
                
                return [
                    'handler' => $route['handler'],
                    'params' => $params
                ];
            }
        }
        
        // Aucune route ne correspond
        throw new RouteNotFoundException("Route non trouvée pour $method $path");
    }

    /**
     * Récupère les paramètres d'URL extraits
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Dispatche la requête et retourne la réponse
     *
     * @param Request $request Requête HTTP
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        try {
            // Essaie de résoudre la route
            $route = $this->resolve($request->getMethod(), $request->getPath());
            
            // Ajoute les paramètres d'URL à la requête
            foreach ($route['params'] as $key => $value) {
                $request->setParam($key, $value);
            }
            
            // Exécute le gestionnaire de route
            return $this->executeHandler($route['handler'], $request);
        } catch (RouteNotFoundException $e) {
            // Gère les routes introuvables
            return $this->handleNotFound($request);
        } catch (\Exception $e) {
            // Gère les autres exceptions
            return $this->handleError($request, $e);
        }
    }

    /**
     * Exécute le gestionnaire de route
     *
     * @param mixed $handler Gestionnaire de route
     * @param Request $request Requête HTTP
     * @return Response
     * @throws \Exception
     */
    private function executeHandler(mixed $handler, Request $request): Response
    {
        // Si le gestionnaire est un callable
        if (is_callable($handler)) {
            return call_user_func($handler, $request);
        }
        
        // Si le gestionnaire est une chaîne au format 'Controller@method'
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            $controllerClass = $this->namespace . $controller;
            
            if (!class_exists($controllerClass)) {
                throw new \Exception("Le contrôleur '$controllerClass' n'existe pas.");
            }
            
            $controllerInstance = new $controllerClass();
            
            if (!method_exists($controllerInstance, $method)) {
                throw new \Exception("La méthode '$method' n'existe pas dans le contrôleur '$controllerClass'.");
            }
            
            return $controllerInstance->$method($request);
        }
        
        throw new \Exception("Le gestionnaire de route n'est pas valide.");
    }

    /**
     * Gère les routes introuvables
     *
     * @param Request $request Requête HTTP
     * @return Response
     */
    private function handleNotFound(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(404);
        $response->setContent('404 - Page non trouvée');
        return $response;
    }

    /**
     * Gère les erreurs
     *
     * @param Request $request Requête HTTP
     * @param \Exception $exception Exception survenue
     * @return Response
     */
    private function handleError(Request $request, \Exception $exception): Response
    {
        $response = new Response();
        $response->setStatusCode(500);
        $response->setContent('500 - Erreur serveur interne: ' . $exception->getMessage());
        return $response;
    }

    /**
     * Charge les routes à partir d'un fichier
     *
     * @param string $file Chemin du fichier
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
                        $handler = $controller . '@' . $action;
                        $this->addRoute($method, $path, $handler);
                    }
                }
            }
        }
        
        return $this;
    }
}