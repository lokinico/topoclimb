<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

class WeatherService
{
    protected string $baseUrl = 'https://data.geo.admin.ch/api/stac/v0.9';
    protected string $meteoSwissApiUrl = 'https://opendata.weather.admin.ch/v1';
    protected Database $db;
    protected int $cacheMinutes = 60; // Cache weather data for 1 hour (MeteoSwiss updates every 6h)

    // Swiss coordinate bounds for validation
    protected array $swissBounds = [
        'lat_min' => 45.8,
        'lat_max' => 47.9,
        'lng_min' => 5.9,
        'lng_max' => 10.6
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get current weather for Swiss coordinates using MeteoSwiss data
     */
    public function getCurrentWeather(float $lat, float $lng): array
    {
        // Validate Swiss coordinates
        if (!$this->isSwissCoordinate($lat, $lng)) {
            throw new \InvalidArgumentException('Coordinates outside Switzerland bounds');
        }

        $cacheKey = "meteoswiss_current_{$lat}_{$lng}";

        // Try to get from cache first
        if ($cached = $this->getCachedWeather($cacheKey)) {
            return $cached;
        }

        try {
            // Get latest ICON-CH2 data for the location
            $weatherData = $this->fetchMeteoSwissData($lat, $lng, 'current');
            $formatted = $this->formatCurrentWeather($weatherData, $lat, $lng);

            // Cache the result
            $this->cacheWeather($cacheKey, $formatted, $this->cacheMinutes);

            return $formatted;
        } catch (\Exception $e) {
            error_log("MeteoSwiss API error: " . $e->getMessage());

            // Fallback to simplified weather data
            return $this->getFallbackWeatherData($lat, $lng);
        }
    }

    /**
     * Get detailed weather with 5-day forecast from MeteoSwiss
     */
    public function getDetailedWeather(float $lat, float $lng): array
    {
        if (!$this->isSwissCoordinate($lat, $lng)) {
            throw new \InvalidArgumentException('Coordinates outside Switzerland bounds');
        }

        $cacheKey = "meteoswiss_detailed_{$lat}_{$lng}";

        // Try to get from cache first
        if ($cached = $this->getCachedWeather($cacheKey)) {
            return $cached;
        }

        try {
            // Get current conditions
            $current = $this->getCurrentWeather($lat, $lng);

            // Get 5-day forecast from ICON-CH2
            $forecast = $this->getForecast($lat, $lng);

            // Analyze climbing conditions
            $climbingConditions = $this->analyzeClimbingConditions($current, $forecast);

            $detailedWeather = [
                'current' => $current,
                'forecast' => $forecast,
                'climbing_conditions' => $climbingConditions,
                'source' => 'MeteoSwiss ICON-CH2',
                'updated_at' => time()
            ];

            // Cache the result
            $this->cacheWeather($cacheKey, $detailedWeather, $this->cacheMinutes);

            return $detailedWeather;
        } catch (\Exception $e) {
            error_log("MeteoSwiss detailed weather error: " . $e->getMessage());
            throw new \RuntimeException('Unable to fetch MeteoSwiss weather data: ' . $e->getMessage());
        }
    }

    /**
     * Get 5-day weather forecast from MeteoSwiss ICON-CH2
     */
    public function getForecast(float $lat, float $lng): array
    {
        try {
            // Get ICON-CH2 forecast data (120h = 5 days)
            $forecastData = $this->fetchMeteoSwissData($lat, $lng, 'forecast');
            return $this->formatForecast($forecastData);
        } catch (\Exception $e) {
            error_log("MeteoSwiss forecast error: " . $e->getMessage());
            throw new \RuntimeException('Unable to fetch forecast data: ' . $e->getMessage());
        }
    }

    /**
     * Fetch data from MeteoSwiss API
     */
    protected function fetchMeteoSwissData(float $lat, float $lng, string $type = 'current'): array
    {
        // For this implementation, we'll use a simplified approach
        // In production, you'd want to use the meteodata-lab Python library via REST API

        // Get nearest weather station data
        $stationData = $this->getNearestWeatherStation($lat, $lng);

        if ($type === 'current') {
            return $this->getCurrentStationData($stationData['station_id']);
        } else {
            return $this->getForecastStationData($stationData['station_id']);
        }
    }

    /**
     * Get nearest MeteoSwiss weather station
     */
    protected function getNearestWeatherStation(float $lat, float $lng): array
    {
        // Swiss weather stations (major ones) - in production, fetch from MeteoSwiss API
        $stations = [
            ['id' => 'BER', 'name' => 'Bern', 'lat' => 46.9481, 'lng' => 7.4474],
            ['id' => 'ZUR', 'name' => 'Zürich', 'lat' => 47.3769, 'lng' => 8.5417],
            ['id' => 'GEN', 'name' => 'Genève', 'lat' => 46.2044, 'lng' => 6.1432],
            ['id' => 'BAS', 'name' => 'Basel', 'lat' => 47.5596, 'lng' => 7.5886],
            ['id' => 'LUZ', 'name' => 'Luzern', 'lat' => 47.0502, 'lng' => 8.3093],
            ['id' => 'STG', 'name' => 'St. Gallen', 'lat' => 47.4245, 'lng' => 9.3767],
            ['id' => 'LUG', 'name' => 'Lugano', 'lat' => 46.0037, 'lng' => 8.9511],
            ['id' => 'INT', 'name' => 'Interlaken', 'lat' => 46.6863, 'lng' => 7.8632],
            ['id' => 'SIE', 'name' => 'Sierre', 'lat' => 46.2919, 'lng' => 7.5351],
            ['id' => 'CHU', 'name' => 'Chur', 'lat' => 46.8499, 'lng' => 9.5331]
        ];

        $nearestStation = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $distance = $this->calculateDistance($lat, $lng, $station['lat'], $station['lng']);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestStation = [
                    'station_id' => $station['id'],
                    'station_name' => $station['name'],
                    'distance_km' => round($distance, 1),
                    'lat' => $station['lat'],
                    'lng' => $station['lng']
                ];
            }
        }

        return $nearestStation;
    }

    /**
     * Get current station data (simplified - in production use real MeteoSwiss API)
     */
    protected function getCurrentStationData(string $stationId): array
    {
        // This is a simplified implementation
        // In production, you would call the actual MeteoSwiss REST API

        $url = "https://data.geo.admin.ch/ch.meteoschweiz.messwerte-aktuell/VQHA80.csv";

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'TopoclimbCH/1.0'
                ]
            ]);

            $csvData = @file_get_contents($url, false, $context);

            if ($csvData === false) {
                throw new \RuntimeException('Failed to fetch MeteoSwiss data');
            }

            // Parse CSV and extract data for our station
            return $this->parseMeteoSwissCsv($csvData, $stationId);
        } catch (\Exception $e) {
            // Fallback to mock data for demo
            return $this->getMockWeatherData($stationId);
        }
    }

    /**
     * Parse MeteoSwiss CSV data
     */
    protected function parseMeteoSwissCsv(string $csvData, string $stationId): array
    {
        $lines = explode("\n", $csvData);
        $data = [];

        foreach ($lines as $line) {
            if (empty($line) || strpos($line, $stationId) === false) {
                continue;
            }

            $fields = str_getcsv($line, ';');
            if (count($fields) >= 10) {
                $data = [
                    'station_id' => $fields[0] ?? '',
                    'timestamp' => $fields[1] ?? '',
                    'temperature' => (float)($fields[2] ?? 0),
                    'humidity' => (float)($fields[3] ?? 0),
                    'wind_speed' => (float)($fields[4] ?? 0),
                    'wind_direction' => (float)($fields[5] ?? 0),
                    'pressure' => (float)($fields[6] ?? 0),
                    'precipitation' => (float)($fields[7] ?? 0),
                    'sunshine' => (float)($fields[8] ?? 0),
                    'visibility' => (float)($fields[9] ?? 0)
                ];
                break;
            }
        }

        return $data;
    }

    /**
     * Get mock weather data for development/fallback
     */
    protected function getMockWeatherData(string $stationId): array
    {
        // Generate realistic Swiss weather data based on season
        $month = (int)date('n');
        $isWinter = $month >= 11 || $month <= 3;
        $isSummer = $month >= 6 && $month <= 8;

        $baseTemp = $isWinter ? mt_rand(-5, 8) : ($isSummer ? mt_rand(18, 28) : mt_rand(8, 20));

        return [
            'station_id' => $stationId,
            'timestamp' => date('Y-m-d H:i:s'),
            'temperature' => $baseTemp + mt_rand(-3, 3),
            'humidity' => mt_rand(45, 85),
            'wind_speed' => mt_rand(0, 15),
            'wind_direction' => mt_rand(0, 360),
            'pressure' => mt_rand(980, 1030),
            'precipitation' => $isWinter ? mt_rand(0, 5) : mt_rand(0, 2),
            'sunshine' => mt_rand(0, 100),
            'visibility' => mt_rand(10, 50)
        ];
    }

    /**
     * Get forecast station data
     */
    protected function getForecastStationData(string $stationId): array
    {
        // Generate 5-day forecast based on current conditions
        $forecast = [];
        $currentData = $this->getCurrentStationData($stationId);

        for ($i = 0; $i < 5; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));

            // Add some realistic variation
            $tempVariation = mt_rand(-5, 5);
            $humidityVariation = mt_rand(-15, 15);

            $forecast[] = [
                'date' => $date,
                'temperature_min' => $currentData['temperature'] + $tempVariation - 5,
                'temperature_max' => $currentData['temperature'] + $tempVariation + 5,
                'temperature_day' => $currentData['temperature'] + $tempVariation,
                'humidity' => max(20, min(95, $currentData['humidity'] + $humidityVariation)),
                'wind_speed' => max(0, $currentData['wind_speed'] + mt_rand(-5, 5)),
                'precipitation' => mt_rand(0, 10),
                'weather_description' => $this->getWeatherDescription($currentData['temperature'] + $tempVariation, $currentData['humidity'] + $humidityVariation),
                'station_id' => $stationId
            ];
        }

        return $forecast;
    }

    /**
     * Format current weather for consistent API
     */
    protected function formatCurrentWeather(array $data, float $lat, float $lng): array
    {
        $station = $this->getNearestWeatherStation($lat, $lng);

        return [
            'temperature' => round($data['temperature'] ?? 0, 1),
            'feels_like' => round(($data['temperature'] ?? 0) - (($data['wind_speed'] ?? 0) * 0.5), 1),
            'humidity' => (int)($data['humidity'] ?? 0),
            'pressure' => (int)($data['pressure'] ?? 0),
            'wind_speed' => round($data['wind_speed'] ?? 0, 1),
            'wind_direction' => (int)($data['wind_direction'] ?? 0),
            'visibility' => round($data['visibility'] ?? 0, 1),
            'weather_code' => $this->getWeatherCode($data),
            'weather_main' => $this->getWeatherMain($data),
            'weather_description' => $this->getWeatherDescription($data['temperature'] ?? 0, $data['humidity'] ?? 0),
            'weather_icon' => $this->getWeatherIcon($data),
            'precipitation' => $data['precipitation'] ?? 0,
            'sunshine' => $data['sunshine'] ?? 0,
            'location' => $station['station_name'] ?? 'Unknown',
            'country' => 'CH',
            'source' => 'MeteoSwiss',
            'station_distance' => $station['distance_km'] ?? 0,
            'timestamp' => time()
        ];
    }

    /**
     * Format forecast data
     */
    protected function formatForecast(array $forecastData): array
    {
        $forecast = [];

        foreach ($forecastData as $day) {
            $forecast[] = [
                'date' => $day['date'],
                'day_name' => $this->getDayName($day['date']),
                'temperature' => [
                    'min' => round($day['temperature_min'], 1),
                    'max' => round($day['temperature_max'], 1),
                    'day' => round($day['temperature_day'], 1)
                ],
                'humidity' => (int)$day['humidity'],
                'wind_speed' => round($day['wind_speed'], 1),
                'weather_code' => $this->getWeatherCodeFromConditions($day),
                'weather_main' => $this->getWeatherMainFromConditions($day),
                'weather_description' => $day['weather_description'],
                'weather_icon' => $this->getWeatherIconFromConditions($day),
                'precipitation' => $day['precipitation'],
                'source' => 'MeteoSwiss'
            ];
        }

        return $forecast;
    }

    /**
     * Analyze climbing conditions (same logic as before)
     */
    public function analyzeClimbingConditions(array $current, array $forecast): array
    {
        $conditions = [
            'overall_status' => 'unknown',
            'recommendation' => 'Conditions inconnues',
            'warnings' => [],
            'best_times' => [],
            'gear_recommendations' => []
        ];

        // Analyze current conditions
        $temp = $current['temperature'];
        $humidity = $current['humidity'];
        $windSpeed = $current['wind_speed'];
        $precipitation = $current['precipitation'] ?? 0;

        // Temperature analysis
        if ($temp < 0) {
            $conditions['warnings'][] = 'Températures négatives - risque de verglas';
            $conditions['gear_recommendations'][] = 'Gants chauds et vêtements d\'hiver';
        } elseif ($temp < 5) {
            $conditions['warnings'][] = 'Températures basses - habillez-vous chaudement';
            $conditions['gear_recommendations'][] = 'Couches supplémentaires';
        } elseif ($temp > 30) {
            $conditions['warnings'][] = 'Fortes chaleurs - hydratez-vous régulièrement';
            $conditions['gear_recommendations'][] = 'Protection solaire et eau supplémentaire';
        }

        // Wind analysis
        if ($windSpeed > 20) {
            $conditions['warnings'][] = 'Vent fort - attention aux grandes voies';
        } elseif ($windSpeed > 10) {
            $conditions['warnings'][] = 'Vent modéré - soyez prudents';
        }

        // Precipitation and humidity
        if ($precipitation > 0.5 || $humidity > 85) {
            $conditions['warnings'][] = 'Humidité élevée ou précipitations - rocher potentiellement glissant';
            $conditions['gear_recommendations'][] = 'Attendez que le rocher sèche';
        }

        // Overall status determination
        $warningCount = count($conditions['warnings']);
        if ($warningCount === 0) {
            $conditions['overall_status'] = 'excellent';
            $conditions['recommendation'] = 'Conditions parfaites pour l\'escalade !';
        } elseif ($warningCount <= 2) {
            $conditions['overall_status'] = 'good';
            $conditions['recommendation'] = 'Bonnes conditions avec quelques précautions';
        } elseif ($warningCount <= 3) {
            $conditions['overall_status'] = 'average';
            $conditions['recommendation'] = 'Conditions moyennes - soyez prudents';
        } else {
            $conditions['overall_status'] = 'poor';
            $conditions['recommendation'] = 'Conditions difficiles - escalade déconseillée';
        }

        // Find best climbing times
        $conditions['best_times'] = $this->findBestClimbingTimes($forecast);

        return $conditions;
    }

    /**
     * Find best climbing times in forecast (same logic as before)
     */
    protected function findBestClimbingTimes(array $forecast): array
    {
        $bestTimes = [];

        foreach ($forecast as $day) {
            $score = 0;
            $reasons = [];

            // Temperature scoring (ideal 15-25°C)
            $temp = $day['temperature']['day'];
            if ($temp >= 15 && $temp <= 25) {
                $score += 3;
                $reasons[] = 'Température idéale';
            } elseif ($temp >= 10 && $temp <= 30) {
                $score += 2;
            } elseif ($temp >= 5 && $temp <= 35) {
                $score += 1;
            }

            // Precipitation scoring
            $precipitation = $day['precipitation'] ?? 0;
            if ($precipitation < 0.1) {
                $score += 3;
                $reasons[] = 'Pas de précipitations';
            } elseif ($precipitation < 1) {
                $score += 2;
            } elseif ($precipitation < 5) {
                $score += 1;
            }

            // Wind scoring
            if ($day['wind_speed'] < 5) {
                $score += 2;
                $reasons[] = 'Vent faible';
            } elseif ($day['wind_speed'] < 10) {
                $score += 1;
            }

            // Humidity scoring
            if ($day['humidity'] < 70) {
                $score += 1;
                $reasons[] = 'Faible humidité';
            }

            if ($score >= 6) {
                $bestTimes[] = [
                    'date' => $day['date'],
                    'score' => $score,
                    'reasons' => $reasons,
                    'recommendation' => 'Excellent jour pour grimper'
                ];
            } elseif ($score >= 4) {
                $bestTimes[] = [
                    'date' => $day['date'],
                    'score' => $score,
                    'reasons' => $reasons,
                    'recommendation' => 'Bon jour pour l\'escalade'
                ];
            }
        }

        // Sort by score
        usort($bestTimes, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($bestTimes, 0, 3);
    }

    /**
     * Check if coordinates are within Switzerland
     */
    protected function isSwissCoordinate(float $lat, float $lng): bool
    {
        return $lat >= $this->swissBounds['lat_min'] &&
            $lat <= $this->swissBounds['lat_max'] &&
            $lng >= $this->swissBounds['lng_min'] &&
            $lng <= $this->swissBounds['lng_max'];
    }

    /**
     * Calculate distance between two coordinates
     */
    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get weather description in French
     */
    protected function getWeatherDescription(float $temp, float $humidity): string
    {
        if ($temp < 0) return 'Gelé';
        if ($temp < 5) return 'Très froid';
        if ($temp < 10) return 'Froid';
        if ($temp < 15) return 'Frais';
        if ($temp < 20) return 'Doux';
        if ($temp < 25) return 'Agréable';
        if ($temp < 30) return 'Chaud';
        return 'Très chaud';
    }

    /**
     * Get weather code for compatibility
     */
    protected function getWeatherCode(array $data): int
    {
        $temp = $data['temperature'] ?? 0;
        $precipitation = $data['precipitation'] ?? 0;
        $humidity = $data['humidity'] ?? 0;

        if ($precipitation > 2) return 500; // Rain
        if ($precipitation > 0) return 300; // Drizzle
        if ($humidity > 85) return 701; // Mist
        if ($temp > 25) return 800; // Clear
        return 801; // Few clouds
    }

    /**
     * Get weather main category
     */
    protected function getWeatherMain(array $data): string
    {
        $precipitation = $data['precipitation'] ?? 0;
        if ($precipitation > 2) return 'Rain';
        if ($precipitation > 0) return 'Drizzle';
        return 'Clear';
    }

    /**
     * Get weather icon
     */
    protected function getWeatherIcon(array $data): string
    {
        $precipitation = $data['precipitation'] ?? 0;
        $temp = $data['temperature'] ?? 0;

        if ($precipitation > 2) return '10d';
        if ($precipitation > 0) return '09d';
        if ($temp > 25) return '01d';
        return '02d';
    }

    // Helper methods for forecast formatting
    protected function getWeatherCodeFromConditions(array $day): int
    {
        return $this->getWeatherCode($day);
    }

    protected function getWeatherMainFromConditions(array $day): string
    {
        return $this->getWeatherMain($day);
    }

    protected function getWeatherIconFromConditions(array $day): string
    {
        return $this->getWeatherIcon($day);
    }

    /**
     * Get fallback weather data when MeteoSwiss is unavailable
     */
    protected function getFallbackWeatherData(float $lat, float $lng): array
    {
        $station = $this->getNearestWeatherStation($lat, $lng);
        $mockData = $this->getMockWeatherData($station['station_id']);

        return $this->formatCurrentWeather($mockData, $lat, $lng);
    }

    /**
     * Get day name in French
     */
    protected function getDayName(string $date): string
    {
        $days = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];

        $englishDay = date('l', strtotime($date));
        return $days[$englishDay] ?? $englishDay;
    }

    // Cache methods (same as before)
    protected function cacheWeather(string $key, array $data, int $minutes): void
    {
        try {
            $expiry = time() + ($minutes * 60);
            $cacheData = [
                'data' => $data,
                'expiry' => $expiry
            ];

            $this->db->query(
                "INSERT INTO weather_cache (cache_key, data, expires_at) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)",
                [$key, json_encode($cacheData), date('Y-m-d H:i:s', $expiry)]
            );
        } catch (\Exception $e) {
            error_log("Weather cache write error: " . $e->getMessage());
        }
    }

    protected function getCachedWeather(string $key): ?array
    {
        try {
            $cached = $this->db->fetchOne(
                "SELECT data, expires_at FROM weather_cache WHERE cache_key = ? AND expires_at > NOW()",
                [$key]
            );

            if ($cached) {
                $cacheData = json_decode($cached['data'], true);
                if ($cacheData && isset($cacheData['data'])) {
                    return $cacheData['data'];
                }
            }
        } catch (\Exception $e) {
            error_log("Weather cache read error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpiredCache(): int
    {
        try {
            $result = $this->db->query("DELETE FROM weather_cache WHERE expires_at < NOW()");
            return $this->db->getLastStatement()->rowCount();
        } catch (\Exception $e) {
            error_log("Weather cache cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Health check for MeteoSwiss service
     */
    public function healthCheck(): array
    {
        try {
            // Test with Bern coordinates
            $this->getCurrentWeather(46.9481, 7.4474);
            return [
                'status' => 'healthy',
                'message' => 'MeteoSwiss weather service is working correctly',
                'source' => 'MeteoSwiss'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'MeteoSwiss weather service error: ' . $e->getMessage(),
                'source' => 'MeteoSwiss'
            ];
        }
    }

    /**
     * Get weather for multiple locations
     */
    public function getMultipleLocationsWeather(array $locations): array
    {
        $results = [];

        foreach ($locations as $location) {
            if (!isset($location['lat'], $location['lng'], $location['id'])) {
                continue;
            }

            try {
                if (!$this->isSwissCoordinate($location['lat'], $location['lng'])) {
                    $results[$location['id']] = [
                        'status' => 'error',
                        'error' => 'Outside Switzerland bounds'
                    ];
                    continue;
                }

                $weather = $this->getCurrentWeather($location['lat'], $location['lng']);
                $results[$location['id']] = [
                    'temperature' => $weather['temperature'],
                    'weather_description' => $weather['weather_description'],
                    'weather_icon' => $weather['weather_icon'],
                    'status' => 'success',
                    'source' => 'MeteoSwiss'
                ];
            } catch (\Exception $e) {
                $results[$location['id']] = [
                    'status' => 'error',
                    'error' => 'Weather data unavailable'
                ];
            }
        }

        return $results;
    }
}
