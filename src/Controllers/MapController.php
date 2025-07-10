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
        ?Database $db = null,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * Affiche la carte principale avec tous les sites d'escalade
     */
    public function index(Request $request): Response
    {
        try {
            // Récupérer les paramètres de filtrage
            $filters = [
                'region_id' => $request->query->get('region'),
                'difficulty_min' => $request->query->get('difficulty_min'),
                'difficulty_max' => $request->query->get('difficulty_max'),
                'type' => $request->query->get('type'),
                'season' => $request->query->get('season')
            ];

            // Récupérer toutes les régions pour les filtres
            $regions = Region::all();

            // Récupérer les sites avec coordonnées pour la carte
            $sites = $this->getSitesForMap($filters);

            // Statistiques pour l'interface
            $stats = $this->getMapStatistics();

            return $this->render('map/index', [
                'title' => 'Carte Interactive - Sites d\'escalade en Suisse',
                'sites' => $sites,
                'regions' => $regions,
                'filters' => $filters,
                'stats' => $stats,
                'meta_description' => 'Découvrez tous les sites d\'escalade de Suisse sur notre carte interactive. Trouvez votre prochaine voie d\'escalade avec filtres par région, difficulté et type.',
                'meta_keywords' => 'carte escalade suisse, sites escalade, voies escalade, carte interactive, climbing switzerland'
            ]);

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
    public function apiSites(Request $request): Response
    {
        try {
            $filters = [
                'region_id' => $request->query->get('region'),
                'difficulty_min' => $request->query->get('difficulty_min'),
                'difficulty_max' => $request->query->get('difficulty_max'),
                'type' => $request->query->get('type'),
                'season' => $request->query->get('season')
            ];

            $sites = $this->getSitesForMap($filters);

            return $this->json([
                'success' => true,
                'sites' => $sites,
                'count' => count($sites)
            ]);

        } catch (\Exception $e) {
            error_log("Erreur MapController::apiSites: " . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des données'
            ], 500);
        }
    }

    /**
     * API pour récupérer les détails d'un site spécifique
     */
    public function apiSiteDetails(Request $request): Response
    {
        try {
            $siteId = $request->attributes->get('id');
            
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
    public function apiGeoSearch(Request $request): Response
    {
        try {
            $query = $request->query->get('q');
            $lat = $request->query->get('lat');
            $lng = $request->query->get('lng');
            $radius = $request->query->get('radius', 50); // 50km par défaut

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
                // Vérifier que le site a des coordonnées
                if (empty($site['latitude']) || empty($site['longitude'])) {
                    continue;
                }

                // Appliquer les filtres
                if (!$this->passeFilters($site, $filters)) {
                    continue;
                }

                // Récupérer les informations supplémentaires
                $region = Region::find($site['region_id']);
                $sectors = Sector::where('site_id', $site['id'])->get();
                $routeCount = 0;
                
                foreach ($sectors as $sector) {
                    $routes = Route::where('sector_id', $sector['id'])->get();
                    $routeCount += count($routes);
                }

                $sitesForMap[] = [
                    'id' => $site['id'],
                    'name' => $site['name'],
                    'latitude' => (float) $site['latitude'],
                    'longitude' => (float) $site['longitude'],
                    'region_name' => $region ? $region['name'] : 'Région inconnue',
                    'region_id' => $site['region_id'],
                    'description' => $site['description'] ?? '',
                    'approach_time' => $site['approach_time'] ?? null,
                    'sector_count' => count($sectors),
                    'route_count' => $routeCount,
                    'url' => '/sites/' . $site['id']
                ];
            }

            return $sitesForMap;

        } catch (\Exception $e) {
            error_log("Erreur getSitesForMap: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si un site passe les filtres
     */
    private function passeFilters(array $site, array $filters): bool
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
}