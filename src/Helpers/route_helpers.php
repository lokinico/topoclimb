<?php
// Helpers pour les routes (src/Helpers/route_helpers.php)

if (!function_exists('route')) {
    /**
     * Générer une URL pour une route nommée
     *
     * @param string $name
     * @param array $params
     * @param bool $absolute
     * @return string
     */
    function route(string $name, array $params = [], bool $absolute = false): string
    {
        global $app;
        return $app->getUrlGenerator()->generate($name, $params, $absolute);
    }
}

if (!function_exists('url')) {
    /**
     * Générer une URL absolue
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    function url(string $name, array $params = []): string
    {
        return route($name, $params, true);
    }
}

if (!function_exists('api_route')) {
    /**
     * Générer une URL d'API
     *
     * @param string $name
     * @param array $params
     * @param string $version
     * @return string
     */
    function api_route(string $name, array $params = [], string $version = 'v1'): string
    {
        global $app;
        return $app->getUrlGenerator()->api($name, $params, $version);
    }
}

if (!function_exists('admin_route')) {
    /**
     * Générer une URL d'administration
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    function admin_route(string $name, array $params = []): string
    {
        global $app;
        return $app->getUrlGenerator()->admin($name, $params);
    }
}

if (!function_exists('asset')) {
    /**
     * Générer une URL d'asset
     *
     * @param string $path
     * @param bool $absolute
     * @return string
     */
    function asset(string $path, bool $absolute = false): string
    {
        global $app;
        return $app->getUrlGenerator()->asset($path, $absolute);
    }
}

if (!function_exists('redirect')) {
    /**
     * Créer une réponse de redirection
     *
     * @param string $route
     * @param array $params
     * @param int $status
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function redirect(string $route, array $params = [], int $status = 302): \Symfony\Component\HttpFoundation\Response
    {
        $url = route($route, $params);
        return new \Symfony\Component\HttpFoundation\Response('', $status, ['Location' => $url]);
    }
}

if (!function_exists('back')) {
    /**
     * Rediriger vers la page précédente
     *
     * @param string $fallback
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function back(string $fallback = '/'): \Symfony\Component\HttpFoundation\Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return new \Symfony\Component\HttpFoundation\Response('', 302, ['Location' => $referer]);
    }
};
