<?php

namespace TopoclimbCH\Services;

/**
 * Service de géocodage pour TopoclimbCH
 * Convertit les adresses en coordonnées GPS et vice versa
 */
class GeocodingService
{
    private const NOMINATIM_API = 'https://nominatim.openstreetmap.org';
    private const SWISSTOPO_API = 'https://api3.geo.admin.ch';
    
    /**
     * Convertit une adresse en coordonnées GPS
     */
    public function geocodeAddress(string $address, bool $useSwissAPI = true): array
    {
        if ($useSwissAPI) {
            return $this->geocodeWithSwisstopo($address);
        } else {
            return $this->geocodeWithNominatim($address);
        }
    }

    /**
     * Convertit des coordonnées GPS en adresse
     */
    public function reverseGeocode(float $lat, float $lng, bool $useSwissAPI = true): array
    {
        if ($useSwissAPI) {
            return $this->reverseGeocodeWithSwisstopo($lat, $lng);
        } else {
            return $this->reverseGeocodeWithNominatim($lat, $lng);
        }
    }

    /**
     * Géocodage avec l'API Swisstopo (recommandé pour la Suisse)
     */
    private function geocodeWithSwisstopo(string $address): array
    {
        $url = self::SWISSTOPO_API . '/rest/services/api/SearchServer';
        $params = [
            'searchText' => $address,
            'type' => 'locations',
            'returnGeometry' => 'true',
            'sr' => '4326', // WGS84
            'limit' => 10
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            // Fallback vers Nominatim si Swisstopo ne fonctionne pas
            return $this->geocodeWithNominatim($address);
        }

        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results'])) {
            return [];
        }

        $locations = [];
        foreach ($data['results'] as $result) {
            if (isset($result['attrs']['lat'], $result['attrs']['lon'])) {
                $locations[] = [
                    'name' => $result['attrs']['label'] ?? $address,
                    'latitude' => floatval($result['attrs']['lat']),
                    'longitude' => floatval($result['attrs']['lon']),
                    'type' => $result['attrs']['origin'] ?? 'unknown',
                    'canton' => $result['attrs']['detail'] ?? '',
                    'source' => 'swisstopo'
                ];
            }
        }

        return $locations;
    }

    /**
     * Géocodage avec Nominatim OpenStreetMap
     */
    private function geocodeWithNominatim(string $address): array
    {
        $url = self::NOMINATIM_API . '/search';
        $params = [
            'q' => $address . ', Switzerland',
            'format' => 'json',
            'limit' => 10,
            'countrycodes' => 'ch',
            'addressdetails' => 1,
            'extratags' => 1
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
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
                'canton' => $result['address']['state'] ?? '',
                'source' => 'nominatim'
            ];
        }

        return $locations;
    }

    /**
     * Géocodage inverse avec l'API Swisstopo
     */
    private function reverseGeocodeWithSwisstopo(float $lat, float $lng): array
    {
        $url = self::SWISSTOPO_API . '/rest/services/api/MapServer/identify';
        $params = [
            'geometry' => "{$lng},{$lat}",
            'geometryType' => 'esriGeometryPoint',
            'returnGeometry' => 'false',
            'sr' => '4326',
            'layers' => 'all:ch.bfs.gebaeude_wohnungs_register'
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            return $this->reverseGeocodeWithNominatim($lat, $lng);
        }

        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results'])) {
            return [];
        }

        $locations = [];
        foreach ($data['results'] as $result) {
            if (isset($result['attributes'])) {
                $attrs = $result['attributes'];
                $locations[] = [
                    'address' => $attrs['strname_deinr'] ?? 'Adresse inconnue',
                    'locality' => $attrs['plz_name'] ?? '',
                    'postal_code' => $attrs['plz'] ?? '',
                    'canton' => $attrs['gdekt'] ?? '',
                    'country' => 'Suisse',
                    'source' => 'swisstopo'
                ];
            }
        }

        return $locations;
    }

    /**
     * Géocodage inverse avec Nominatim
     */
    private function reverseGeocodeWithNominatim(float $lat, float $lng): array
    {
        $url = self::NOMINATIM_API . '/reverse';
        $params = [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'addressdetails' => 1,
            'extratags' => 1
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            throw new \Exception('Erreur lors du géocodage inverse');
        }

        $result = json_decode($response, true);
        
        if (!$result || !isset($result['address'])) {
            return [];
        }

        $address = $result['address'];
        
        return [[
            'address' => $result['display_name'],
            'locality' => $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
            'postal_code' => $address['postcode'] ?? '',
            'canton' => $address['state'] ?? '',
            'country' => $address['country'] ?? '',
            'source' => 'nominatim'
        ]];
    }

    /**
     * Recherche des points d'intérêt autour d'une position
     */
    public function findNearbyPOIs(float $lat, float $lng, int $radius = 5000): array
    {
        $url = self::NOMINATIM_API . '/search';
        $params = [
            'q' => 'climbing OR escalade OR klettern',
            'format' => 'json',
            'limit' => 20,
            'countrycodes' => 'ch',
            'addressdetails' => 1,
            'extratags' => 1,
            'bounded' => 1,
            'viewbox' => sprintf(
                '%f,%f,%f,%f',
                $lng - 0.05, $lat + 0.05,
                $lng + 0.05, $lat - 0.05
            )
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            return [];
        }

        $results = json_decode($response, true);
        
        if (!$results) {
            return [];
        }

        $geolocationService = new GeolocationService();
        $pois = [];

        foreach ($results as $result) {
            $poiLat = floatval($result['lat']);
            $poiLng = floatval($result['lon']);
            $distance = $geolocationService->calculateDistance($lat, $lng, $poiLat, $poiLng);

            if ($distance <= $radius / 1000) { // Convert to km
                $pois[] = [
                    'name' => $result['display_name'],
                    'latitude' => $poiLat,
                    'longitude' => $poiLng,
                    'type' => $result['type'] ?? 'unknown',
                    'distance' => round($distance, 2),
                    'tags' => $result['extratags'] ?? []
                ];
            }
        }

        // Trier par distance
        usort($pois, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $pois;
    }

    /**
     * Valide une adresse suisse
     */
    public function validateSwissAddress(string $address): bool
    {
        $results = $this->geocodeAddress($address, true);
        
        if (empty($results)) {
            return false;
        }

        // Vérifier que la coordonnée est bien en Suisse
        $result = $results[0];
        $lat = $result['latitude'];
        $lng = $result['longitude'];
        
        // Limites approximatives de la Suisse
        return $lat >= 45.8 && $lat <= 47.8 && $lng >= 5.9 && $lng <= 10.5;
    }

    /**
     * Convertit les coordonnées suisses CH1903+ en WGS84
     */
    public function convertFromSwissCoordinates(float $east, float $north): array
    {
        // Conversion approximative CH1903+ -> WGS84
        // Pour une conversion précise, utiliser la bibliothèque proj4php
        
        $x = ($east - 600000) / 1000000;
        $y = ($north - 200000) / 1000000;
        
        // Calcul approximatif
        $lng = 7.43958333 + 
               3.238272 * $x - 
               0.00000 * $x * $y -
               0.00000 * $x * $x * $y;
               
        $lat = 46.95240556 + 
               3.669004 * $y - 
               0.00000 * $x * $x -
               0.00000 * $y * $y;

        return [
            'latitude' => $lat,
            'longitude' => $lng
        ];
    }

    /**
     * Obtient les informations d'altitude pour une position
     */
    public function getElevation(float $lat, float $lng): ?int
    {
        $url = self::SWISSTOPO_API . '/rest/services/height';
        $params = [
            'easting' => $lng,
            'northing' => $lat,
            'sr' => '4326'
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: TopoclimbCH/1.0',
                'timeout' => 10
            ]
        ]);

        $response = file_get_contents($url . '?' . http_build_query($params), false, $context);
        
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        
        if (!$data || !isset($data['height'])) {
            return null;
        }

        return intval($data['height']);
    }
}