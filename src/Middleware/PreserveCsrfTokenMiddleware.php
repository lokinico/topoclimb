<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;

class PreserveCsrfTokenMiddleware
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): Response
    {
        error_log("PreserveCsrfTokenMiddleware: Handling request path = " . $request->getPathInfo());

        // Ignorer la route de déconnexion pour éviter les conflits
        if ($request->getPathInfo() === '/logout') {
            error_log("PreserveCsrfTokenMiddleware: Bypassing for /logout");
            return $next($request);
        }

        // Sauvegarder le token CSRF avant tout traitement
        $originalToken = $this->session->get('csrf_token');
        $hasOriginalToken = !empty($originalToken);

        if ($hasOriginalToken) {
            // Garder une copie sécurisée du token
            $this->session->set('_original_csrf_token', $originalToken);
            error_log("CSRF token sauvegardé: " . substr($originalToken, 0, 10) . "...");
        }

        // Exécuter le pipeline de middleware
        $response = $next($request);

        // Restaurer le token si nécessaire
        if ($hasOriginalToken) {
            $currentToken = $this->session->get('csrf_token');

            if ($currentToken !== $originalToken) {
                $this->session->set('csrf_token', $originalToken);
                error_log("CSRF token restauré: " . substr($originalToken, 0, 10) . "...");
            }
        }

        // S'assurer que les changements sont persistés
        $this->session->persist();

        return $response;
    }
}
