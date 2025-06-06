<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Services\CountryService;
use TopoclimbCH\Services\WeatherService;
use TopoclimbCH\Services\AuthService; // ← CORRECTION: Import correct
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Validation\Validator;

class RegionController extends BaseController
{
    private const SWISS_BOUNDS = [
        'lat_min' => 45.8,
        'lat_max' => 47.9,
        'lng_min' => 5.9,
        'lng_max' => 10.6
    ];

    private const VALIDATION_RULES = [
        'country_id' => 'required|numeric',
        'name' => 'required|min:2|max:100',
        'description' => 'nullable|max:2000',
        'coordinates_lat' => 'nullable|numeric|between:-90,90',
        'coordinates_lng' => 'nullable|numeric|between:-180,180',
        'altitude' => 'nullable|numeric|min:0|max:5000',
        'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
        'access_info' => 'nullable|max:1000',
        'parking_info' => 'nullable|max:1000'
    ];

    protected RegionService $regionService;
    protected MediaService $mediaService;
    protected WeatherService $weatherService;
    protected Database $db;
    protected ?Auth $auth;
    protected ?AuthService $authService;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        MediaService $mediaService,
        WeatherService $weatherService,
        Database $db,
        ?Auth $auth = null,
        ?AuthService $authService = null // ← CORRECTION: Type hint correct maintenant
    ) {
        parent::__construct($view, $session, $csrfManager);

        $this->regionService = $regionService;
        $this->mediaService = $mediaService;
        $this->weatherService = $weatherService;
        $this->db = $db;
        $this->auth = $auth ?? Auth::getInstance();
        $this->authService = $authService;
    }

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
        
        // Fallback : essayer d'obtenir Auth via getInstance
        return \TopoclimbCH\Core\Auth::getInstance();
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
    
    // Construire l'URL de base
    $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
    
    // En production, ajouter un hash pour le cache busting
    if (env('APP_ENV') === 'production') {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (file_exists($fullPath)) {
            $version = substr(md5_file($fullPath), 0, 8);
            $separator = strpos($path, '?') !== false ? '&' : '?';
            $path .= $separator . 'v=' . $version;
        }
    }
    
    return $baseUrl . '/public/' . $path;
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
        'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
        'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
        'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
        'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
    ];
    
    $frenchDays = [
        'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi',
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

    /**
     * Display detailed region page with all related data
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int) $request->attributes->get('id');
            if (!$id) {
                throw new \InvalidArgumentException('ID invalide');
            }

            $region = $this->regionService->getRegionWithAllRelations($id);
            if (!$region) {
                throw new \RuntimeException('Région non trouvée');
            }

            if (!$region) {
                $this->flash('error', 'Région non trouvée');
                return $this->redirect('/regions');
            }

            // Get sectors with enhanced data
            $sectors = $this->regionService->getRegionSectorsWithStats($id);

            // Get comprehensive statistics
            $stats = $this->regionService->getRegionDetailedStatistics($id);

            // Get photos and media
            $photos = $this->mediaService->getRegionMedia($id, 'gallery');
            $coverImage = $this->mediaService->getRegionCoverImage($id);

            // Get upcoming events in this region
            $upcomingEvents = $this->regionService->getUpcomingEvents($id);

            // Get access information and parking
            $parkingAreas = $this->regionService->getRegionParking($id);

            // Get weather data if coordinates are available
            $weatherData = null;
            if ($region->coordinates_lat && $region->coordinates_lng) {
                try {
                    $weatherData = $this->weatherService->getCurrentWeather(
                        $region->coordinates_lat,
                        $region->coordinates_lng
                    );
                } catch (\Exception $e) {
                    // Weather data is optional, continue without it
                    error_log("Weather API error: " . $e->getMessage());
                }
            }

            // Get related regions (same country, similar characteristics)
            $relatedRegions = $this->regionService->getRelatedRegions($region, 4);

            return $this->render('regions/show', [
                'region' => $region,
                'sectors' => $sectors,
                'stats' => $stats,
                'photos' => $photos,
                'coverImage' => $coverImage,
                'upcomingEvents' => $upcomingEvents,
                'parkingAreas' => $parkingAreas,
                'weatherData' => $weatherData,
                'relatedRegions' => $relatedRegions,
                'title' => $region->name
            ]);
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/regions');
        }
    }

    /**
     * Show create region form
     */
    public function create(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $countries = $this->regionService->getActiveCountries();

        return $this->render('regions/form', [
            'region' => null,
            'countries' => $countries,
            'title' => 'Créer une nouvelle région',
            'csrf_token' => $this->createCsrfToken(),
            'isEditing' => false
        ]);
    }

    /**
     * Store new region with file uploads and validation
     */
    public function store(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        $validator = new Validator();
        $data = $request->request->all();

        // Validate input data
        $rules = [
            'country_id' => 'required|numeric',
            'name' => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'coordinates_lat' => 'nullable|numeric|between:-90,90',
            'coordinates_lng' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|numeric|min:0|max:5000',
            'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
            'access_info' => 'nullable|max:1000',
            'parking_info' => 'nullable|max:1000'
        ];

        if (!$validator->validate($data, $rules)) {
            return $this->jsonError('Données invalides', 400, [
                'errors' => $validator->getErrors()
            ]);
        }

        try {
            $this->db->beginTransaction();

            // Add user context
            if ($this->auth && $this->auth->check()) {
                $data['created_by'] = $this->auth->id();
            }

            // Create region
            $region = $this->regionService->createRegion($data);

            // Handle file uploads
            $this->handleFileUploads($request, $region);

            // Handle coordinates validation
            $this->validateCoordinates($data);

            $this->db->commit();

            // Clear any existing drafts
            $this->clearUserDraft($request, 'region_create');

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région créée avec succès',
                'region' => $region->toArray(),
                'redirect' => "/regions/{$region->id}"
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region creation error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la création: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Show edit region form
     */
    public function edit(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegionWithAllRelations($id);

        if (!$region) {
            $this->flash('error', 'Région non trouvée');
            return $this->redirect('/regions');
        }

        $countries = $this->regionService->getActiveCountries();

        return $this->render('regions/form', [
            'region' => $region,
            'countries' => $countries,
            'title' => 'Modifier la région : ' . $region->name,
            'csrf_token' => $this->createCsrfToken(),
            'isEditing' => true
        ]);
    }

    /**
     * Update existing region
     */
    public function update(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->flash('error', 'Token CSRF invalide');
            return $this->redirect('/regions');
        }
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        $validator = new Validator();
        $data = $request->request->all();

        // Same validation rules as create
        $rules = [
            'country_id' => 'required|numeric',
            'name' => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'coordinates_lat' => 'nullable|numeric|between:-90,90',
            'coordinates_lng' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|numeric|min:0|max:5000',
            'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
            'access_info' => 'nullable|max:1000',
            'parking_info' => 'nullable|max:1000'
        ];

        if (!$validator->validate($data, $rules)) {
            return $this->jsonError('Données invalides', 400, [
                'errors' => $validator->getErrors()
            ]);
        }

        try {
            $this->db->beginTransaction();

            // Add user context
            if ($this->auth && $this->auth->check()) {
                $data['updated_by'] = $this->auth->id();
            }

            // Update region
            $region = $this->regionService->updateRegion($region, $data);

            // Handle file uploads
            $this->handleFileUploads($request, $region);

            $this->db->commit();

            // Clear draft
            $this->clearUserDraft($request, "region_edit_{$id}");

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région mise à jour avec succès',
                'region' => $region->toArray(),
                'redirect' => "/regions/{$region->id}"
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region update error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la mise à jour: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete region and all related data
     */
    public function destroy(Request $request): Response
    {
        $this->requirePermission('manage-climbing-data');

        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        if (!$this->validateCsrfToken($request)) {
            return $this->jsonError('Token de sécurité invalide', 403);
        }

        try {
            $this->db->beginTransaction();

            // Check if region has dependent data
            $dependencies = $this->regionService->checkDependencies($id);

            if (!empty($dependencies['sectors']) || !empty($dependencies['routes'])) {
                return $this->jsonError(
                    'Impossible de supprimer cette région car elle contient des secteurs et des voies. ' .
                        'Veuillez d\'abord supprimer ou déplacer le contenu.',
                    400,
                    ['dependencies' => $dependencies]
                );
            }

            // Delete associated media files
            $this->mediaService->deleteRegionMedia($id);

            // Delete region
            $this->regionService->deleteRegion($region);

            $this->db->commit();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Région supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Region deletion error: " . $e->getMessage());

            return $this->jsonError(
                'Erreur lors de la suppression: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get regions data for AJAX requests (map, autocomplete, etc.)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $filters = [
            'country_id' => $request->query->get('country_id'),
            'search' => $request->query->get('search'),
            'limit' => min((int) $request->query->get('limit', 50), 100)
        ];

        $regions = $this->regionService->getRegionsForApi($filters);

        return $this->jsonResponse([
            'regions' => $regions,
            'total' => count($regions)
        ]);
    }

    /**
     * Get weather data for a specific region
     */
    public function weather(Request $request): JsonResponse
    {
        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region || !$region->coordinates_lat || !$region->coordinates_lng) {
            return $this->jsonError('Région ou coordonnées non trouvées', 404);
        }

        try {
            $weatherData = $this->weatherService->getDetailedWeather(
                $region->coordinates_lat,
                $region->coordinates_lng
            );

            return $this->jsonResponse([
                'current' => $weatherData['current'],
                'forecast' => $weatherData['forecast'],
                'climbing_conditions' => $weatherData['climbing_conditions']
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Données météo indisponibles', 503);
        }
    }

    /**
     * Get upcoming events for a region
     */
    public function events(Request $request): JsonResponse
    {
        $id = (int) $request->attributes->get('id');
        $region = $this->regionService->getRegion($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        $events = $this->regionService->getUpcomingEvents($id, 10);

        return $this->jsonResponse([
            'events' => $events
        ]);
    }

    /**
     * Search regions for autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = min((int) $request->query->get('limit', 10), 20);

        if (strlen($query) < 2) {
            return $this->jsonResponse(['results' => []]);
        }

        $results = $this->regionService->searchRegions($query, $limit);

        return $this->jsonResponse([
            'results' => $results
        ]);
    }

    /**
     * Export region data (GPX, KML, etc.)
     */
    public function export(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        $format = $request->query->get('format', 'json');

        $region = $this->regionService->getRegionWithAllRelations($id);

        if (!$region) {
            return $this->jsonError('Région non trouvée', 404);
        }

        try {
            switch ($format) {
                case 'gpx':
                    return $this->exportGpx($region);
                case 'kml':
                    return $this->exportKml($region);
                case 'geojson':
                    return $this->exportGeoJson($region);
                default:
                    return $this->exportJson($region);
            }
        } catch (\Exception $e) {
            return $this->jsonError('Erreur lors de l\'export: ' . $e->getMessage(), 500);
        }
    }

    // Helper methods

    protected function buildPageTitle(array $filters): string
    {
        $title = 'Régions d\'escalade';

        if (!empty($filters['country_id'])) {
            $country = $this->db->fetchOne(
                "SELECT name FROM climbing_countries WHERE id = ?",
                [$filters['country_id']]
            );
            if ($country) {
                $title = 'Régions de ' . $country['name'];
            }
        }

        if (!empty($filters['search'])) {
            $title .= ' - Recherche: ' . $filters['search'];
        }

        return $title;
    }

    protected function handleFileUploads(Request $request, Region $region): void
    {
        // Handle cover image
        $coverFile = $request->files->get('cover_image');
        if ($coverFile && $coverFile->isValid()) {
            $this->mediaService->uploadRegionCoverImage($region->id, $coverFile);
        }

        // Handle gallery images
        $galleryFiles = $request->files->get('gallery_images', []);
        foreach ($galleryFiles as $file) {
            if ($file && $file->isValid()) {
                $this->mediaService->uploadRegionGalleryImage($region->id, $file);
            }
        }
    }

    protected function validateCoordinates(array $data): void
    {
        if (empty($data['coordinates_lat']) && empty($data['coordinates_lng'])) {
            return;
        }

        if (!is_numeric($data['coordinates_lat']) || !is_numeric($data['coordinates_lng'])) {
            throw new \InvalidArgumentException('Les coordonnées doivent être numériques');
        }

        $lat = $data['coordinates_lat'] ?? null;
        $lng = $data['coordinates_lng'] ?? null;

        // Both must be provided or both must be empty
        if (($lat && !$lng) || (!$lat && $lng)) {
            throw new \InvalidArgumentException(
                'Les coordonnées latitude et longitude doivent être fournies ensemble'
            );
        }

        // If provided, they must be within Switzerland bounds (approximately)
        if ($lat && $lng) {
            $swissBounds = [
                'lat_min' => 45.8,
                'lat_max' => 47.9,
                'lng_min' => 5.9,
                'lng_max' => 10.6
            ];

            if (
                $lat < $swissBounds['lat_min'] || $lat > $swissBounds['lat_max'] ||
                $lng < $swissBounds['lng_min'] || $lng > $swissBounds['lng_max']
            ) {

                // Log warning but don't fail - coordinates might be valid for regions outside Switzerland
                error_log("Coordinates outside Switzerland bounds: lat={$lat}, lng={$lng}");
            }
        }
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->auth || !$this->auth->check()) {
            throw new \RuntimeException('Authentification requise', 401);
        }

        if (!$this->auth->user()->can($permission)) {
            throw new \RuntimeException('Permissions insuffisantes', 403);
        }
    }

    protected function clearUserDraft(Request $request, string $draftKey): void
    {
        // This would typically clear localStorage via JavaScript
        // For now, we'll just note it for the frontend
    }

    protected function jsonResponse(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            ...$extra
        ], $status);
    }

    // Export methods

    protected function exportGpx(Region $region): Response
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $gpx = $this->regionService->generateGpxExport($region, $sectors);

        return new Response($gpx, 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.gpx"'
        ]);
    }

    protected function exportKml(Region $region): Response
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $kml = $this->regionService->generateKmlExport($region, $sectors);

        return new Response($kml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.kml"'
        ]);
    }

    protected function exportGeoJson(Region $region): JsonResponse
    {
        $sectors = $this->regionService->getRegionSectorsWithCoordinates($region->id);

        $geoJson = $this->regionService->generateGeoJsonExport($region, $sectors);

        return new JsonResponse($geoJson, 200, [
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.geojson"'
        ]);
    }

    protected function exportJson(Region $region): JsonResponse
    {
        $data = $this->regionService->getRegionExportData($region->id);

        return new JsonResponse($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $region->name . '.json"'
        ]);
    }
}
