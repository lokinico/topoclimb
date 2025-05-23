<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;

class CsrfMiddleware
{
    private Session $session;
    private const CSRF_KEY = 'csrf_token';
    // Champs où chercher le token
    private const TOKEN_FIELD_NAMES = ['_csrf_token', 'csrf_token'];

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): SymfonyResponse
    {
        error_log("CsrfMiddleware: Traitement " . $request->getMethod() . " " . $request->getPathInfo());

        // Assurons-nous qu'un token CSRF existe
        $sessionToken = $this->ensureTokenExists();

        // Vérifier si cette route est exemptée
        if ($this->isExempted($request)) {
            error_log("CSRF: Route exemptée: " . $request->getPathInfo());
            return $next($request);
        }

        // Uniquement vérifier pour les méthodes non sûres
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            $submittedToken = $this->getSubmittedToken($request);

            error_log("CSRF: Validation - Token soumis: " .
                (isset($submittedToken) ? substr($submittedToken, 0, 10) . '...' : 'non trouvé') .
                " vs Token session: " . substr($sessionToken, 0, 10) . '...');

            if (empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
                error_log("CSRF: Validation échouée, redirection");
                $this->session->flash('error', 'Session expirée ou formulaire invalide. Veuillez réessayer.');
                return $this->createRedirectResponse($request->headers->get('referer') ?: '/');
            }

            error_log("CSRF: Validation réussie");

            // Stocker le token original pour pouvoir le récupérer si nécessaire
            $this->session->set('_original_csrf_token', $sessionToken);

            // Exécuter le pipeline
            $response = $next($request);

            // Après réussite, générer un nouveau token pour prévenir les attaques de replay
            if (!$this->isRedirect($response)) {
                $newToken = $this->generateToken();
                $this->session->set(self::CSRF_KEY, $newToken);
                error_log("CSRF: Nouveau token généré: " . substr($newToken, 0, 10) . '...');
            } else {
                // Pour les redirections, restaurer le token original si nécessaire
                $originalToken = $this->session->get('_original_csrf_token');
                if ($originalToken && $originalToken !== $this->session->get(self::CSRF_KEY)) {
                    $this->session->set(self::CSRF_KEY, $originalToken);
                    error_log("CSRF: Token restauré suite à redirection: " . substr($originalToken, 0, 10) . '...');
                }
            }

            // Nettoyage
            $this->session->remove('_original_csrf_token');
            return $response;
        }

        // Pour GET/HEAD, simplement continuer
        return $next($request);
    }

    /**
     * Vérifie si la requête peut être exemptée de vérification CSRF
     * Utile pour certaines routes comme /logout
     */
    private function isExempted(Request $request): bool
    {
        // Routes exemptées de vérification CSRF
        $exemptedRoutes = [
            '/logout'
        ];

        return in_array($request->getPathInfo(), $exemptedRoutes);
    }

    private function getSubmittedToken(Request $request): ?string
    {
        // Chercher dans les différents noms de champs possibles
        foreach (self::TOKEN_FIELD_NAMES as $fieldName) {
            $token = $request->request->get($fieldName);
            if (!empty($token)) {
                return $token;
            }
        }

        // Chercher dans les headers (pour API)
        return $request->headers->get('X-CSRF-TOKEN');
    }

    /**
     * CORRECTION: Accepter les deux types de Response
     */
    private function isRedirect($response): bool
    {
        // Gérer à la fois TopoclimbCH\Core\Response et Symfony\Component\HttpFoundation\Response
        if ($response instanceof Response) {
            // Notre propre classe Response
            $code = $response->getStatusCode();
        } elseif ($response instanceof SymfonyResponse) {
            // Classe Symfony Response
            $code = $response->getStatusCode();
        } else {
            // Type inconnu, considérer comme non-redirection
            error_log("CSRF: Type de response inconnu: " . get_class($response));
            return false;
        }

        return $code >= 300 && $code < 400;
    }

    /**
     * CORRECTION: Créer une réponse de redirection compatible
     */
    private function createRedirectResponse(string $url): SymfonyResponse
    {
        return new SymfonyResponse('', 302, ['Location' => $url]);
    }

    private function ensureTokenExists(): string
    {
        $token = $this->session->get(self::CSRF_KEY);

        if (empty($token)) {
            $token = $this->generateToken();
            $this->session->set(self::CSRF_KEY, $token);
            error_log("CSRF: Nouveau token créé: " . substr($token, 0, 10) . '...');
        }

        return $token;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
