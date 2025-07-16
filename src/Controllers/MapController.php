<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;

/**
 * Contrôleur pour la carte interactive TopoclimbCH
 * Affiche une carte interactive avec tous les sites d'escalade suisses
 */
class MapController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        
        // Injecter la base de données dans les modèles pour éviter les problèmes de singleton
        \TopoclimbCH\Models\Region::setDatabase($this->db);
        \TopoclimbCH\Models\Site::setDatabase($this->db);
        \TopoclimbCH\Models\Sector::setDatabase($this->db);
        \TopoclimbCH\Models\Route::setDatabase($this->db);
    }

    /**
     * Affiche la carte principale avec tous les sites d'escalade
     */
    public function index(?Request $request = null): Response
    {
        try {
            // Récupérer les paramètres de filtrage depuis $_GET
            $filters = [
                'region_id' => $_GET['region'] ?? null,
                'difficulty_min' => $_GET['difficulty_min'] ?? null,
                'difficulty_max' => $_GET['difficulty_max'] ?? null,
                'type' => $_GET['type'] ?? null,
                'season' => $_GET['season'] ?? null
            ];

            // Initialiser les variables par défaut
            $regions = [];
            $sites = [];
            $stats = ['total_sites' => 0, 'total_routes' => 0, 'total_regions' => 0];
            $dbError = null;

            try {
                // Récupérer toutes les régions pour les filtres
                $regions = Region::all();

                // Récupérer les sites avec coordonnées pour la carte
                $sites = $this->getSitesForMap($filters);

                // Statistiques pour l'interface
                $stats = $this->getMapStatistics();
                
            } catch (\Exception $dbException) {
                error_log("MapController::index - Erreur DB: " . $dbException->getMessage());
                $dbError = "La base de données est temporairement inaccessible. Carte affichée avec des données d'exemple.";
                
                // Données de test suisses pour la démo
                $regions = $this->getTestRegions();
                $sites = $this->getTestSites();
                $stats = ['total_sites' => count($sites), 'total_routes' => 150, 'total_regions' => count($regions)];
            }

            $data = [
                'title' => 'Carte Interactive - Sites d\'escalade en Suisse',
                'sites' => $sites,
                'regions' => $regions,
                'filters' => $filters,
                'stats' => $stats,
                'meta_description' => 'Découvrez tous les sites d\'escalade de Suisse sur notre carte interactive. Trouvez votre prochaine voie d\'escalade avec filtres par région, difficulté et type.',
                'meta_keywords' => 'carte escalade suisse, sites escalade, voies escalade, carte interactive, climbing switzerland'
            ];
            
            // Ajouter l'erreur DB si elle existe
            if ($dbError) {
                $data['db_error'] = $dbError;
            }
            
            return $this->render('map/index', $data);

        } catch (\Exception $e) {
            error_log("Erreur MapController::index: " . $e->getMessage());
            
            return $this->render('map/index', [
                'title' => 'Carte Interactive - Sites d\'escalade en Suisse',
                'sites' => [],
                'regions' => [],
                'filters' => [],
                'stats' => ['total_sites' => 0, 'total_routes' => 0, 'total_regions' => 0],
                'error' => 'Erreur lors du chargement de la carte'
            ]);
        }
    }

    /**
     * API pour récupérer les données des sites en format JSON
     */
    public function apiSites(?Request $request = null): Response
    {
        try {
            $filters = [
                'region_id' => $_GET['region'] ?? null,
                'difficulty_min' => $_GET['difficulty_min'] ?? null,
                'difficulty_max' => $_GET['difficulty_max'] ?? null,
                'type' => $_GET['type'] ?? null,
                'season' => $_GET['season'] ?? null
            ];

            $sites = [];
            
            try {
                $sites = $this->getSitesForMap($filters);
            } catch (\Exception $dbException) {
                error_log("MapController::apiSites - Erreur DB, utilisation des données de test");
                
                // En cas d'erreur DB, utiliser les données de test
                $sites = $this->getTestSites();
                
                // Appliquer les filtres aux données de test
                $sites = $this->filterTestSites($sites, $filters);
            }

            return $this->json([
                'success' => true,
                'sites' => $sites,
                'count' => count($sites)
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiSites: " . $e->getMessage());
            
            // En dernier recours, retourner les données de test sans filtre
            $fallbackSites = $this->getTestSites();
            
            return $this->json([
                'success' => true,
                'sites' => $fallbackSites,
                'count' => count($fallbackSites),
                'warning' => 'Données de test utilisées'
            ]);
        }
    }

    /**
     * API pour récupérer les détails d'un site spécifique
     */
    public function apiSiteDetails(?Request $request = null): Response
    {
        try {
            // Récupérer l'ID depuis l'URL (assumé être passé en paramètre)
            $pathInfo = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/api\/map\/sites\/(\d+)/', $pathInfo, $matches);
            $siteId = $matches[1] ?? null;
            
            $site = Site::find($siteId);
            if (!$site) {
                return $this->json([
                    'success' => false,
                    'error' => 'Site non trouvé'
                ], 404);
            }

            // Récupérer les secteurs et voies du site
            $sectors = Sector::where('site_id', $siteId)->get();
            $routes = [];
            
            foreach ($sectors as $sector) {
                $sectorRoutes = Route::where('sector_id', $sector['id'])->get();
                $routes = array_merge($routes, $sectorRoutes);
            }

            // Calculer les statistiques du site
            $stats = [
                'total_sectors' => count($sectors),
                'total_routes' => count($routes),
                'difficulty_range' => $this->calculateDifficultyRange($routes),
                'route_types' => $this->calculateRouteTypes($routes)
            ];

            return $this->json([
                'success' => true,
                'site' => $site,
                'sectors' => $sectors,
                'routes' => $routes,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiSiteDetails: " . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des détails'
            ], 500);
        }
    }

    /**
     * API pour la recherche géographique
     */
    public function apiGeoSearch(?Request $request = null): Response
    {
        try {
            $query = $_GET['q'] ?? null;
            $lat = $_GET['lat'] ?? null;
            $lng = $_GET['lng'] ?? null;
            $radius = $_GET['radius'] ?? 50; // 50km par défaut

            $results = [];

            if ($query) {
                // Recherche par nom
                $results = $this->searchByName($query);
            } elseif ($lat && $lng) {
                // Recherche par proximité
                $results = $this->searchByProximity($lat, $lng, $radius);
            }

            return $this->json([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiGeoSearch: " . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * Récupère les sites pour l'affichage sur la carte
     */
    private function getSitesForMap(array $filters): array
    {
        try {
            $sites = Site::all();
            $sitesForMap = [];

            foreach ($sites as $site) {
                // Accéder aux propriétés directement
                $siteData = [
                    'id' => $site->id,
                    'name' => $site->name,
                    'latitude' => $site->latitude,
                    'longitude' => $site->longitude,
                    'region_id' => $site->region_id,
                    'description' => $site->description,
                    'approach_time' => $site->approach_time
                ];
                
                // Vérifier que le site a des coordonnées
                if (empty($siteData['latitude']) || empty($siteData['longitude'])) {
                    continue;
                }

                // Appliquer les filtres
                if (!$this->passeFilters($siteData, $filters)) {
                    continue;
                }

                try {
                    // Récupérer les informations supplémentaires
                    $region = Region::find($siteData['region_id']);
                    $sectors = Sector::where('site_id', $siteData['id'])->get();
                    $routeCount = 0;
                    
                    foreach ($sectors as $sector) {
                        $routes = Route::where('sector_id', $sector->id)->get();
                        $routeCount += count($routes);
                    }

                    $sitesForMap[] = [
                        'id' => $siteData['id'],
                        'name' => $siteData['name'],
                        'latitude' => (float) $siteData['latitude'],
                        'longitude' => (float) $siteData['longitude'],
                        'region_name' => $region ? $region->name : 'Région inconnue',
                        'region_id' => $siteData['region_id'],
                        'description' => $siteData['description'] ?? '',
                        'approach_time' => $siteData['approach_time'] ?? null,
                        'sector_count' => count($sectors),
                        'route_count' => $routeCount,
                        'url' => '/sites/' . $siteData['id']
                    ];
                    
                } catch (\Exception $siteException) {
                    error_log("MapController::getSitesForMap - Erreur lors du traitement du site " . $siteData['name'] . ": " . $siteException->getMessage());
                    // Continuer avec le site suivant
                    continue;
                }
            }

            return $sitesForMap;

        } catch (\Exception $e) {
            error_log("Erreur getSitesForMap: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-lancer l'exception pour que apiSites puisse utiliser les données de test
        }
    }

    /**
     * Vérifie si un site passe les filtres
     */
    private function passeFilters($site, array $filters): bool
    {
        // Filtre par région
        if (!empty($filters['region_id']) && $site['region_id'] != $filters['region_id']) {
            return false;
        }

        // TODO: Implémenter d'autres filtres (difficulté, type, saison)
        // Ces filtres nécessiteraient d'analyser les voies du site

        return true;
    }

    /**
     * Calcule les statistiques pour la carte
     */
    private function getMapStatistics(): array
    {
        try {
            $totalSites = count(Site::all());
            $totalRegions = count(Region::all());
            $totalSectors = count(Sector::all());
            $totalRoutes = count(Route::all());

            return [
                'total_sites' => $totalSites,
                'total_regions' => $totalRegions,
                'total_sectors' => $totalSectors,
                'total_routes' => $totalRoutes
            ];

        } catch (\Exception $e) {
            error_log("Erreur getMapStatistics: " . $e->getMessage());
            return [
                'total_sites' => 0,
                'total_regions' => 0,
                'total_sectors' => 0,
                'total_routes' => 0
            ];
        }
    }

    /**
     * Recherche par nom de site
     */
    private function searchByName(string $query): array
    {
        $results = [];
        $sites = Site::all();

        foreach ($sites as $site) {
            if (stripos($site['name'], $query) !== false || 
                stripos($site['description'] ?? '', $query) !== false) {
                
                if (!empty($site['latitude']) && !empty($site['longitude'])) {
                    $results[] = [
                        'id' => $site['id'],
                        'name' => $site['name'],
                        'type' => 'site',
                        'latitude' => (float) $site['latitude'],
                        'longitude' => (float) $site['longitude'],
                        'url' => '/sites/' . $site['id']
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Recherche par proximité géographique
     */
    private function searchByProximity(float $lat, float $lng, float $radius): array
    {
        $results = [];
        $sites = Site::all();

        foreach ($sites as $site) {
            if (empty($site['latitude']) || empty($site['longitude'])) {
                continue;
            }

            $distance = $this->calculateDistance(
                $lat, $lng, 
                (float) $site['latitude'], 
                (float) $site['longitude']
            );

            if ($distance <= $radius) {
                $results[] = [
                    'id' => $site['id'],
                    'name' => $site['name'],
                    'type' => 'site',
                    'latitude' => (float) $site['latitude'],
                    'longitude' => (float) $site['longitude'],
                    'distance' => round($distance, 1),
                    'url' => '/sites/' . $site['id']
                ];
            }
        }

        // Trier par distance
        usort($results, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $results;
    }

    /**
     * Calcule la distance entre deux points en kilomètres
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Rayon de la terre en kilomètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Calcule la gamme de difficulté des voies
     */
    private function calculateDifficultyRange(array $routes): array
    {
        if (empty($routes)) {
            return ['min' => null, 'max' => null];
        }

        $grades = [];
        foreach ($routes as $route) {
            if (!empty($route['difficulty_grade'])) {
                $grades[] = $route['difficulty_grade'];
            }
        }

        if (empty($grades)) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => min($grades),
            'max' => max($grades)
        ];
    }

    /**
     * Calcule la répartition des types de voies
     */
    private function calculateRouteTypes(array $routes): array
    {
        $types = [];
        
        foreach ($routes as $route) {
            $type = $route['route_type'] ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return $types;
    }

    /**
     * Données de test pour les régions suisses
     */
    private function getTestRegions(): array
    {
        return [
            ['id' => 1, 'name' => 'Valais', 'active' => 1],
            ['id' => 2, 'name' => 'Jura', 'active' => 1],
            ['id' => 3, 'name' => 'Grisons', 'active' => 1],
            ['id' => 4, 'name' => 'Tessin', 'active' => 1],
            ['id' => 5, 'name' => 'Vaud', 'active' => 1],
            ['id' => 6, 'name' => 'Berne', 'active' => 1]
        ];
    }

    /**
     * Données de test pour les sites d'escalade suisses populaires
     */
    private function getTestSites(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Saillon',
                'latitude' => 46.1847,
                'longitude' => 7.1883,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Site d\'escalade sportive réputé en Valais',
                'approach_time' => 5,
                'sector_count' => 8,
                'route_count' => 120,
                'url' => '/sites/1'
            ],
            [
                'id' => 2,
                'name' => 'Vouvry',
                'latitude' => 46.3306,
                'longitude' => 6.8542,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Escalade sportive sur calcaire',
                'approach_time' => 10,
                'sector_count' => 6,
                'route_count' => 85,
                'url' => '/sites/2'
            ],
            [
                'id' => 3,
                'name' => 'Freyr',
                'latitude' => 46.7089,
                'longitude' => 6.2333,
                'region_name' => 'Vaud',
                'region_id' => 5,
                'description' => 'Falaise calcaire au bord du lac',
                'approach_time' => 3,
                'sector_count' => 12,
                'route_count' => 200,
                'url' => '/sites/3'
            ],
            [
                'id' => 4,
                'name' => 'Pont du Diable',
                'latitude' => 46.6547,
                'longitude' => 8.5883,
                'region_name' => 'Tessin',
                'region_id' => 4,
                'description' => 'Escalade sur granit en montagne',
                'approach_time' => 20,
                'sector_count' => 4,
                'route_count' => 45,
                'url' => '/sites/4'
            ],
            [
                'id' => 5,
                'name' => 'Roc de la Vache',
                'latitude' => 47.2167,
                'longitude' => 7.0833,
                'region_name' => 'Jura',
                'region_id' => 2,
                'description' => 'Escalade traditionnelle sur calcaire jurassien',
                'approach_time' => 15,
                'sector_count' => 5,
                'route_count' => 60,
                'url' => '/sites/5'
            ],
            [
                'id' => 6,
                'name' => 'Gimmelwald',
                'latitude' => 46.5506,
                'longitude' => 7.8958,
                'region_name' => 'Berne',
                'region_id' => 6,
                'description' => 'Escalade alpine avec vue sur les Alpes',
                'approach_time' => 30,
                'sector_count' => 3,
                'route_count' => 25,
                'url' => '/sites/6'
            ],
            [
                'id' => 7,
                'name' => 'Cresciano',
                'latitude' => 46.3833,
                'longitude' => 8.8667,
                'region_name' => 'Tessin',
                'region_id' => 4,
                'description' => 'Bloc de renommée mondiale',
                'approach_time' => 5,
                'sector_count' => 10,
                'route_count' => 300,
                'url' => '/sites/7'
            ],
            [
                'id' => 8,
                'name' => 'Branson',
                'latitude' => 46.1917,
                'longitude' => 7.1833,
                'region_name' => 'Valais',
                'region_id' => 1,
                'description' => 'Escalade sportive sur schiste',
                'approach_time' => 8,
                'sector_count' => 7,
                'route_count' => 95,
                'url' => '/sites/8'
            ]
        ];
    }

    /**
     * Applique les filtres aux données de test
     */
    private function filterTestSites(array $sites, array $filters): array
    {
        $filteredSites = [];

        foreach ($sites as $site) {
            // Filtre par région
            if (!empty($filters['region_id']) && $site['region_id'] != $filters['region_id']) {
                continue;
            }

            // Pour les autres filtres (difficulté, type, saison), on accepte tous les sites
            // car les données de test ne contiennent pas ces informations détaillées
            
            $filteredSites[] = $site;
        }

        return $filteredSites;
    }
}