<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;

class PreserveCsrfTokenMiddleware
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): \Symfony\Component\HttpFoundation\Response
    {
        // Sauvegarder le token CSRF avant tout traitement
        $originalToken = $this->session->get('csrf_token');

        // Un flag pour indiquer si on a sauvegardé un token
        $hasOriginalToken = !empty($originalToken);

        if ($hasOriginalToken) {
            $this->session->set('_original_csrf_token', $originalToken);
            error_log("CSRF token sauvegardé: " . substr($originalToken, 0, 10) . "...");
        }

        // Exécuter le reste du pipeline de middleware et le contrôleur
        $response = $next($request);

        // Après tout traitement, restaurer le token original si nécessaire
        if ($hasOriginalToken) {
            $currentToken = $this->session->get('csrf_token');

            // Ne restaurer que si le token a changé
            if ($currentToken !== $originalToken) {
                $this->session->set('csrf_token', $originalToken);
                error_log("CSRF token restauré: " . substr($originalToken, 0, 10) . "...");
            }
        }

        return $response;
    }
}
