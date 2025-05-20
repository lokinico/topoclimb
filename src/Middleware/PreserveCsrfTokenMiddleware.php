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
        // Ignorer les requêtes pour les ressources statiques
        if ($this->isStaticAssetPath($request->getPathInfo()) || $request->getPathInfo() === '/logout') {
            return $next($request);
        }

        // Pour les requêtes POST/PUT/DELETE, effectuer une validation CSRF complète
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $submittedToken = $request->request->get('_csrf_token');
            $storedToken = $this->session->get('csrf_token');

            // Sauvegarder le token original pour référence
            $this->session->set('_original_csrf_token', $storedToken);

            // Indiquer qu'une validation est en cours pour éviter les doubles validations
            $this->session->set('csrf_validation_in_progress', true);

            // Vérifier le token (validation principale)
            if (!$submittedToken || !$storedToken || !hash_equals($storedToken, $submittedToken)) {
                $this->session->remove('csrf_validation_in_progress');
                $this->session->flash('error', 'Session expirée ou invalidée. Veuillez réessayer.');
                return new Response('', 302, ['Location' => $request->headers->get('referer') ?: '/']);
            }
        }

        try {
            // Exécuter le pipeline de middleware
            $response = $next($request);

            // Nettoyer les drapeaux de validation
            $this->session->remove('csrf_validation_in_progress');

            // Si la requête est réussie et n'est pas une redirection, générer un nouveau token pour la prochaine requête
            if ($response->isSuccessful() && !$this->isRedirect($response)) {
                $this->generateNewToken();
            } else {
                // Sinon, synchroniser les tokens pour s'assurer que nous avons le bon
                $this->session->synchronizeTokens();
            }

            return $response;
        } catch (\Throwable $e) {
            // Nettoyer en cas d'erreur
            $this->session->remove('csrf_validation_in_progress');
            throw $e;
        }
    }

    private function generateNewToken(): void
    {
        $newToken = bin2hex(random_bytes(32));
        $this->session->set('csrf_token', $newToken);
        error_log("CSRF: Nouveau token généré: " . substr($newToken, 0, 10) . "...");
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
