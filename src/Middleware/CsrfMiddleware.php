<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;

/**
 * Middleware CSRF simplifié utilisant CsrfManager
 */
class CsrfMiddleware
{
    private CsrfManager $csrfManager;
    private Session $session;

    public function __construct(CsrfManager $csrfManager, Session $session)
    {
        $this->csrfManager = $csrfManager;
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): SymfonyResponse
    {
        error_log("CsrfMiddleware: Traitement " . $request->getMethod() . " " . $request->getPathInfo());

        // S'assurer qu'un token existe pour les futures utilisations
        $this->csrfManager->getToken();

        // Valider la requête en utilisant CsrfManager
        if (!$this->csrfManager->validateRequest($request)) {
            error_log("CSRF: Validation échouée, redirection vers referer");
            $this->session->flash('error', 'Session expirée ou formulaire invalide. Veuillez réessayer.');
            return $this->createRedirectResponse($request->headers->get('referer') ?: '/');
        }

        // Exécuter le pipeline
        $response = $next($request);

        // Après une validation réussie sur une méthode non-sûre, 
        // régénérer le token pour prévenir les attaques de replay
        if (!$this->isGetRequest($request) && !$this->isRedirect($response)) {
            $newToken = $this->csrfManager->regenerateToken();
            error_log("CSRF: Token régénéré après validation réussie: " . substr($newToken, 0, 10) . '...');
        }

        return $response;
    }

    /**
     * Vérifie si c'est une requête GET/HEAD/OPTIONS
     */
    private function isGetRequest(Request $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Vérifie si la réponse est une redirection
     */
    private function isRedirect($response): bool
    {
        if ($response instanceof Response) {
            $code = $response->getStatusCode();
        } elseif ($response instanceof SymfonyResponse) {
            $code = $response->getStatusCode();
        } else {
            error_log("CSRF: Type de response inconnu: " . get_class($response));
            return false;
        }

        return $code >= 300 && $code < 400;
    }

    /**
     * Crée une réponse de redirection
     */
    private function createRedirectResponse(string $url): SymfonyResponse
    {
        return new SymfonyResponse('', 302, ['Location' => $url]);
    }
}
