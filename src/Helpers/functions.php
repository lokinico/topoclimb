<?php
/**
 * Fichier contenant les fonctions utilitaires globales
 */

/**
 * Récupère une variable d'environnement avec une valeur par défaut.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

/**
 * Génère une URL complète à partir d'un chemin relatif.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    $path = ltrim($path, '/');
    
    return $baseUrl . ($path ? "/$path" : '');
}

/**
 * Redirige vers une URL spécifique.
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Échappe le HTML pour éviter les failles XSS.
 *
 * @param string $value
 * @return string
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Convertit un tableau en objet.
 *
 * @param array $array
 * @return object
 */
function arrayToObject(array $array): object
{
    return json_decode(json_encode($array));
}

/**
 * Génère une chaîne aléatoire.
 *
 * @param int $length
 * @return string
 */
function randomString(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Affiche un message de débogage (uniquement en développement).
 *
 * @param mixed $value
 * @return void
 */
function dd(mixed $value): void
{
    if (env('APP_ENV') === 'development') {
        echo '<pre>';
        var_dump($value);
        echo '</pre>';
        die();
    }
}

// Fonction d'authentification
if (!function_exists('auth')) {
    /**
     * Récupère le service d'authentification
     *
     * @return \TopoclimbCH\Services\AuthService
     */
    function auth()
    {
        return \TopoclimbCH\Core\Container::getInstance()->get(\TopoclimbCH\Services\AuthService::class);
    }
}