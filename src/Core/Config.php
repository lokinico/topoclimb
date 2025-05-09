<?php
// src/Core/Application.php

namespace TopoclimbCH\Core;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Exceptions\RouteNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Application
{
    /**
     * @var Router
     */
    private Router $router;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Request
     */
    private Request $request;
    
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;
    
    /**
     * @var string
     */
    private string $environment;
    
    /**
     * @var Session
     */
    private Session $session;

    /**
     * Application constructor.
     *
     * @param Router $router
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     * @param string $environment
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
        $this->session = new Session();
    }
    
    /**
     * Check if environment is development
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Handle the request and return a response.
     *
     * @return Response
     */
    public function handle(): Response
    {
        try {
            $this->logger->info('Application handling request', [
                'method' => $this->request->getMethod(),
                'uri' => $this->request->getUri()
            ]);

            // Load routes if they haven't been loaded yet
            if ($this->router->isEmpty()) {
                $routesFile = BASE_PATH . '/config/routes.php';
                if (file_exists($routesFile)) {
                    $this->router->loadRoutes($routesFile);
                }
            }

            return $this->router->dispatch($this->request);
        } catch (RouteNotFoundException $e) {
            $this->logger->warning('Route not found', [
                'uri' => $this->request->getUri(),
                'exception' => $e
            ]);
            
            return $this->handleNotFound();
        } catch (\Throwable $e) {
            $this->logger->error('Error handling request', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->handleError($e);
        }
    }
    
    /**
     * Handle 404 errors
     *
     * @return Response
     */
    private function handleNotFound(): Response
    {
        $errorController = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
        return $errorController->notFound($this->request);
    }
    
    /**
     * Handle other errors
     *
     * @param \Throwable $exception
     * @return Response
     */
    private function handleError(\Throwable $exception): Response
    {
        $errorController = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
        return $errorController->serverError($this->request, $exception);
    }
    
    /**
     * Get the container
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
    
    /**
     * Get the router
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
    
    /**
     * Get the session
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }
    
    /**
     * Get the request
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}