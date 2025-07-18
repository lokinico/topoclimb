<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;
use TopoclimbCH\Services\GeolocationService;
use TopoclimbCH\Services\GeocodingService;
use TopoclimbCH\Services\WeatherService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class GeolocationController extends BaseController
{
    private GeolocationService $geolocationService;
    private GeocodingService $geocodingService;
    private WeatherService $weatherService;

    public function __construct(
        GeolocationService $geolocationService,
        GeocodingService $geocodingService,
        WeatherService $weatherService
    ) {
        $this->geolocationService = $geolocationService;
        $this->geocodingService = $geocodingService;
        $this->weatherService = $weatherService;
    }

    /**
     * API: Trouve les sites d'escalade les plus proches
     */
    public function apiNearestSites(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = (int) $request->query->get('radius', 50);
        $limit = (int) $request->query->get('limit', 10);

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$this->geolocationService->validateSwissCoordinates($lat, $lng)) {
            return new JsonResponse(['error' => 'Coordonnées invalides pour la Suisse'], 400);
        }

        try {
            $nearestSites = $this->geolocationService->findNearestSites($lat, $lng, $radius, $limit);
            
            $response = [
                'user_location' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'search_params' => [
                    'radius_km' => $radius,
                    'limit' => $limit
                ],
                'results' => array_map(function($item) {
                    return [
                        'site' => [
                            'id' => $item['site']->id,
                            'name' => $item['site']->name,
                            'latitude' => $item['site']->latitude,
                            'longitude' => $item['site']->longitude,
                            'region_name' => $item['site']->region_name ?? '',
                            'access_info' => $item['site']->access_info ?? '',
                            'elevation' => $item['site']->elevation ?? null
                        ],
                        'distance_km' => $item['distance'],
                        'travel_time' => $item['travel_time'],
                        'navigation_url' => "/geolocation/directions/{$item['site']->id}?lat={$lat}&lng={$lng}"
                    ];
                }, $nearestSites),
                'total_found' => count($nearestSites)
            ];

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Trouve les secteurs d'escalade les plus proches
     */
    public function apiNearestSectors(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = (int) $request->query->get('radius', 30);
        $limit = (int) $request->query->get('limit', 15);

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$this->geolocationService->validateSwissCoordinates($lat, $lng)) {
            return new JsonResponse(['error' => 'Coordonnées invalides pour la Suisse'], 400);
        }

        try {
            $nearestSectors = $this->geolocationService->findNearestSectors($lat, $lng, $radius, $limit);
            
            $response = [
                'user_location' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'search_params' => [
                    'radius_km' => $radius,
                    'limit' => $limit
                ],
                'results' => array_map(function($item) {
                    return [
                        'sector' => [
                            'id' => $item['sector']->id,
                            'name' => $item['sector']->name,
                            'latitude' => $item['sector']->latitude,
                            'longitude' => $item['sector']->longitude,
                            'site_name' => $item['site_name'],
                            'region_name' => $item['region_name'],
                            'routes_count' => $item['sector']->routes_count ?? 0
                        ],
                        'distance_km' => $item['distance'],
                        'travel_time' => $item['travel_time']
                    ];
                }, $nearestSectors),
                'total_found' => count($nearestSectors)
            ];

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Génère des directions vers un site
     */
    public function apiDirections(Request $request): JsonResponse
    {
        $siteId = (int) $request->attributes->get('id');
        $userLat = $request->query->get('lat');
        $userLng = $request->query->get('lng');

        try {
            $directions = $this->geolocationService->generateDirections(
                $siteId,
                $userLat ? floatval($userLat) : null,
                $userLng ? floatval($userLng) : null
            );

            return new JsonResponse($directions);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Géocode une adresse
     */
    public function apiGeocode(Request $request): JsonResponse
    {
        $address = $request->query->get('address');
        $useSwissAPI = $request->query->get('swiss', 'true') === 'true';

        if (!$address) {
            return new JsonResponse(['error' => 'Adresse requise'], 400);
        }

        try {
            $results = $this->geocodingService->geocodeAddress($address, $useSwissAPI);
            
            return new JsonResponse([
                'query' => $address,
                'api_used' => $useSwissAPI ? 'swisstopo' : 'nominatim',
                'results' => $results,
                'total_found' => count($results)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Géocodage inverse
     */
    public function apiReverseGeocode(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $useSwissAPI = $request->query->get('swiss', 'true') === 'true';

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        try {
            $results = $this->geocodingService->reverseGeocode(
                floatval($lat),
                floatval($lng),
                $useSwissAPI
            );
            
            return new JsonResponse([
                'coordinates' => [
                    'latitude' => floatval($lat),
                    'longitude' => floatval($lng)
                ],
                'api_used' => $useSwissAPI ? 'swisstopo' : 'nominatim',
                'results' => $results,
                'total_found' => count($results)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Convertit les coordonnées en coordonnées suisses
     */
    public function apiConvertToSwiss(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$this->geolocationService->validateSwissCoordinates($lat, $lng)) {
            return new JsonResponse(['error' => 'Coordonnées invalides pour la Suisse'], 400);
        }

        try {
            $swissCoords = $this->geolocationService->convertToSwissCoordinates($lat, $lng);
            
            return new JsonResponse([
                'input' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'system' => 'WGS84'
                ],
                'output' => [
                    'east' => $swissCoords['east'],
                    'north' => $swissCoords['north'],
                    'system' => 'CH1903+'
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Obtient la météo pour une position
     */
    public function apiWeatherByLocation(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$this->geolocationService->validateSwissCoordinates($lat, $lng)) {
            return new JsonResponse(['error' => 'Coordonnées invalides pour la Suisse'], 400);
        }

        try {
            $weather = $this->geolocationService->getWeatherForLocation($lat, $lng);
            
            return new JsonResponse([
                'location' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'weather' => $weather
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de géolocalisation pour les utilisateurs
     */
    public function index(Request $request): Response
    {
        return $this->render('geolocation/index.twig', [
            'page_title' => 'Géolocalisation - Sites d\'escalade proches',
            'user_position' => null
        ]);
    }

    /**
     * Page de navigation vers un site spécifique
     */
    public function directions(Request $request): Response
    {
        $siteId = (int) $request->attributes->get('id');
        $userLat = $request->query->get('lat');
        $userLng = $request->query->get('lng');

        try {
            $directions = $this->geolocationService->generateDirections(
                $siteId,
                $userLat ? floatval($userLat) : null,
                $userLng ? floatval($userLng) : null
            );

            return $this->render('geolocation/directions.twig', [
                'page_title' => 'Navigation vers ' . $directions['site']['name'],
                'directions' => $directions,
                'user_position' => [
                    'latitude' => $userLat,
                    'longitude' => $userLng
                ]
            ]);
        } catch (\Exception $e) {
            return $this->render('errors/500.twig', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * API: Trouve les points d'intérêt autour d'une position
     */
    public function apiNearbyPOIs(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = (int) $request->query->get('radius', 5000);

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Latitude et longitude requises'], 400);
        }

        $lat = floatval($lat);
        $lng = floatval($lng);

        if (!$this->geolocationService->validateSwissCoordinates($lat, $lng)) {
            return new JsonResponse(['error' => 'Coordonnées invalides pour la Suisse'], 400);
        }

        try {
            $pois = $this->geocodingService->findNearbyPOIs($lat, $lng, $radius);
            
            return new JsonResponse([
                'location' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'search_radius_meters' => $radius,
                'points_of_interest' => $pois,
                'total_found' => count($pois)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Recherche géographique générique
     */
    public function apiSearch(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            $type = $request->query->get('type', 'all'); // 'sites', 'sectors', 'addresses', 'all'
            $lat = $request->query->get('lat');
            $lng = $request->query->get('lng');
            $radius = (int) $request->query->get('radius', 50);
            $limit = min((int) $request->query->get('limit', 20), 100);

            if (empty($query) && !$lat && !$lng) {
                return new JsonResponse([
                    'error' => 'Requête de recherche ou coordonnées requises'
                ], 400);
            }

            $results = [];

            // Recherche textuelle
            if (!empty($query)) {
                // Recherche de sites d'escalade
                if ($type === 'sites' || $type === 'all') {
                    $sites = $this->geolocationService->searchSites($query, $limit);
                    $results['sites'] = $sites;
                }

                // Recherche de secteurs
                if ($type === 'sectors' || $type === 'all') {
                    $sectors = $this->geolocationService->searchSectors($query, $limit);
                    $results['sectors'] = $sectors;
                }

                // Recherche d'adresses via géocodage
                if ($type === 'addresses' || $type === 'all') {
                    try {
                        $addresses = $this->geocodingService->geocodeAddress($query, true);
                        $results['addresses'] = array_slice($addresses, 0, $limit);
                    } catch (\Exception $e) {
                        $results['addresses'] = [];
                    }
                }
            }

            // Recherche géographique par proximité
            if ($lat && $lng) {
                $lat = floatval($lat);
                $lng = floatval($lng);

                if ($this->geolocationService->validateSwissCoordinates($lat, $lng)) {
                    if ($type === 'sites' || $type === 'all') {
                        $nearSites = $this->geolocationService->findNearestSites($lat, $lng, $radius, $limit);
                        $results['nearby_sites'] = $nearSites;
                    }

                    if ($type === 'sectors' || $type === 'all') {
                        $nearSectors = $this->geolocationService->findNearestSectors($lat, $lng, $radius, $limit);
                        $results['nearby_sectors'] = $nearSectors;
                    }
                }
            }

            // Compter les résultats totaux
            $totalResults = 0;
            foreach ($results as $category) {
                if (is_array($category)) {
                    $totalResults += count($category);
                }
            }

            return new JsonResponse([
                'success' => true,
                'query' => $query,
                'search_type' => $type,
                'coordinates' => $lat && $lng ? ['lat' => $lat, 'lng' => $lng, 'radius_km' => $radius] : null,
                'results' => $results,
                'total_results' => $totalResults,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la recherche géographique'
            ], 500);
        }
    }
}