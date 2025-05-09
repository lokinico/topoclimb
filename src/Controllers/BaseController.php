<?php
// src/Controllers/BaseController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Container;
use TopoclimbCH\Core\Validation\Validator;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;
use TopoclimbCH\Services\AuthService;

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
    
    /**
     * Set a flash message
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }
    
    /**
     * Validate request data
     *
     * @param array $data
     * @param array $rules
     * @throws ValidationException
     * @return array
     */
    protected function validate(array $data, array $rules): array
    {
        $validator = new Validator();
        
        if (!$validator->validate($data, $rules)) {
            $this->session->flash('errors', $validator->getErrors());
            $this->session->flash('old', $data);
            
            throw new ValidationException(
                "Validation failed: " . json_encode($validator->getErrors())
            );
        }
        
        return $data;
    }
    
    /**
     * Check if user has permission
     *
     * @param string $ability
     * @param mixed $model
     * @throws AuthorizationException
     * @return void
     */
    protected function authorize(string $ability, $model = null): void
    {
        // Récupérer le service d'authentification via le conteneur
        $authService = Container::getInstance()->get(AuthService::class);
        
        if (!$authService->can($ability, $model)) {
            throw new AuthorizationException("Action non autorisée");
        }
    }
}