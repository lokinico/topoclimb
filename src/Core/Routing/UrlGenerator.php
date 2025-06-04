<?php
// src/Core/Routing/UrlGenerator.php

namespace TopoclimbCH\Core\Routing;

use TopoclimbCH\Core\Router;

class UrlGenerator
{
    /**
     * @var Router
     */
    private Router $router;

    /**
     * Configuration pour la génération d'URLs
     *
     * @var array
     */
    private array $config;

    /**
     * Cache des URLs générées
     *
     * @var array
     */
    private array $urlCache = [];

    /**
     * UrlGenerator constructor
     *
     * @param Router $router
     * @param array $config
     */
    public function __construct(Router $router, array $config = [])
    {
        $this->router = $router;
        $this->config = array_merge([
            'base_url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'force_https' => $_ENV['APP_ENV'] === 'production',
            'default_domain' => $_ENV['APP_DOMAIN'] ?? 'localhost',
            'cache_urls' => true
        ], $config);
    }

    /**
     * Générer une URL pour une route nommée
     *
     * @param string $name Nom de la route
     * @param array $params Paramètres à injecter
     * @param bool $absolute Générer une URL absolue
     * @param string|null $domain Domaine spécifique
     * @return string
     * @throws \InvalidArgumentException
     */
    public function generate(string $name, array $params = [], bool $absolute = false, ?string $domain = null): string
    {
        // Vérifier le cache
        $cacheKey = $this->getCacheKey($name, $params, $absolute, $domain);
        if ($this->config['cache_urls'] && isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $namedRoutes = $this->router->getNamedRoutes();

        if (!isset($namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route named '{$name}' not found");
        }

        $route = $namedRoutes[$name];
        $path = $route['path'];

        // Remplacer les paramètres dans le chemin
        $url = $this->replacePlaceholders($path, $params);

        // Ajouter les paramètres de requête supplémentaires
        $queryParams = $this->getQueryParams($path, $params);
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        // Générer l'URL absolue si demandé
        if ($absolute) {
            $url = $this->makeAbsolute($url, $domain);
        }

        // Mettre en cache
        if ($this->config['cache_urls']) {
            $this->urlCache[$cacheKey] = $url;
        }

        return $url;
    }

    /**
     * Générer une URL absolue
     *
     * @param string $name
     * @param array $params
     * @param string|null $domain
     * @return string
     */
    public function absolute(string $name, array $params = [], ?string $domain = null): string
    {
        return $this->generate($name, $params, true, $domain);
    }

    /**
     * Générer une URL relative
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    public function relative(string $name, array $params = []): string
    {
        return $this->generate($name, $params, false);
    }

    /**
     * Générer une URL sécurisée (HTTPS)
     *
     * @param string $name
     * @param array $params
     * @param string|null $domain
     * @return string
     */
    public function secure(string $name, array $params = [], ?string $domain = null): string
    {
        $url = $this->generate($name, $params, true, $domain);

        // Forcer HTTPS
        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }

    /**
     * Générer une URL d'API avec versioning
     *
     * @param string $name
     * @param array $params
     * @param string $version
     * @param bool $absolute
     * @return string
     */
    public function api(string $name, array $params = [], string $version = 'v1', bool $absolute = true): string
    {
        // Préfixer le nom avec la version API
        $apiName = "api.{$version}.{$name}";

        return $this->generate($apiName, $params, $absolute);
    }

    /**
     * Générer une URL d'administration
     *
     * @param string $name
     * @param array $params
     * @param bool $absolute
     * @return string
     */
    public function admin(string $name, array $params = [], bool $absolute = false): string
    {
        $adminName = "admin.{$name}";
        return $this->generate($adminName, $params, $absolute);
    }

    /**
     * Générer une URL pour un média
     *
     * @param string $path
     * @param array $params
     * @param bool $absolute
     * @return string
     */
    public function media(string $path, array $params = [], bool $absolute = true): string
    {
        $url = '/media/' . ltrim($path, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        if ($absolute) {
            $url = $this->makeAbsolute($url);
        }

        return $url;
    }

    /**
     * Générer une URL pour un asset (CSS, JS, images)
     *
     * @param string $path
     * @param bool $absolute
     * @param string|null $version
     * @return string
     */
    public function asset(string $path, bool $absolute = false, ?string $version = null): string
    {
        $url = '/assets/' . ltrim($path, '/');

        // Ajouter un paramètre de version pour le cache busting
        if ($version) {
            $url .= '?v=' . $version;
        } elseif (defined('ASSET_VERSION')) {
            $url .= '?v=' . ASSET_VERSION;
        }

        if ($absolute) {
            $url = $this->makeAbsolute($url);
        }

        return $url;
    }

    /**
     * Générer une URL de pagination
     *
     * @param string $name
     * @param int $page
     * @param array $params
     * @return string
     */
    public function paginate(string $name, int $page, array $params = []): string
    {
        $params['page'] = $page;
        return $this->generate($name, $params);
    }

    /**
     * Générer une URL avec signature (pour URLs sécurisées temporaires)
     *
     * @param string $name
     * @param array $params
     * @param int $expires Timestamp d'expiration
     * @param string|null $key Clé de signature
     * @return string
     */
    public function signed(string $name, array $params = [], int $expires = 0, ?string $key = null): string
    {
        $key = $key ?: ($_ENV['APP_KEY'] ?? 'default-key');

        if ($expires === 0) {
            $expires = time() + 3600; // 1 heure par défaut
        }

        $params['expires'] = $expires;
        $url = $this->generate($name, $params);

        // Générer la signature
        $signature = hash_hmac('sha256', $url, $key);

        return $url . (str_contains($url, '?') ? '&' : '?') . 'signature=' . $signature;
    }

    /**
     * Vérifier si une URL signée est valide
     *
     * @param string $url
     * @param string|null $key
     * @return bool
     */
    public function verifySignature(string $url, ?string $key = null): bool
    {
        $key = $key ?: ($_ENV['APP_KEY'] ?? 'default-key');

        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $queryParams);

        if (!isset($queryParams['signature']) || !isset($queryParams['expires'])) {
            return false;
        }

        // Vérifier l'expiration
        if ($queryParams['expires'] < time()) {
            return false;
        }

        $signature = $queryParams['signature'];
        unset($queryParams['signature']);

        // Reconstruire l'URL sans la signature
        $urlWithoutSignature = $urlParts['path'] . '?' . http_build_query($queryParams);

        // Vérifier la signature
        $expectedSignature = hash_hmac('sha256', $urlWithoutSignature, $key);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Remplacer les placeholders dans le chemin
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    private function replacePlaceholders(string $path, array $params): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($params) {
            $placeholder = $matches[1];
            $isOptional = str_ends_with($placeholder, '?');

            if ($isOptional) {
                $placeholder = rtrim($placeholder, '?');
            }

            if (isset($params[$placeholder])) {
                return $params[$placeholder];
            }

            if ($isOptional) {
                return '';
            }

            throw new \InvalidArgumentException("Missing required parameter: {$placeholder}");
        }, $path);
    }

    /**
     * Obtenir les paramètres de requête supplémentaires
     *
     * @param string $path
     * @param array $params
     * @return array
     */
    private function getQueryParams(string $path, array $params): array
    {
        // Extraire les noms des placeholders du chemin
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        $pathParams = array_map(function ($param) {
            return rtrim($param, '?');
        }, $matches[1]);

        // Retourner les paramètres qui ne sont pas dans le chemin
        return array_diff_key($params, array_flip($pathParams));
    }

    /**
     * Convertir une URL relative en URL absolue
     *
     * @param string $url
     * @param string|null $domain
     * @return string
     */
    private function makeAbsolute(string $url, ?string $domain = null): string
    {
        $baseUrl = $this->config['base_url'];

        if ($domain) {
            $parsedBase = parse_url($baseUrl);
            $baseUrl = $parsedBase['scheme'] . '://' . $domain;

            if (isset($parsedBase['port'])) {
                $baseUrl .= ':' . $parsedBase['port'];
            }
        }

        // Forcer HTTPS si configuré
        if ($this->config['force_https'] && str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }

        return rtrim($baseUrl, '/') . $url;
    }

    /**
     * Générer une clé de cache pour l'URL
     *
     * @param string $name
     * @param array $params
     * @param bool $absolute
     * @param string|null $domain
     * @return string
     */
    private function getCacheKey(string $name, array $params, bool $absolute, ?string $domain): string
    {
        return md5($name . serialize($params) . ($absolute ? '1' : '0') . ($domain ?? ''));
    }

    /**
     * Vider le cache des URLs
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->urlCache = [];
    }

    /**
     * Obtenir des statistiques sur le cache des URLs
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        return [
            'cached_urls' => count($this->urlCache),
            'memory_usage' => strlen(serialize($this->urlCache))
        ];
    }

    /**
     * Configurer l'URL de base
     *
     * @param string $baseUrl
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->config['base_url'] = $baseUrl;
        $this->clearCache(); // Vider le cache car les URLs vont changer
    }

    /**
     * Obtenir l'URL de base
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->config['base_url'];
    }
}
