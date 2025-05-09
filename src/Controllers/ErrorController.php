<?php
// src/Controllers/ErrorController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\View;

class ErrorController
{
    /**
     * @var View
     */
    private View $view;

    /**
     * Constructor
     *
     * @param View $view
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * 404 error page
     *
     * @param Request $request
     * @return Response
     */
    public function notFound(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->setContent($this->view->render('errors/404.twig'));
        
        return $response;
    }
    
    /**
     * 500 error page
     *
     * @param Request $request
     * @param \Throwable|null $exception
     * @return Response
     */
    public function serverError(Request $request, \Throwable $exception = null): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        
        $data = [];
        if ($exception && $_ENV['APP_ENV'] === 'development') {
            $data['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        $response->setContent($this->view->render('errors/500.twig', $data));
        
        return $response;
    }
    
    /**
     * 403 error page
     *
     * @param Request $request
     * @return Response
     */
    public function forbidden(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_FORBIDDEN);
        $response->setContent($this->view->render('errors/403.twig'));
        
        return $response;
    }
}