<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorController
{
    /**
     * Page d'erreur 404
     *
     * @param Request $request
     * @return Response
     */
    public function notFound(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->setContent($this->renderView('errors/404.php'));
        
        return $response;
    }
    
    /**
     * Page d'erreur 500
     *
     * @param Request $request
     * @param \Throwable|null $exception
     * @return Response
     */
    public function serverError(Request $request, \Throwable $exception = null): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->setContent($this->renderView('errors/500.php'));
        
        return $response;
    }
    
    /**
     * Page d'erreur 403
     *
     * @param Request $request
     * @return Response
     */
    public function forbidden(Request $request): Response
    {
        $response = new Response();
        $response->setStatusCode(Response::HTTP_FORBIDDEN);
        $response->setContent($this->renderView('errors/403.php'));
        
        return $response;
    }
    
    /**
     * Méthode d'aide pour le rendu des vues
     *
     * @param string $view
     * @param array $data
     * @return string
     */
    private function renderView(string $view, array $data = []): string
    {
        $viewPath = BASE_PATH . '/resources/views/' . $view;
        
        if (!file_exists($viewPath)) {
            return 'Error: View file not found';
        }
        
        extract($data);
        
        ob_start();
        include $viewPath;
        return ob_get_clean();
    }
}