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
            // Essayer les différentes formes possibles de token CSRF
            $token = $request->request->get('_csrf_token');
            
            // Vérifier aussi le format legacy _csrf pour la compatibilité
            if (!$token) {
                $token = $request->request->get('_csrf');
            }
            
            // Si non trouvé, essayer les en-têtes
            if (!$token) {
                $token = $request->headers->get('X-CSRF-TOKEN');
            }
            
            // Ajouter des logs pour le débogage
            error_log("CSRF Check - Token reçu: " . ($token ? substr($token, 0, 10) . '...' : 'null'));
            error_log("CSRF Check - Session token: " . ($this->session->get('_csrf_token') ? substr($this->session->get('_csrf_token'), 0, 10) . '...' : 'null'));
            
            if (!$token || !$this->session->validateCsrfToken($token)) {
                // Réponse personnalisée en cas d'échec CSRF
                $response = new Response('Token CSRF invalide', 403);
                $response->headers->set('Content-Type', 'text/html');
                $content = '<h1>Erreur de sécurité</h1>';
                $content .= '<p>Le jeton de sécurité est invalide ou a expiré.</p>';
                $content .= '<p><a href="' . $request->getPathInfo() . '">Retour au formulaire</a></p>';
                $response->setContent($content);
                return $response;
            }
        }
        
        return $next($request);
    }
}