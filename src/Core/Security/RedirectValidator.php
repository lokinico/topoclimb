<?php

namespace TopoclimbCH\Core\Security;

class RedirectValidator
{
    private array $allowedDomains;
    private array $allowedPaths;
    private bool $strictMode;

    public function __construct(array $config = [])
    {
        // Configuration par défaut
        $this->allowedDomains = $config['allowed_domains'] ?? [
            'topoclimb.ch',
            'www.topoclimb.ch',
            'localhost',
            '127.0.0.1'
        ];
        
        $this->allowedPaths = $config['allowed_paths'] ?? [
            '/login',
            '/register', 
            '/dashboard',
            '/profile',
            '/admin',
            '/regions',
            '/sites',
            '/sectors',
            '/routes',
            '/books',
            '/events',
            '/forum'
        ];
        
        $this->strictMode = $config['strict_mode'] ?? true;
    }

    /**
     * Valider une URL de redirection
     */
    public function isValidRedirectUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Log de sécurité
        error_log("RedirectValidator: Validation URL de redirection: $url");

        // Nettoyer l'URL
        $url = trim($url);
        
        // Protections de base contre les attaques communes
        if ($this->containsMaliciousPatterns($url)) {
            error_log("RedirectValidator: URL refusée - patterns malicieux détectés: $url");
            return false;
        }

        // URL absolue
        if ($this->isAbsoluteUrl($url)) {
            return $this->validateAbsoluteUrl($url);
        }

        // URL relative
        return $this->validateRelativeUrl($url);
    }

    /**
     * Obtenir une URL de redirection sécurisée
     */
    public function getSafeRedirectUrl(?string $url, string $fallback = '/'): string
    {
        if ($this->isValidRedirectUrl($url)) {
            return $url;
        }

        error_log("RedirectValidator: URL invalide, fallback utilisé. Original: " . ($url ?? 'null') . ", Fallback: $fallback");
        return $fallback;
    }

    /**
     * Vérifier si l'URL contient des patterns malicieux
     */
    private function containsMaliciousPatterns(string $url): bool
    {
        $maliciousPatterns = [
            // JavaScript et data URLs
            '/^javascript:/i',
            '/^data:/i',
            '/^vbscript:/i',
            
            // Encodage d'URL suspect
            '/%2[fF]%2[fF]/',  // //
            '/%5[cC]/',        // \
            '/%2[eE]%2[eE]%2[fF]/', // ../
            
            // Protocoles suspects
            '/^(ftp|file|gopher|ldap|dict):/i',
            
            // Double slashes au début (protocol-relative)
            '/^\/\//',
            
            // Tentatives de contournement avec espaces ou caractères speciaux
            '/^\s*javascript:/i',
            '/javascript\s*:/i',
            
            // Unicode normalization attacks
            '/\u[0-9a-fA-F]{4}/',
            
            // Null bytes
            '/\x00/',
            
            // Tentatives d'injection
            '/<script/i',
            '/on\w+\s*=/i',
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier si l'URL est absolue
     */
    private function isAbsoluteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//', $url) === 1;
    }

    /**
     * Valider une URL absolue
     */
    private function validateAbsoluteUrl(string $url): bool
    {
        $parsedUrl = parse_url($url);
        
        if ($parsedUrl === false) {
            error_log("RedirectValidator: URL malformée: $url");
            return false;
        }

        // Vérifier le schéma
        if (!isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'])) {
            error_log("RedirectValidator: Schéma non autorisé: " . ($parsedUrl['scheme'] ?? 'null'));
            return false;
        }

        // Vérifier le domaine
        if (!isset($parsedUrl['host'])) {
            error_log("RedirectValidator: Host manquant dans l'URL: $url");
            return false;
        }

        $host = strtolower($parsedUrl['host']);
        
        // Vérifier contre la liste des domaines autorisés
        if (!in_array($host, $this->allowedDomains)) {
            // En mode strict, refuser tout domaine non listé
            if ($this->strictMode) {
                error_log("RedirectValidator: Domaine non autorisé (mode strict): $host");
                return false;
            }
            
            // En mode permissif, vérifier si c'est un sous-domaine autorisé
            $isSubdomainAllowed = false;
            foreach ($this->allowedDomains as $allowedDomain) {
                if (str_ends_with($host, '.' . $allowedDomain)) {
                    $isSubdomainAllowed = true;
                    break;
                }
            }
            
            if (!$isSubdomainAllowed) {
                error_log("RedirectValidator: Domaine non autorisé: $host");
                return false;
            }
        }

        // Vérifications supplémentaires de sécurité
        if (isset($parsedUrl['user']) || isset($parsedUrl['pass'])) {
            error_log("RedirectValidator: Credentials dans l'URL refusés: $url");
            return false;
        }

        // Vérifier le port (si spécifié)
        if (isset($parsedUrl['port'])) {
            $allowedPorts = [80, 443, 8080, 3000, 8000]; // Ports communs
            if (!in_array($parsedUrl['port'], $allowedPorts)) {
                error_log("RedirectValidator: Port non autorisé: " . $parsedUrl['port']);
                return false;
            }
        }

        return true;
    }

    /**
     * Valider une URL relative
     */
    private function validateRelativeUrl(string $url): bool
    {
        // L'URL ne doit pas commencer par // (protocol-relative)
        if (str_starts_with($url, '//')) {
            error_log("RedirectValidator: URL protocol-relative refusée: $url");
            return false;
        }

        // L'URL doit commencer par /
        if (!str_starts_with($url, '/')) {
            error_log("RedirectValidator: URL relative invalide (doit commencer par /): $url");
            return false;
        }

        // Décomposer l'URL
        $parsedUrl = parse_url($url);
        
        if ($parsedUrl === false) {
            error_log("RedirectValidator: URL relative malformée: $url");
            return false;
        }

        $path = $parsedUrl['path'] ?? '/';

        // Normaliser le chemin (résoudre les ../)
        $normalizedPath = $this->normalizePath($path);
        
        // Le chemin normalisé ne doit pas sortir de la racine
        if (!str_starts_with($normalizedPath, '/')) {
            error_log("RedirectValidator: Tentative de sortir de la racine: $path -> $normalizedPath");
            return false;
        }

        // En mode strict, vérifier contre la liste des chemins autorisés
        if ($this->strictMode) {
            $isPathAllowed = false;
            
            foreach ($this->allowedPaths as $allowedPath) {
                if ($normalizedPath === $allowedPath || str_starts_with($normalizedPath, $allowedPath . '/')) {
                    $isPathAllowed = true;
                    break;
                }
            }
            
            if (!$isPathAllowed) {
                error_log("RedirectValidator: Chemin non autorisé (mode strict): $normalizedPath");
                return false;
            }
        }

        // Vérifier les paramètres de requête et fragment
        if (isset($parsedUrl['query'])) {
            if (!$this->validateQueryString($parsedUrl['query'])) {
                return false;
            }
        }

        if (isset($parsedUrl['fragment'])) {
            if (!$this->validateFragment($parsedUrl['fragment'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normaliser un chemin (résoudre les ../ et ./)
     */
    private function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $normalized = [];
        
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            
            if ($part === '..') {
                if (count($normalized) > 0 && end($normalized) !== '..') {
                    array_pop($normalized);
                } else {
                    // Tentative de sortir de la racine
                    $normalized[] = '..';
                }
            } else {
                $normalized[] = $part;
            }
        }
        
        $result = '/' . implode('/', $normalized);
        
        // Préserver le slash final si présent dans l'original
        if (str_ends_with($path, '/') && !str_ends_with($result, '/')) {
            $result .= '/';
        }
        
        return $result;
    }

    /**
     * Valider la query string
     */
    private function validateQueryString(string $query): bool
    {
        // Vérifier la présence de patterns malicieux dans les paramètres
        if ($this->containsMaliciousPatterns($query)) {
            error_log("RedirectValidator: Query string malicieuse: $query");
            return false;
        }

        return true;
    }

    /**
     * Valider le fragment (hash)
     */
    private function validateFragment(string $fragment): bool
    {
        // Vérifier la présence de patterns malicieux dans le fragment
        if ($this->containsMaliciousPatterns($fragment)) {
            error_log("RedirectValidator: Fragment malicieux: $fragment");
            return false;
        }

        return true;
    }

    /**
     * Ajouter un domaine autorisé
     */
    public function addAllowedDomain(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->allowedDomains)) {
            $this->allowedDomains[] = $domain;
            error_log("RedirectValidator: Domaine ajouté à la whitelist: $domain");
        }
    }

    /**
     * Ajouter un chemin autorisé
     */
    public function addAllowedPath(string $path): void
    {
        $path = trim($path);
        if (!in_array($path, $this->allowedPaths)) {
            $this->allowedPaths[] = $path;
            error_log("RedirectValidator: Chemin ajouté à la whitelist: $path");
        }
    }

    /**
     * Obtenir les statistiques de validation
     */
    public function getStats(): array
    {
        return [
            'allowed_domains_count' => count($this->allowedDomains),
            'allowed_paths_count' => count($this->allowedPaths),
            'strict_mode' => $this->strictMode,
            'allowed_domains' => $this->allowedDomains,
            'allowed_paths' => $this->allowedPaths
        ];
    }
}