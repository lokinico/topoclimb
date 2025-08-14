<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

class WeatherController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        Database $db,
        CsrfManager $csrfManager,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * API: Récupère les conditions météorologiques actuelles
     */
    public function apiCurrent(Request $request): Response
    {
        try {
            $lat = $request->query->get('lat');
            $lng = $request->query->get('lng');
            $sectorId = $request->query->get('sector_id');
            $regionId = $request->query->get('region_id');

            // Si aucune coordonnée fournie, essayer de les récupérer depuis sector/region
            if ((!$lat || !$lng) && ($sectorId || $regionId)) {
                if ($sectorId) {
                    $sector = $this->db->fetchOne(
                        "SELECT coordinates_lat, coordinates_lng FROM climbing_sectors WHERE id = ? AND active = 1",
                        [(int)$sectorId]
                    );
                    if ($sector && $sector['coordinates_lat'] && $sector['coordinates_lng']) {
                        $lat = $sector['coordinates_lat'];
                        $lng = $sector['coordinates_lng'];
                    }
                } elseif ($regionId) {
                    $region = $this->db->fetchOne(
                        "SELECT coordinates_lat, coordinates_lng FROM climbing_regions WHERE id = ? AND active = 1",
                        [(int)$regionId]
                    );
                    if ($region && $region['coordinates_lat'] && $region['coordinates_lng']) {
                        $lat = $region['coordinates_lat'];
                        $lng = $region['coordinates_lng'];
                    }
                }
            }

            if (!$lat || !$lng) {
                return Response::json([
                    'success' => false,
                    'error' => 'Coordonnées géographiques requises'
                ], 400);
            }

            // Valider les coordonnées
            if (!is_numeric($lat) || !is_numeric($lng)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Coordonnées invalides'
                ], 400);
            }

            $lat = (float)$lat;
            $lng = (float)$lng;

            // Valider les limites géographiques (approximatives pour la Suisse)
            if ($lat < 45.5 || $lat > 48.0 || $lng < 5.5 || $lng > 11.0) {
                return Response::json([
                    'success' => false,
                    'error' => 'Coordonnées hors limites suisses'
                ], 400);
            }

            // Récupérer les données météo depuis l'API MeteoSwiss ou OpenWeatherMap
            $weatherData = $this->fetchWeatherData($lat, $lng);

            if (!$weatherData) {
                return Response::json([
                    'success' => false,
                    'error' => 'Impossible de récupérer les données météo'
                ], 503);
            }

            return Response::json([
                'success' => true,
                'data' => $weatherData,
                'coordinates' => [
                    'lat' => $lat,
                    'lng' => $lng
                ],
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            error_log('WeatherController::apiCurrent error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des données météo'
            ], 500);
        }
    }

    /**
     * Récupère les données météo depuis l'API externe
     */
    private function fetchWeatherData(float $lat, float $lng): ?array
    {
        try {
            // Mode développement/test : retourner des données simulées
            if (!isset($_ENV['OPENWEATHER_API_KEY']) || $_ENV['OPENWEATHER_API_KEY'] === 'demo_key') {
                return $this->getMockWeatherData($lat, $lng);
            }

            // Essayer OpenWeatherMap en priorité
            $openWeatherData = $this->fetchOpenWeatherData($lat, $lng);
            if ($openWeatherData) {
                return $openWeatherData;
            }

            // Fallback sur données simulées
            return $this->getMockWeatherData($lat, $lng);
        } catch (\Exception $e) {
            error_log('WeatherController::fetchWeatherData error: ' . $e->getMessage());
            // Toujours retourner des données simulées en cas d'erreur
            return $this->getMockWeatherData($lat, $lng);
        }
    }

    /**
     * Retourne des données météo simulées pour les tests
     */
    private function getMockWeatherData(float $lat, float $lng): array
    {
        // Générer des données réalistes basées sur les coordonnées et la saison
        $season = (int)date('n'); // Mois de l'année
        $baseTemp = $this->getSeasonalTemperature($season, $lat);
        
        return [
            'source' => 'Simulation (TopoclimbCH)',
            'temperature' => $baseTemp + rand(-3, 3),
            'humidity' => rand(40, 80),
            'wind_speed' => rand(5, 25),
            'wind_direction' => rand(0, 359),
            'pressure' => rand(980, 1020),
            'condition' => $this->getRandomCondition(),
            'description' => 'Conditions simulées pour développement',
            'visibility' => rand(10, 50),
            'precipitation' => rand(0, 5),
            'last_updated' => date('Y-m-d H:i:s'),
            'climbing_conditions' => $this->evaluateClimbingConditions($baseTemp, rand(40, 80), rand(5, 25))
        ];
    }

    /**
     * Température saisonnière approximative pour la Suisse
     */
    private function getSeasonalTemperature(int $month, float $lat): int
    {
        $seasonalTemps = [
            12 => 2, 1 => 1, 2 => 4,     // Hiver
            3 => 8, 4 => 13, 5 => 18,    // Printemps  
            6 => 21, 7 => 24, 8 => 23,   // Été
            9 => 19, 10 => 13, 11 => 7   // Automne
        ];
        
        // Ajustement selon l'altitude approximative (plus haut = plus froid)
        $altitudeAdjustment = $lat > 46.5 ? -3 : 0; // Approximation pour les Alpes
        
        return $seasonalTemps[$month] + $altitudeAdjustment;
    }

    /**
     * Condition météo aléatoire réaliste
     */
    private function getRandomCondition(): string
    {
        $conditions = ['01d', '02d', '03d', '04d', '09d', '10d', '13d'];
        return $conditions[array_rand($conditions)];
    }

    /**
     * Évalue les conditions d'escalade
     */
    private function evaluateClimbingConditions(int $temp, int $humidity, int $windSpeed): array
    {
        $score = 100;
        
        // Température optimale entre 10-25°C
        if ($temp < 5 || $temp > 30) $score -= 30;
        elseif ($temp < 10 || $temp > 25) $score -= 15;
        
        // Humidité optimale < 70%
        if ($humidity > 80) $score -= 20;
        elseif ($humidity > 70) $score -= 10;
        
        // Vent fort problématique
        if ($windSpeed > 30) $score -= 25;
        elseif ($windSpeed > 20) $score -= 10;
        
        $score = max(0, min(100, $score));
        
        if ($score >= 80) $rating = 'excellent';
        elseif ($score >= 60) $rating = 'bon';
        elseif ($score >= 40) $rating = 'moyen';
        else $rating = 'difficile';
        
        return [
            'score' => $score,
            'rating' => $rating,
            'recommendations' => $this->getClimbingRecommendations($temp, $humidity, $windSpeed)
        ];
    }

    /**
     * Recommandations d'escalade basées sur la météo
     */
    private function getClimbingRecommendations(int $temp, int $humidity, int $windSpeed): array
    {
        $recommendations = [];
        
        if ($temp < 5) {
            $recommendations[] = 'Température froide - prévoir des gants et vêtements chauds';
        }
        if ($temp > 30) {
            $recommendations[] = 'Forte chaleur - commencer tôt le matin, prévoir beaucoup d\'eau';
        }
        if ($humidity > 80) {
            $recommendations[] = 'Humidité élevée - rocher potentiellement glissant';
        }
        if ($windSpeed > 20) {
            $recommendations[] = 'Vent fort - éviter les voies exposées';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Conditions favorables pour l\'escalade';
        }
        
        return $recommendations;
    }

    /**
     * Récupère les données depuis l'API MeteoSwiss
     */
    private function fetchMeteoSwissData(float $lat, float $lng): ?array
    {
        try {
            // API MeteoSwiss (format simplifié pour le prototype)
            $url = "https://app-prod-ws.meteoswiss-app.ch/v1/plzDetail";
            
            // Trouver le code postal le plus proche (simplification)
            $plz = $this->findNearestSwissPostalCode($lat, $lng);
            if (!$plz) {
                return null;
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => [
                        'User-Agent: TopoclimbCH/1.0',
                        'Accept: application/json'
                    ]
                ]
            ]);

            $url .= "?plz={$plz}";
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if (!$data) {
                return null;
            }

            // Normaliser les données MeteoSwiss
            return [
                'source' => 'MeteoSwiss',
                'temperature' => $data['currentWeather']['temperature'] ?? null,
                'humidity' => $data['currentWeather']['humidity'] ?? null,
                'wind_speed' => $data['currentWeather']['windSpeed'] ?? null,
                'wind_direction' => $data['currentWeather']['windDirection'] ?? null,
                'pressure' => $data['currentWeather']['pressure'] ?? null,
                'condition' => $data['currentWeather']['iconV2'] ?? 'unknown',
                'description' => $this->translateWeatherCondition($data['currentWeather']['iconV2'] ?? ''),
                'visibility' => $data['currentWeather']['visibility'] ?? null,
                'precipitation' => $data['currentWeather']['precipitation'] ?? 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            error_log('WeatherController::fetchMeteoSwissData error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les données depuis OpenWeatherMap (fallback)
     */
    private function fetchOpenWeatherData(float $lat, float $lng): ?array
    {
        try {
            // Configuration OpenWeatherMap (remplacer par votre clé API)
            $apiKey = $_ENV['OPENWEATHER_API_KEY'] ?? 'demo_key';
            $url = "https://api.openweathermap.org/data/2.5/weather";
            $url .= "?lat={$lat}&lon={$lng}&appid={$apiKey}&units=metric&lang=fr";

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => [
                        'User-Agent: TopoclimbCH/1.0'
                    ]
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if (!$data || isset($data['cod']) && $data['cod'] !== 200) {
                return null;
            }

            // Normaliser les données OpenWeatherMap
            return [
                'source' => 'OpenWeatherMap',
                'temperature' => $data['main']['temp'] ?? null,
                'humidity' => $data['main']['humidity'] ?? null,
                'wind_speed' => isset($data['wind']['speed']) ? $data['wind']['speed'] * 3.6 : null, // m/s vers km/h
                'wind_direction' => $data['wind']['deg'] ?? null,
                'pressure' => $data['main']['pressure'] ?? null,
                'condition' => $data['weather'][0]['icon'] ?? 'unknown',
                'description' => $data['weather'][0]['description'] ?? '',
                'visibility' => isset($data['visibility']) ? $data['visibility'] / 1000 : null, // m vers km
                'precipitation' => $data['rain']['1h'] ?? $data['snow']['1h'] ?? 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            error_log('WeatherController::fetchOpenWeatherData error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Trouve le code postal suisse le plus proche (simplification)
     */
    private function findNearestSwissPostalCode(float $lat, float $lng): ?string
    {
        // Table de correspondance approximative pour les principales régions d'escalade
        $regions = [
            // Valais
            ['lat' => 46.2044, 'lng' => 7.7492, 'plz' => '3906'], // Saas-Fee
            ['lat' => 46.0207, 'lng' => 7.7491, 'plz' => '3920'], // Zermatt
            ['lat' => 46.1191, 'lng' => 7.2212, 'plz' => '1936'], // Verbier
            
            // Oberland bernois
            ['lat' => 46.6863, 'lng' => 7.8632, 'plz' => '3800'], // Interlaken
            ['lat' => 46.5583, 'lng' => 7.9756, 'plz' => '3818'], // Grindelwald
            
            // Grisons
            ['lat' => 46.8182, 'lng' => 9.8386, 'plz' => '7500'], // St. Moritz
            
            // Jura
            ['lat' => 47.0682, 'lng' => 6.8006, 'plz' => '2000'], // Neuchâtel
            
            // Tessin
            ['lat' => 46.0037, 'lng' => 8.9511, 'plz' => '6600'], // Locarno
        ];

        $minDistance = PHP_FLOAT_MAX;
        $nearestPlz = null;

        foreach ($regions as $region) {
            $distance = $this->calculateDistance($lat, $lng, $region['lat'], $region['lng']);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestPlz = $region['plz'];
            }
        }

        return $nearestPlz;
    }

    /**
     * Calcule la distance entre deux points géographiques
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Traduit les conditions météo en français
     */
    private function translateWeatherCondition(string $condition): string
    {
        $translations = [
            '1' => 'Ensoleillé',
            '2' => 'Peu nuageux',
            '3' => 'Partiellement nuageux',
            '4' => 'Nuageux',
            '5' => 'Très nuageux',
            '6' => 'Couvert',
            '7' => 'Averses',
            '8' => 'Pluie',
            '9' => 'Orage',
            '101' => 'Brouillard',
            '01d' => 'Ensoleillé',
            '01n' => 'Clair',
            '02d' => 'Peu nuageux',
            '02n' => 'Peu nuageux',
            '03d' => 'Nuageux',
            '03n' => 'Nuageux',
            '04d' => 'Très nuageux',
            '04n' => 'Très nuageux',
            '09d' => 'Averses',
            '09n' => 'Averses',
            '10d' => 'Pluie',
            '10n' => 'Pluie',
            '11d' => 'Orage',
            '11n' => 'Orage',
            '13d' => 'Neige',
            '13n' => 'Neige',
            '50d' => 'Brouillard',
            '50n' => 'Brouillard',
        ];

        return $translations[$condition] ?? 'Conditions inconnues';
    }
    
    /**
     * Page météo générale
     */
    public function index(): Response
    {
        try {
            // Données météo pour les principales régions d'escalade
            $mainRegions = [
                ['name' => 'Valais', 'lat' => 46.2044, 'lng' => 7.7492],
                ['name' => 'Oberland', 'lat' => 46.6863, 'lng' => 7.8632],
                ['name' => 'Grisons', 'lat' => 46.8182, 'lng' => 9.8386],
                ['name' => 'Jura', 'lat' => 47.0682, 'lng' => 6.8006],
                ['name' => 'Tessin', 'lat' => 46.0037, 'lng' => 8.9511]
            ];
            
            $weatherData = [];
            foreach ($mainRegions as $region) {
                $weather = $this->fetchWeatherData($region['lat'], $region['lng']);
                if ($weather) {
                    $weatherData[] = array_merge($region, $weather);
                }
            }
            
            return $this->render('weather/index.twig', [
                'weather_regions' => $weatherData,
                'page_title' => 'Météo Escalade Suisse'
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'WeatherController::index');
            return $this->redirect('/');
        }
    }
    
    /**
     * Météo spécifique à une région
     */
    public function region(int $regionId): Response
    {
        try {
            // Récupérer les informations de la région
            $region = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ? AND active = 1",
                [$regionId]
            );
            
            if (!$region) {
                $this->flash('error', 'Région non trouvée');
                return $this->redirect('/weather');
            }
            
            // Météo pour cette région
            $weather = null;
            if ($region['coordinates_lat'] && $region['coordinates_lng']) {
                $weather = $this->fetchWeatherData(
                    (float)$region['coordinates_lat'],
                    (float)$region['coordinates_lng']
                );
            }
            
            // Récupérer les secteurs de cette région pour la météo locale
            $sectors = $this->db->fetchAll(
                "SELECT id, name, coordinates_lat, coordinates_lng 
                 FROM climbing_sectors 
                 WHERE region_id = ? AND active = 1 
                 AND coordinates_lat IS NOT NULL 
                 AND coordinates_lng IS NOT NULL
                 ORDER BY name",
                [$regionId]
            );
            
            return $this->render('weather/region.twig', [
                'region' => $region,
                'weather' => $weather,
                'sectors' => $sectors,
                'page_title' => 'Météo - ' . $region['name']
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'WeatherController::region');
            return $this->redirect('/weather');
        }
    }
}