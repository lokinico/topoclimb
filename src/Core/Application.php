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
    }

    /**
     * Handle the request and return a response.
     *
     * @return Response
     */
    public function handle(): Response
    {
        try {
            return $this->router->dispatch($this->request);
        } catch (RouteNotFoundException $e) {
            return $this->createNotFoundResponse($e);
        } catch (\Throwable $e) {
            return $this->createErrorResponse($e);
        }
    }
    
    /**
     * Create a 404 response
     *
     * @param \Throwable $e
     * @return Response
     */
    private function createNotFoundResponse(\Throwable $e): Response
    {
        $this->logger->warning('Route not found', [
            'uri' => $this->request->getUri(),
            'message' => $e->getMessage()
        ]);
        
        try {
            $controller = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
            return $controller->notFound($this->request);
        } catch (\Throwable $e) {
            // Fallback if error controller fails
            $response = new Response('Page not found', 404);
            $response->headers->set('Content-Type', 'text/html');
            $response->setContent('<h1>404 - Page Not Found</h1><p>The requested page could not be found.</p>');
            return $response;
        }
    }
    
    /**
     * Create a 500 error response
     *
     * @param \Throwable $e
     * @return Response
     */
    private function createErrorResponse(\Throwable $e): Response
    {
        $this->logger->error('Application error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        try {
            // Vérifier si le contrôleur existe avant de l'utiliser
            if ($this->container->has(\TopoclimbCH\Controllers\ErrorController::class)) {
                $controller = $this->container->get(\TopoclimbCH\Controllers\ErrorController::class);
                return $controller->serverError($this->request, $e);
            }
            
            // Fallback si le contrôleur n'existe pas
            $response = new Response('Internal Server Error', 500);
            $response->headers->set('Content-Type', 'text/html');
            $response->setContent('<h1>500 - Internal Server Error</h1>');
            
            if ($this->environment === 'development') {
                $response->setContent(
                    $response->getContent() . 
                    '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
                    '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (line ' . $e->getLine() . ')</p>' .
                    '<h2>Stack Trace</h2>' .
                    '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                );
            }
            
            return $response;
        } catch (\Throwable $fallbackError) {
            // Gestion d'erreur de dernier recours
            $response = new Response('Internal Server Error', 500);
            $response->headers->set('Content-Type', 'text/html');
            $content = '<h1>500 - Internal Server Error</h1>';
            
            if ($this->environment === 'development') {
                $content .= '<p>Original error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                $content .= '<p>Error handler failed: ' . htmlspecialchars($fallbackError->getMessage()) . '</p>';
            }
            
            $response->setContent($content);
            return $response;
        }
    }
}