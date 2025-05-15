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
        // Générer un token CSRF pour toutes les requêtes
        if (!$this->session->has('_csrf_token')) {
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }

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

            // Vérification du token
            $sessionToken = $this->session->get('_csrf_token');

            if (!$token || !$sessionToken) {
                error_log("CSRF Échec: Token manquant (reçu: " . ($token ? 'oui' : 'non') .
                    ", session: " . ($sessionToken ? 'oui' : 'non') . ")");
                return $this->csrfFailureResponse($request);
            }

            if (!hash_equals($sessionToken, $token)) {
                error_log("CSRF Échec: Tokens ne correspondent pas");
                return $this->csrfFailureResponse($request);
            }

            error_log("CSRF: Validation réussie");

            // Régénérer un nouveau token après validation réussie
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }

        return $next($request);
    }

    private function csrfFailureResponse(Request $request): Response
    {
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
