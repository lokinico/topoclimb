<?php

namespace TopoclimbCH\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

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
     * Application constructor.
     *
     * @param Router $router
     * @param LoggerInterface $logger
     */
    public function __construct(Router $router, LoggerInterface $logger)
    {
        $this->router = $router;
        $this->logger = $logger;
        $this->request = Request::createFromGlobals();
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

            // Charger explicitement les routes
            $routesFile = BASE_PATH . '/config/routes.php';
            if (file_exists($routesFile)) {
                $this->logger->info('Loading routes from file: ' . $routesFile);
                $this->router->loadRoutes($routesFile);
            } else {
                $this->logger->error('Routes file not found: ' . $routesFile);
            }
            
            // Débogage - afficher les routes chargées
            $this->logger->debug('Routes loaded: ' . json_encode($this->router->getRoutes()));

            return $this->router->dispatch($this->request);
        } catch (RouteNotFoundException $e) {
            $this->logger->warning('Route not found', [
                'uri' => $this->request->getUri(),
                'message' => $e->getMessage(),
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
}