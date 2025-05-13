<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;

class CsrfMiddleware
{
    private Session $session;
    
    public function __construct(Session $session)
    {
        $this->session = $session;
    }
    
    public function handle(Request $request, callable $next): Response
    {
        // Vérifie uniquement les méthodes non sécurisées (POST, PUT, DELETE, PATCH)
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            // Essayer d'abord la méthode standard de Symfony
            $token = $request->request->get('_csrf_token');
            
            // Si non trouvé, essayer les en-têtes
            if (!$token) {
                $token = $request->headers->get('X-CSRF-TOKEN');
            }
            
            if (!$token || !$this->session->validateCsrfToken($token)) {
                return new Response('Token CSRF invalide', 403);
            }
        }
        
        return $next($request);
    }
}