<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;

class CsrfMiddleware
{
    private Session $session;

    // Clé constante pour stocker le token CSRF dans la session
    private const CSRF_KEY = '_csrf_token';

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Assurons-nous qu'un token CSRF existe toujours
        $this->ensureTokenExists();

        // Vérifie uniquement les méthodes non sécurisées (POST, PUT, DELETE, PATCH)
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            // Vérifie le token CSRF
            $valid = $this->validateRequest($request);

            if (!$valid) {
                return $this->csrfFailureResponse($request);
            }

            // Régénère un nouveau token UNIQUEMENT après une validation réussie
            $newToken = $this->regenerateToken();
            error_log("CSRF: Nouveau token généré après validation: " . substr($newToken, 0, 10) . "...");
        }

        return $next($request);
    }

    private function ensureTokenExists(): string
    {
        $token = $this->session->get(self::CSRF_KEY);

        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::CSRF_KEY, $token);
            error_log("CSRF Token généré: " . substr($token, 0, 10) . "...");
        }

        return $token;
    }

    private function validateRequest(Request $request): bool
    {
        // Récupérer le token de la requête
        $token = $request->request->get(self::CSRF_KEY) ??
            $request->request->get('_csrf') ??
            $request->headers->get('X-CSRF-TOKEN');

        // Récupérer le token de la session
        $sessionToken = $this->session->get(self::CSRF_KEY);

        // Log pour le débogage
        error_log("CSRF Check - Token reçu: " . ($token ? substr($token, 0, 10) . '...' : 'null'));
        error_log("CSRF Check - Session token: " . ($sessionToken ? substr($sessionToken, 0, 10) . '...' : 'null'));

        // Validation
        if (!$token || !$sessionToken) {
            error_log("CSRF Échec: Token manquant");
            return false;
        }

        $valid = hash_equals($sessionToken, $token);

        if ($valid) {
            error_log("CSRF: Validation réussie");
        } else {
            error_log("CSRF Échec: Tokens ne correspondent pas");
        }

        return $valid;
    }

    private function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::CSRF_KEY, $token);
        return $token;
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
