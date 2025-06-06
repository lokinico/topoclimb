<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

class WeatherService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openweathermap.org/data/2.5';
    protected Database $db;
    protected int $cacheMinutes = 10; // Cache weather data for 10 minutes

    public function __construct(Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->apiKey = env('WEATHER_API_KEY');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Weather API key not configured');
        }
    }

    /**
     * Get current weather for coordinates
     */
    public function getCurrentWeather(float $lat, float $lng): array
    {
        $cacheKey = "weather_current_{$lat}_{$lng}";

        // Try to get from cache first
        if ($cached = $this->getCachedWeather($cacheKey)) {
            return $cached;
        }

        $url = "{$this->baseUrl}/weather?" . http_build_query([
            'lat' => $lat,
            'lon' => $lng,
            'appid' => $this->apiKey,
            'units' => 'metric',
            'lang' => 'fr'
        ]);

        try {
            $response = $this->makeApiRequest($url);
            $weather = $this->formatCurrentWeather($response);

            // Cache the result
            $this->cacheWeather($cacheKey, $weather, $this->cacheMinutes);

            return $weather;
        } catch (\Exception $e) {
            error_log("Weather API error: " . $e->getMessage());
            throw new \RuntimeException('Unable to fetch weather data: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed weather with forecast
     */
    public function getDetailedWeather(float $lat, float $lng): array
    {
        $cacheKey = "weather_detailed_{$lat}_{$lng}";

        // Try to get from cache first
        if ($cached = $this->getCachedWeather($cacheKey)) {
            return $cached;
        }

        // Get current weather
        $current = $this->getCurrentWeather($lat, $lng);

        // Get 5-day forecast
        $forecast = $this->getForecast($lat, $lng);

        // Analyze climbing conditions
        $climbingConditions = $this->analyzeClimbingConditions($current, $forecast);

        $detailedWeather = [
            'current' => $current,
            'forecast' => $forecast,
            'climbing_conditions' => $climbingConditions,
            'updated_at' => time()
        ];

        // Cache the result
        $this->cacheWeather($cacheKey, $detailedWeather, $this->cacheMinutes);

        return $detailedWeather;
    }

    /**
     * Get 5-day weather forecast
     */
    public function getForecast(float $lat, float $lng): array
    {
        $url = "{$this->baseUrl}/forecast?" . http_build_query([
            'lat' => $lat,
            'lon' => $lng,
            'appid' => $this->apiKey,
            'units' => 'metric',
            'lang' => 'fr'
        ]);

        try {
            $response = $this->makeApiRequest($url);
            return $this->formatForecast($response);
        } catch (\Exception $e) {
            error_log("Forecast API error: " . $e->getMessage());
            throw new \RuntimeException('Unable to fetch forecast data: ' . $e->getMessage());
        }
    }

    /**
     * Analyze climbing conditions based on weather
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
        $weatherCode = $current['weather_code'];

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

        // Humidity and precipitation
        if ($humidity > 85 || in_array($weatherCode, [500, 501, 502, 503, 504, 520, 521, 522])) {
            $conditions['warnings'][] = 'Humidité élevée ou pluie - rocher potentiellement glissant';
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

        // Analyze forecast for best climbing times
        $conditions['best_times'] = $this->findBestClimbingTimes($forecast);

        return $conditions;
    }

    /**
     * Find best climbing times in forecast
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

            // Weather condition scoring
            if ($day['weather_code'] < 300) { // Clear/few clouds
                $score += 3;
                $reasons[] = 'Ciel dégagé';
            } elseif ($day['weather_code'] < 500) { // Clouds
                $score += 2;
            } elseif ($day['weather_code'] < 600) { // Rain
                $score -= 2;
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

        return array_slice($bestTimes, 0, 3); // Return top 3
    }

    /**
     * Format current weather response
     */
    protected function formatCurrentWeather(array $response): array
    {
        return [
            'temperature' => round($response['main']['temp'], 1),
            'feels_like' => round($response['main']['feels_like'], 1),
            'humidity' => $response['main']['humidity'],
            'pressure' => $response['main']['pressure'],
            'wind_speed' => round($response['wind']['speed'] ?? 0, 1),
            'wind_direction' => $response['wind']['deg'] ?? null,
            'visibility' => round(($response['visibility'] ?? 0) / 1000, 1), // Convert to km
            'weather_code' => $response['weather'][0]['id'],
            'weather_main' => $response['weather'][0]['main'],
            'weather_description' => $response['weather'][0]['description'],
            'weather_icon' => $response['weather'][0]['icon'],
            'clouds' => $response['clouds']['all'] ?? 0,
            'sunrise' => $response['sys']['sunrise'],
            'sunset' => $response['sys']['sunset'],
            'location' => $response['name'] ?? 'Unknown',
            'country' => $response['sys']['country'] ?? '',
            'timestamp' => time()
        ];
    }

    /**
     * Format forecast response
     */
    protected function formatForecast(array $response): array
    {
        $forecast = [];
        $dailyData = [];

        // Group by day
        foreach ($response['list'] as $item) {
            $date = date('Y-m-d', $item['dt']);
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [];
            }
            $dailyData[$date][] = $item;
        }

        // Process each day
        foreach ($dailyData as $date => $dayItems) {
            $dayForecast = $this->processDayForecast($date, $dayItems);
            if ($dayForecast) {
                $forecast[] = $dayForecast;
            }
        }

        return array_slice($forecast, 0, 5); // Return 5 days
    }

    /**
     * Process forecast data for a single day
     */
    protected function processDayForecast(string $date, array $dayItems): array
    {
        if (empty($dayItems)) return [];

        $temps = array_column(array_column($dayItems, 'main'), 'temp');
        $humidity = array_column(array_column($dayItems, 'main'), 'humidity');
        $windSpeeds = array_column(array_column($dayItems, 'wind'), 'speed');

        // Find midday item for main weather
        $middayItem = null;
        foreach ($dayItems as $item) {
            $hour = (int) date('H', $item['dt']);
            if ($hour >= 11 && $hour <= 14) {
                $middayItem = $item;
                break;
            }
        }

        if (!$middayItem) {
            $middayItem = $dayItems[0];
        }

        return [
            'date' => $date,
            'day_name' => $this->getDayName($date),
            'temperature' => [
                'min' => round(min($temps), 1),
                'max' => round(max($temps), 1),
                'day' => round($middayItem['main']['temp'], 1)
            ],
            'humidity' => round(array_sum($humidity) / count($humidity)),
            'wind_speed' => round(array_sum($windSpeeds) / count($windSpeeds), 1),
            'weather_code' => $middayItem['weather'][0]['id'],
            'weather_main' => $middayItem['weather'][0]['main'],
            'weather_description' => $middayItem['weather'][0]['description'],
            'weather_icon' => $middayItem['weather'][0]['icon'],
            'clouds' => $middayItem['clouds']['all'] ?? 0,
            'rain' => $middayItem['rain']['3h'] ?? 0,
            'snow' => $middayItem['snow']['3h'] ?? 0
        ];
    }

    /**
     * Make API request with error handling
     */
    protected function makeApiRequest(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'TopoclimbCH/1.0'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Failed to fetch weather data');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from weather API');
        }

        if (isset($data['cod']) && $data['cod'] !== 200) {
            throw new \RuntimeException('Weather API error: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data;
    }

    /**
     * Cache weather data
     */
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
            // Cache failure shouldn't break the request
            error_log("Weather cache write error: " . $e->getMessage());
        }
    }

    /**
     * Get cached weather data
     */
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
            // Cache failure shouldn't break the request
            error_log("Weather cache read error: " . $e->getMessage());
        }

        return null;
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
     * Get weather for multiple locations (for map view)
     */
    public function getMultipleLocationsWeather(array $locations): array
    {
        $results = [];

        foreach ($locations as $location) {
            if (!isset($location['lat'], $location['lng'], $location['id'])) {
                continue;
            }

            try {
                $weather = $this->getCurrentWeather($location['lat'], $location['lng']);
                $results[$location['id']] = [
                    'temperature' => $weather['temperature'],
                    'weather_description' => $weather['weather_description'],
                    'weather_icon' => $weather['weather_icon'],
                    'status' => 'success'
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

    /**
     * Check if weather API is working
     */
    public function healthCheck(): array
    {
        try {
            // Test with Bern coordinates
            $this->getCurrentWeather(46.9481, 7.4474);
            return [
                'status' => 'healthy',
                'message' => 'Weather API is working correctly'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Weather API is not working: ' . $e->getMessage()
            ];
        }
    }
}
