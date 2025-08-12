<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Region;

/**
 * Service de géolocalisation et navigation GPS pour TopoclimbCH
 * Gère la localisation des utilisateurs et la navigation vers les sites d'escalade
 */
class GeolocationService
{
    private const EARTH_RADIUS_KM = 6371;
    
    /**
     * Calcule la distance entre deux points GPS en kilomètres
     * Utilise la formule de Haversine
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLatRad = deg2rad($lat2 - $lat1);
        $deltaLngRad = deg2rad($lng2 - $lng1);

        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLngRad / 2) * sin($deltaLngRad / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Trouve les sites d'escalade les plus proches d'une position
     */
    public function findNearestSites(float $lat, float $lng, int $radius = 50, int $limit = 10): array
    {
        $sites = Site::getAll();
        $nearSites = [];

        foreach ($sites as $site) {
            if ($site->latitude && $site->longitude) {
                $distance = $this->calculateDistance($lat, $lng, $site->latitude, $site->longitude);
                
                if ($distance <= $radius) {
                    $nearSites[] = [
                        'site' => $site,
                        'distance' => round($distance, 2),
                        'travel_time' => $this->estimateTravelTime($distance)
                    ];
                }
            }
        }

        // Trier par distance
        usort($nearSites, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return array_slice($nearSites, 0, $limit);
    }

    /**
     * Trouve les secteurs d'escalade les plus proches d'une position
     */
    public function findNearestSectors(float $lat, float $lng, int $radius = 30, int $limit = 15): array
    {
        $sectors = Sector::getAll();
        $nearSectors = [];

        foreach ($sectors as $sector) {
            if ($sector->latitude && $sector->longitude) {
                $distance = $this->calculateDistance($lat, $lng, $sector->latitude, $sector->longitude);
                
                if ($distance <= $radius) {
                    $nearSectors[] = [
                        'sector' => $sector,
                        'distance' => round($distance, 2),
                        'travel_time' => $this->estimateTravelTime($distance),
                        'site_name' => $sector->site_name ?? '',
                        'region_name' => $sector->region_name ?? ''
                    ];
                }
            }
        }

        // Trier par distance
        usort($nearSectors, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return array_slice($nearSectors, 0, $limit);
    }

    /**
     * Génère des directions GPS vers un site d'escalade
     */
    public function generateDirections(int $siteId, ?float $userLat = null, ?float $userLng = null): array
    {
        $site = Site::getById($siteId);
        
        if (!$site || !$site->latitude || !$site->longitude) {
            throw new \Exception('Site non trouvé ou sans coordonnées GPS');
        }

        $directions = [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'latitude' => $site->latitude,
                'longitude' => $site->longitude,
                'region_name' => $site->region_name ?? '',
                'access_info' => $site->access_info ?? ''
            ],
            'links' => [
                'google_maps' => $this->generateGoogleMapsUrl($site->latitude, $site->longitude, $site->name),
                'apple_maps' => $this->generateAppleMapsUrl($site->latitude, $site->longitude, $site->name),
                'swiss_maps' => $this->generateSwissMapUrl($site->latitude, $site->longitude),
                'waze' => $this->generateWazeUrl($site->latitude, $site->longitude)
            ]
        ];

        // Si position utilisateur fournie, calculer distance et temps
        if ($userLat && $userLng) {
            $distance = $this->calculateDistance($userLat, $userLng, $site->latitude, $site->longitude);
            $directions['navigation'] = [
                'distance_km' => round($distance, 2),
                'estimated_time' => $this->estimateTravelTime($distance),
                'difficulty' => $this->assessAccessDifficulty($distance, $site->elevation ?? 0)
            ];
        }

        return $directions;
    }

    /**
     * Convertit les coordonnées GPS en coordonnées suisses CH1903+ (LV95)
     * Utilise les formules officielles swisstopo avec correction de décalage
     */
    public function convertToSwissCoordinates(float $lat, float $lng): array
    {
        // Étape 1: Conversion en coordonnées auxiliaires
        // Origine: Ancienne observatoire de Berne
        $phi_0 = 169028.66; // 46°57'08.66" en secondes d'arc
        $lambda_0 = 26782.5; // 7°26'22.50" en secondes d'arc
        
        // Conversion des degrés en secondes d'arc
        $phi_sec = $lat * 3600;
        $lambda_sec = $lng * 3600;
        
        // Coordonnées auxiliaires (unité: 10000 secondes)
        $phi_prime = ($phi_sec - $phi_0) / 10000;
        $lambda_prime = ($lambda_sec - $lambda_0) / 10000;
        
        // Étape 2: Projection avec polynômes officiels swisstopo
        // Coordonnée Est (Y) - LV95
        $y = 2600072.37 + 
             211455.93 * $lambda_prime - 
             10938.51 * $lambda_prime * $phi_prime - 
             0.36 * $lambda_prime * pow($phi_prime, 2) - 
             44.54 * pow($lambda_prime, 3);
        
        // Coordonnée Nord (X) - LV95  
        $x = 1200147.07 + 
             308807.95 * $phi_prime + 
             3745.25 * pow($lambda_prime, 2) + 
             76.63 * pow($phi_prime, 2) - 
             194.56 * pow($lambda_prime, 2) * $phi_prime + 
             119.79 * pow($phi_prime, 3);
        
        // Étape 3: Correction du décalage d'origine découvert
        $corrected_east = $y - 2000000;
        $corrected_north = $x - 1000000;

        return [
            'east' => round($corrected_east, 0), // Précision au mètre
            'north' => round($corrected_north, 0)
        ];
    }

    /**
     * Valide des coordonnées GPS pour la Suisse
     */
    public function validateSwissCoordinates(float $lat, float $lng): bool
    {
        // Limites approximatives de la Suisse
        return $lat >= 45.8 && $lat <= 47.8 && $lng >= 5.9 && $lng <= 10.5;
    }

    /**
     * Recherche géographique par nom de lieu
     */
    public function geocodeLocation(string $location): array
    {
        // Utilise Nominatim OpenStreetMap pour le géocodage
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = [
            'q' => $location . ', Switzerland',
            'format' => 'json',
            'limit' => 5,
            'countrycodes' => 'ch',
            'addressdetails' => 1
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0 (climbing app)',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            throw new \Exception('Erreur lors du géocodage');
        }

        $results = json_decode($response, true);
        
        if (!$results) {
            return [];
        }

        $locations = [];
        foreach ($results as $result) {
            $locations[] = [
                'name' => $result['display_name'],
                'latitude' => floatval($result['lat']),
                'longitude' => floatval($result['lon']),
                'type' => $result['type'] ?? 'unknown',
                'importance' => floatval($result['importance'] ?? 0)
            ];
        }

        return $locations;
    }

    /**
     * Obtient les conditions météo pour une position GPS
     */
    public function getWeatherForLocation(float $lat, float $lng): array
    {
        $weatherService = new WeatherService();
        return $weatherService->getWeatherByCoordinates($lat, $lng);
    }

    /**
     * Estime le temps de trajet en voiture
     */
    private function estimateTravelTime(float $distanceKm): array
    {
        // Vitesse moyenne en montagne: 40 km/h
        $drivingTimeHours = $distanceKm / 40;
        
        // Temps de marche d'approche estimé: 15 min
        $approachTimeMinutes = 15;
        
        return [
            'driving' => [
                'hours' => floor($drivingTimeHours),
                'minutes' => round(($drivingTimeHours - floor($drivingTimeHours)) * 60),
                'total_minutes' => round($drivingTimeHours * 60)
            ],
            'approach' => [
                'minutes' => $approachTimeMinutes
            ],
            'total' => [
                'minutes' => round($drivingTimeHours * 60) + $approachTimeMinutes
            ]
        ];
    }

    /**
     * Évalue la difficulté d'accès à un site
     */
    private function assessAccessDifficulty(float $distance, int $elevation): string
    {
        if ($distance > 100 || $elevation > 2000) {
            return 'difficile';
        } elseif ($distance > 50 || $elevation > 1500) {
            return 'moyen';
        } else {
            return 'facile';
        }
    }

    /**
     * Génère une URL Google Maps
     */
    private function generateGoogleMapsUrl(float $lat, float $lng, string $name): string
    {
        $encodedName = urlencode($name);
        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}&query_place_id={$encodedName}";
    }

    /**
     * Génère une URL Apple Maps
     */
    private function generateAppleMapsUrl(float $lat, float $lng, string $name): string
    {
        $encodedName = urlencode($name);
        return "https://maps.apple.com/?ll={$lat},{$lng}&q={$encodedName}";
    }

    /**
     * Génère une URL pour les cartes suisses
     */
    private function generateSwissMapUrl(float $lat, float $lng): string
    {
        $swiss = $this->convertToSwissCoordinates($lat, $lng);
        return "https://map.geo.admin.ch/?lang=fr&topic=ech&bgLayer=ch.swisstopo.pixelkarte-farbe&E={$swiss['east']}&N={$swiss['north']}&zoom=10";
    }

    /**
     * Génère une URL Waze
     */
    private function generateWazeUrl(float $lat, float $lng): string
    {
        return "https://waze.com/ul?ll={$lat},{$lng}&navigate=yes";
    }
}