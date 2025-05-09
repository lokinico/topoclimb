<?php
// src/Controllers/BaseController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;

abstract class BaseController
{
    /**
     * @var View
     */
    protected View $view;
    
    /**
     * @var Session
     */
    protected Session $session;
    
    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     */
    public function __construct(View $view, Session $session)
    {
        $this->view = $view;
        $this->session = $session;
    }
    
    /**
     * Render a view with data
     *
     * @param string $view
     * @param array $data
     * @return Response
     */
    protected function render(string $view, array $data = []): Response
    {
        $response = new Response();
        $data['flashes'] = $this->session->getFlashes();
        $content = $this->view->render($view, $data);
        $response->setContent($content);
        
        return $response;
    }
    
    /**
     * Redirect to a route
     *
     * @param string $url
     * @param int $status
     * @return Response
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        $response = new Response();
        $response->setStatusCode($status);
        $response->headers->set('Location', $url);
        
        return $response;
    }
    
    /**
     * Return a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @return Response
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setStatusCode($status);
        $response->setContent(json_encode($data));
        
        return $response;
    }
    
    /**
     * Create a CSRF token for forms
     *
     * @return string
     */
    protected function createCsrfToken(): string
    {
        return $this->session->setCsrfToken();
    }
    
    /**
     * Validate CSRF token
     *
     * @param Request $request
     * @return bool
     */
    protected function validateCsrfToken(Request $request): bool
    {
        $token = $request->request->get('_csrf_token');
        
        if (!$token) {
            return false;
        }
        
        return $this->session->validateCsrfToken($token);
    }
}