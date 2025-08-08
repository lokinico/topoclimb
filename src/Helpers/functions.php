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
    // Détection automatique du serveur local
    if (isset($_SERVER['SERVER_NAME']) && 
        ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') &&
        isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '8000') {
        
        $baseUrl = 'http://localhost:8000';
    } else {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    }
    
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
if (!function_exists('dd')) {
    function dd(mixed $value): void
    {
        if (env('APP_ENV') === 'development') {
            echo '<pre>';
            var_dump($value);
            echo '</pre>';
            die();
        }
    }
}

/**
 * Récupère l'instance Auth actuelle
 */
function auth(): ?\TopoclimbCH\Core\Auth
{
    try {
        $container = \TopoclimbCH\Core\Container::getInstance();
        if ($container && $container->has(\TopoclimbCH\Core\Auth::class)) {
            return $container->get(\TopoclimbCH\Core\Auth::class);
        }

        // Fallback : créer une nouvelle instance avec les dépendances du container
        if ($container && $container->has(\TopoclimbCH\Core\Session::class) && $container->has(\TopoclimbCH\Core\Database::class)) {
            $session = $container->get(\TopoclimbCH\Core\Session::class);
            $database = $container->get(\TopoclimbCH\Core\Database::class);
            return new \TopoclimbCH\Core\Auth($session, $database);
        }
        
        return null;
    } catch (\Exception $e) {
        error_log('Error getting Auth instance: ' . $e->getMessage());
        return null;
    }
}

/**
 * Récupère l'utilisateur authentifié
 */
function auth_user(): ?\TopoclimbCH\Models\User
{
    $auth = auth();
    return $auth && $auth->check() ? $auth->user() : null;
}

/**
 * Vérifie si l'utilisateur est authentifié
 */
function auth_check(): bool
{
    $auth = auth();
    return $auth ? $auth->check() : false;
}

/**
 * Vérifie si l'utilisateur a une permission
 */
function can(string $permission): bool
{
    $auth = auth();
    return $auth && $auth->check() ? $auth->user()->can($permission) : false;
}

/**
 * Génère l'URL d'un asset avec gestion du cache
 */
function asset(string $path): string
{
    // Nettoyer le chemin
    $path = ltrim($path, '/');

    // Détection automatique du serveur local
    if (isset($_SERVER['SERVER_NAME']) && 
        ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') &&
        isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '8000') {
        
        $baseUrl = 'http://localhost:8000';
    } else {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    }

    // En production, ajouter un hash pour le cache busting
    if (env('APP_ENV') === 'production') {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (file_exists($fullPath)) {
            $version = substr(md5_file($fullPath), 0, 8);
            $separator = strpos($path, '?') !== false ? '&' : '?';
            $path .= $separator . 'v=' . $version;
        }
        return $baseUrl . '/public/' . $path;
    }

    // En local, les assets sont servis directement
    return $baseUrl . '/' . $path;
}

/**
 * Vérifie si l'URL courante correspond au chemin donné
 */
function is_active(string $path): bool
{
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $currentPath = strtok($currentPath, '?'); // Enlever les paramètres GET

    // Normaliser les chemins
    $path = '/' . ltrim($path, '/');
    $currentPath = '/' . ltrim($currentPath, '/');

    // Comparaison exacte pour la racine
    if ($path === '/') {
        return $currentPath === '/';
    }

    // Comparaison avec début du chemin pour les autres
    return strpos($currentPath, $path) === 0;
}

/**
 * Génère une URL en retirant un paramètre de filtre
 */
function remove_filter_url(string $paramToRemove): string
{
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
    $urlParts = parse_url($currentUrl);

    // Parser les paramètres GET existants
    $params = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $params);
    }

    // Retirer le paramètre spécifié
    unset($params[$paramToRemove]);

    // Reconstruire l'URL
    $basePath = $urlParts['path'] ?? '/';

    if (!empty($params)) {
        return $basePath . '?' . http_build_query($params);
    }

    return $basePath;
}

/**
 * Génère l'URL de la page courante avec un paramètre ajouté/modifié
 */
function current_url_with(array $params): string
{
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
    $urlParts = parse_url($currentUrl);

    // Parser les paramètres GET existants
    $currentParams = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $currentParams);
    }

    // Fusionner avec les nouveaux paramètres
    $allParams = array_merge($currentParams, $params);

    // Construire l'URL
    $basePath = $urlParts['path'] ?? '/';

    if (!empty($allParams)) {
        return $basePath . '?' . http_build_query($allParams);
    }

    return $basePath;
}

/**
 * Formate une date en français
 */
function date_fr(string $date, string $format = 'd/m/Y'): string
{
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    $frenchMonths = [
        'January' => 'janvier',
        'February' => 'février',
        'March' => 'mars',
        'April' => 'avril',
        'May' => 'mai',
        'June' => 'juin',
        'July' => 'juillet',
        'August' => 'août',
        'September' => 'septembre',
        'October' => 'octobre',
        'November' => 'novembre',
        'December' => 'décembre'
    ];

    $frenchDays = [
        'Monday' => 'lundi',
        'Tuesday' => 'mardi',
        'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi',
        'Friday' => 'vendredi',
        'Saturday' => 'samedi',
        'Sunday' => 'dimanche'
    ];

    $formattedDate = date($format, $timestamp);

    // Remplacer les noms anglais par les français
    $formattedDate = str_replace(array_keys($frenchMonths), array_values($frenchMonths), $formattedDate);
    $formattedDate = str_replace(array_keys($frenchDays), array_values($frenchDays), $formattedDate);

    return $formattedDate;
}

/**
 * Formate un nombre avec les unités appropriées
 */
function number_format_fr(float $number, int $decimals = 0): string
{
    return number_format($number, $decimals, ',', ' ');
}

/**
 * Tronque un texte à une longueur donnée
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Génère un slug URL-friendly
 */
function slug(string $text): string
{
    // Remplacer les caractères accentués
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

    // Nettoyer et convertir
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', trim($text));
    $text = strtolower($text);

    return $text;
}

/**
 * Vérifie si une chaîne est un JSON valide
 */
function is_json(string $string): bool
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Récupère la valeur d'un tableau avec une clé en notation pointée
 */
function array_get(array $array, string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    foreach (explode('.', $key) as $segment) {
        if (is_array($array) && array_key_exists($segment, $array)) {
            $array = $array[$segment];
        } else {
            return $default;
        }
    }

    return $array;
}

/**
 * Génère un token CSRF
 */
function csrf_token(): string
{
    try {
        $container = \TopoclimbCH\Core\Container::getInstance();
        if ($container && $container->has(\TopoclimbCH\Core\Security\CsrfManager::class)) {
            $csrfManager = $container->get(\TopoclimbCH\Core\Security\CsrfManager::class);
            return $csrfManager->getToken();
        }
    } catch (\Exception $e) {
        error_log('Error getting CSRF token: ' . $e->getMessage());
    }

    // Fallback
    return bin2hex(random_bytes(16));
}

/**
 * Génère un champ hidden pour le token CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Debug pour l'environnement de développement
 */
function debug(mixed $data, bool $die = false): void
{
    if (env('APP_DEBUG', false)) {
        echo '<pre style="background: #000; color: #0f0; padding: 10px; margin: 10px; font-size: 12px;">';
        print_r($data);
        echo '</pre>';

        if ($die) {
            die();
        }
    }
}

/**
 * Log une erreur avec contexte
 */
function log_error(string $message, array $context = []): void
{
    $logMessage = $message;
    if (!empty($context)) {
        $logMessage .= ' | Context: ' . json_encode($context);
    }

    error_log($logMessage);
}

/**
 * Valide une adresse email
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Génère une couleur aléatoire en hexadécimal
 */
function random_color(): string
{
    return '#' . substr(md5(rand()), 0, 6);
}

/**
 * Convertit des octets en format lisible
 */
function bytes_to_human(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Vérifie si l'application est en mode maintenance
 */
function is_maintenance_mode(): bool
{
    return file_exists(__DIR__ . '/../../maintenance.flag');
}

/**
 * Récupère l'IP réelle du client
 */
function get_client_ip(): string
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
