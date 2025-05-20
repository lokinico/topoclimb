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
        error_log("PreserveCsrfTokenMiddleware: Classe initialisée le " . date('Y-m-d H:i:s'));
    }

    public function handle(Request $request, callable $next): Response
    {
        error_log("PreserveCsrfTokenMiddleware: Traitement de la requête pour URL = " . $request->getPathInfo());
        error_log("PreserveCsrfTokenMiddleware: Méthode HTTP = " . $request->getMethod());

        // Exceptions pour certaines routes
        if ($request->getPathInfo() === '/logout' || $this->isStaticAssetPath($request->getPathInfo())) {
            return $next($request);
        }

        // 1. IMPORTANT: Sauvegarder le token CSRF actuel
        $originalToken = $this->session->get('csrf_token');

        // 2. POUR LES REQUÊTES POST/PUT/DELETE: Valider le token avant de continuer
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $submittedToken = $request->request->get('_csrf_token');

            // Vérifier si le token soumis correspond au token en session
            if (!$submittedToken || $submittedToken !== $originalToken) {
                error_log("CSRF: Validation échouée - tokens différents");

                // Enregistrer un message flash pour informer l'utilisateur
                $this->session->flash('error', 'Session expirée ou invalidée. Veuillez réessayer.');

                // Rediriger vers la page précédente
                return new Response('', 302, ['Location' => $request->headers->get('referer') ?: '/']);
            }

            error_log("CSRF: Validation réussie");
        }

        // 3. Exécuter le pipeline de middleware SANS générer de nouveau token
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            error_log("PreserveCsrfTokenMiddleware: ERREUR - " . $e->getMessage());
            throw $e;
        }

        // 4. UNIQUEMENT APRÈS le traitement réussi, générer un nouveau token
        // pour la prochaine requête (mais pas pour les Ajax)
        if (!$request->isXmlHttpRequest() && $response->isSuccessful() && !$this->isRedirect($response)) {
            $newToken = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $newToken);
            error_log("CSRF: Nouveau token généré après traitement réussi: " . substr($newToken, 0, 10) . "...");
        }

        return $response;
    }

    private function isStaticAssetPath(string $path): bool
    {
        $staticExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.ttf'];
        foreach ($staticExtensions as $ext) {
            if (str_ends_with($path, $ext)) {
                return true;
            }
        }
        return false;
    }

    private function isRedirect(Response $response): bool
    {
        return $response->getStatusCode() >= 300 && $response->getStatusCode() < 400;
    }
}
