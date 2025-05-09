<?php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Controllers\ErrorController;
use TopoclimbCH\Exceptions\RouteNotFoundException;

class Router
{
    /**
     * @var array
     */
    private array $routes = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ContainerBuilder
     */
    private ContainerBuilder $container;

    /**
     * Router constructor.
     *
     * @param LoggerInterface $logger
     * @param ContainerBuilder $container
     */
    public function __construct(LoggerInterface $logger, ContainerBuilder $container)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->loadRoutes();
    }

    /**
     * Load all application routes.
     *
     * @return void
     */
    private function loadRoutes(): void
    {
        // Charger les routes depuis le fichier de configuration
        $routesFile = BASE_PATH . '/config/routes.php';
        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            foreach ($routes as $route) {
                $this->addRoute(
                    $route['method'] ?? 'GET',
                    $route['path'],
                    $route['controller'],
                    $route['action']
                );
            }
        }
    }

    /**
     * Add a route to the router.
     *
     * @param string $method
     * @param string $path
     * @param string $controller
     * @param string $action
     * @return void
     */
    public function addRoute(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Dispatch the request to the appropriate controller.
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        $this->logger->info('Dispatching route', [
            'method' => $method,
            'path' => $path
        ]);

        try {
            // Trouver la route correspondante
            foreach ($this->routes as $route) {
                if ($this->matchRoute($route, $method, $path)) {
                    return $this->executeController($route['controller'], $route['action'], $request);
                }
            }

            // Aucune route correspondante trouvée
            throw new RouteNotFoundException("No route found for $method $path");
        } catch (RouteNotFoundException $e) {
            $this->logger->warning('Route not found', [
                'method' => $method,
                'path' => $path,
                'exception' => $e
            ]);

            // Rediriger vers le contrôleur d'erreur 404
            $controller = new ErrorController();
            return $controller->notFound($request);
        } catch (\Throwable $e) {
            $this->logger->error('Error dispatching route', [
                'method' => $method,
                'path' => $path,
                'exception' => $e
            ]);

            // Rediriger vers le contrôleur d'erreur 500
            $controller = new ErrorController();
            return $controller->serverError($request, $e);
        }
    }

    /**
     * Check if a route matches the request.
     *
     * @param array $route
     * @param string $method
     * @param string $path
     * @return bool
     */
    private function matchRoute(array $route, string $method, string $path): bool
    {
        if ($route['method'] !== $method) {
            return false;
        }

        // TODO: Implémenter le matching des paramètres d'URL
        return $route['path'] === $path;
    }

    /**
     * Execute the controller action.
     *
     * @param string $controllerClass
     * @param string $action
     * @param Request $request
     * @return Response
     * @throws \ReflectionException
     */
    private function executeController(string $controllerClass, string $action, Request $request): Response
    {
        // Vérifier si le contrôleur existe
        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller $controllerClass does not exist");
        }

        // Créer une instance du contrôleur
        $controller = new $controllerClass();

        // Vérifier si l'action existe
        if (!method_exists($controller, $action)) {
            throw new \RuntimeException("Action $action does not exist in controller $controllerClass");
        }

        // Exécuter l'action et retourner la réponse
        return $controller->$action($request);
    }
}