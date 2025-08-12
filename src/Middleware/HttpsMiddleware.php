<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HttpsMiddleware
{
    /**
     * Force HTTPS pour tous les formulaires et pages sensibles
     */
    public function handle(Request $request, callable $next): Response
    {
        // Vérifier si HTTPS est requis
        $forceHttps = env('FORCE_HTTPS', false);
        $sslRedirect = env('SSL_REDIRECT', false);
        
        // Détecter si la connexion est sécurisée
        $isSecure = $this->isSecureConnection($request);
        
        // Si HTTPS est requis et connexion pas sécurisée
        if ($forceHttps && !$isSecure) {
            // Pages qui nécessitent absolument HTTPS
            $requiresHttps = $this->requiresHttps($request);
            
            if ($requiresHttps && $sslRedirect) {
                // Rediriger vers HTTPS
                $httpsUrl = $this->buildHttpsUrl($request);
                return new RedirectResponse($httpsUrl, 301);
            }
        }
        
        // Continuer avec la requête
        $response = $next($request);
        
        // Ajouter headers de sécurité HTTPS
        if ($isSecure || $forceHttps) {
            $this->addSecurityHeaders($response);
        }
        
        return $response;
    }
    
    /**
     * Détecter si la connexion est sécurisée
     */
    private function isSecureConnection(Request $request): bool
    {
        // Vérifier HTTPS standard
        if ($request->isSecure()) {
            return true;
        }
        
        // Vérifier proxy/load balancer headers
        $forwardedProto = $request->headers->get('X-Forwarded-Proto');
        if ($forwardedProto === 'https') {
            return true;
        }
        
        // Vérifier autres headers communs
        $httpsHeaders = [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_SSL' => 'on',
            'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https',
            'HTTP_CF_VISITOR' => '{"scheme":"https"}'
        ];
        
        foreach ($httpsHeaders as $header => $value) {
            $serverValue = $_SERVER[$header] ?? '';
            if (stripos($serverValue, $value) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Vérifier si la page courante nécessite HTTPS
     */
    private function requiresHttps(Request $request): bool
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        
        // Toutes les soumissions de formulaires
        if ($method === 'POST') {
            return true;
        }
        
        // Pages de connexion et d'authentification
        $authPaths = ['/login', '/register', '/forgot-password', '/reset-password'];
        foreach ($authPaths as $authPath) {
            if (stripos($path, $authPath) === 0) {
                return true;
            }
        }
        
        // Pages d'administration
        if (stripos($path, '/admin') === 0) {
            return true;
        }
        
        // Formulaires de création/édition
        $formPaths = ['/create', '/edit', '/settings', '/profile'];
        foreach ($formPaths as $formPath) {
            if (stripos($path, $formPath) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Construire URL HTTPS
     */
    private function buildHttpsUrl(Request $request): string
    {
        $host = $request->getHost();
        $uri = $request->getRequestUri();
        
        // Utiliser APP_URL si configuré
        $appUrl = env('APP_URL');
        if ($appUrl && parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
            $httpsHost = parse_url($appUrl, PHP_URL_HOST);
            $httpsPort = parse_url($appUrl, PHP_URL_PORT);
            
            $url = 'https://' . $httpsHost;
            if ($httpsPort && $httpsPort != 443) {
                $url .= ':' . $httpsPort;
            }
            $url .= $uri;
            
            return $url;
        }
        
        // Fallback: même host avec HTTPS
        $httpsUrl = 'https://' . $host;
        if ($request->getPort() != 80 && $request->getPort() != 443) {
            // Convertir port HTTP vers HTTPS (exemple: 8000 -> 8443)
            $httpsPort = $request->getPort() + 443;
            $httpsUrl .= ':' . $httpsPort;
        }
        $httpsUrl .= $uri;
        
        return $httpsUrl;
    }
    
    /**
     * Ajouter headers de sécurité HTTPS
     */
    private function addSecurityHeaders(Response $response): void
    {
        // Strict Transport Security
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // Content Security Policy pour formulaires
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
               "style-src 'self' 'unsafe-inline' https:; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https:; " .
               "connect-src 'self' https:; " .
               "form-action 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Autres headers de sécurité
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Header pour indiquer que les formulaires sont sécurisés
        $response->headers->set('X-Forms-Secure', 'true');
    }
}