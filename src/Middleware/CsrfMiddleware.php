<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;

class CsrfMiddleware
{
    private CsrfManager $csrfManager;
    private Session $session;

    public function __construct(Session $session, CsrfManager $csrfManager)
    {
        $this->session = $session;
        $this->csrfManager = $csrfManager;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Valider le token CSRF pour toutes les requêtes non-GET
        if (!$this->csrfManager->validateRequest($request)) {
            $path = $request->getPathInfo();
            $method = $request->getMethod();
            $ip = $request->getClientIp();
            
            // Log de sécurité détaillé
            error_log("CsrfMiddleware: Tentative CSRF détectée - IP: $ip, Méthode: $method, Chemin: $path");
            
            // Ajouter des détails du token pour debug
            $submittedToken = $this->csrfManager->getTokenFromRequest($request);
            $sessionToken = $this->session->get('csrf_token');
            
            error_log("CsrfMiddleware: Token soumis: " . ($submittedToken ? substr($submittedToken, 0, 10) . '...' : 'NULL'));
            error_log("CsrfMiddleware: Token session: " . ($sessionToken ? substr($sessionToken, 0, 10) . '...' : 'NULL'));

            // Déterminer le type de réponse selon le contexte
            if ($this->isApiRequest($request)) {
                return Response::json([
                    'error' => 'Token CSRF invalide ou manquant',
                    'code' => 'CSRF_TOKEN_MISMATCH',
                    'message' => 'Votre session a expiré. Veuillez recharger la page.'
                ], 403);
            }

            // Pour les requêtes web normales
            $this->session->flash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            
            // Redirection sécurisée selon le contexte
            $referrer = $request->headers->get('referer');
            $redirectUrl = '/';
            
            if ($referrer && str_contains($referrer, $request->getHost())) {
                // Utiliser le referrer seulement s'il est du même domaine
                $redirectUrl = $referrer;
            } elseif (str_contains($path, '/admin')) {
                $redirectUrl = '/admin';
            } elseif (str_contains($path, '/profile')) {
                $redirectUrl = '/profile';
            }
            
            return Response::redirect($redirectUrl);
        }

        // Continuer avec la requête
        $response = $next($request);
        
        // Ajouter le token CSRF aux réponses HTML pour les requêtes GET
        if ($request->getMethod() === 'GET' && $this->isHtmlResponse($response)) {
            $this->injectCsrfToken($response);
        }

        return $response;
    }

    /**
     * Vérifier si c'est une requête API
     */
    private function isApiRequest(Request $request): bool
    {
        // Vérifier le chemin
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return true;
        }

        // Vérifier l'en-tête Accept
        $accept = $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Vérifier l'en-tête Content-Type
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        // Vérifier si c'est une requête AJAX
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * Vérifier si la réponse est HTML
     */
    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'text/html') || empty($contentType);
    }

    /**
     * Injecter le token CSRF dans les réponses HTML
     */
    private function injectCsrfToken(Response $response): void
    {
        $content = $response->getContent();
        
        if (empty($content) || !str_contains($content, '<head>')) {
            return;
        }

        // Ajouter la meta tag CSRF dans le head
        $metaTag = $this->csrfManager->getMetaTag();
        $content = str_replace('<head>', "<head>\n    $metaTag", $content);
        
        // Ajouter un script pour faciliter l'utilisation AJAX
        $script = $this->getCsrfScript();
        $content = str_replace('</head>', "    $script\n</head>", $content);
        
        $response->setContent($content);
    }

    /**
     * Générer le script JavaScript pour la gestion CSRF
     */
    private function getCsrfScript(): string
    {
        return '<script>
    // Configuration globale pour les requêtes AJAX avec CSRF
    (function() {
        const token = document.querySelector(\'meta[name="csrf-token"]\')?.getAttribute(\'content\');
        if (token) {
            // Configuration pour fetch()
            const originalFetch = window.fetch;
            window.fetch = function(input, init = {}) {
                init.headers = init.headers || {};
                if (init.method && ![\'GET\', \'HEAD\', \'OPTIONS\'].includes(init.method.toUpperCase())) {
                    init.headers[\'X-CSRF-TOKEN\'] = token;
                }
                return originalFetch(input, init);
            };
            
            // Configuration pour XMLHttpRequest
            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this._method = method.toUpperCase();
                return originalOpen.call(this, method, url, async, user, password);
            };
            
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(data) {
                if (this._method && ![\'GET\', \'HEAD\', \'OPTIONS\'].includes(this._method)) {
                    this.setRequestHeader(\'X-CSRF-TOKEN\', token);
                }
                return originalSend.call(this, data);
            };
            
            // Ajouter token aux formulaires sans token existant
            document.addEventListener(\'DOMContentLoaded\', function() {
                const forms = document.querySelectorAll(\'form:not([method="get"]):not([method="GET"])\');
                forms.forEach(form => {
                    if (!form.querySelector(\'input[name="csrf_token"], input[name="_csrf_token"], input[name="_token"]\')) {
                        const input = document.createElement(\'input\');
                        input.type = \'hidden\';
                        input.name = \'csrf_token\';
                        input.value = token;
                        form.appendChild(input);
                    }
                });
            });
        }
    })();
</script>';
    }
}