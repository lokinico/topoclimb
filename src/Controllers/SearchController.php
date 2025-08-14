<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Controllers\BaseController;

class SearchController extends BaseController
{
    /**
     * Page de recherche principale
     */
    public function index(Request $request)
    {
        try {
            $query = trim($request->query->get('q', ''));
            $type = $request->query->get('type', '');
            $difficulty = $request->query->get('difficulty', '');
            $region = $request->query->get('region', '');
            $style = $request->query->get('style', '');
            
            $results = [];
            $stats = ['total' => 0, 'routes' => 0, 'sectors' => 0, 'sites' => 0, 'regions' => 0];
            
            if ($query) {
                $results = $this->performSearch($query, [
                    'type' => $type,
                    'difficulty' => $difficulty, 
                    'region' => $region,
                    'style' => $style
                ]);
                $stats = $this->calculateSearchStats($results);
            }
            
            // Récupérer les options pour les filtres
            $searchOptions = $this->getSearchOptions();
            
            return $this->render('search/index.twig', [
                'query' => $query,
                'type' => $type,
                'difficulty' => $difficulty,
                'region' => $region,
                'style' => $style,
                'results' => $results,
                'stats' => $stats,
                'search_options' => $searchOptions,
                'page_title' => $query ? "Recherche : {$query}" : 'Recherche Avancée'
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'SearchController::index');
            return $this->render('search/index.twig', [
                'query' => '',
                'results' => [],
                'stats' => ['total' => 0, 'routes' => 0, 'sectors' => 0, 'sites' => 0, 'regions' => 0],
                'search_options' => $this->getSearchOptions(),
                'page_title' => 'Recherche',
                'error' => 'Erreur lors de la recherche'
            ]);
        }
    }
    
    /**
     * API de recherche pour autocomplétion
     */
    public function apiSearch(Request $request)
    {
        try {
            $query = trim($request->query->get('q', ''));
            $limit = min((int)$request->query->get('limit', 10), 50);
            $type = $request->query->get('type', '');
            
            if (strlen($query) < 2) {
                return $this->json([
                    'success' => true,
                    'suggestions' => [],
                    'count' => 0
                ]);
            }
            
            $suggestions = $this->getSuggestions($query, $type, $limit);
            
            return $this->json([
                'success' => true,
                'suggestions' => $suggestions,
                'count' => count($suggestions),
                'query' => $query
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'SearchController::apiSearch');
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }
    
    /**
     * Effectue la recherche dans toutes les entités
     */
    private function performSearch(string $query, array $filters): array
    {
        $results = [
            'routes' => [],
            'sectors' => [],
            'sites' => [],
            'regions' => []
        ];
        
        $searchTerm = '%' . $query . '%';
        
        try {
            // Recherche dans les voies
            if (!$filters['type'] || $filters['type'] === 'route') {
                $routeQuery = "SELECT r.id, r.name, r.difficulty, r.style, s.name as sector_name, reg.name as region_name
                              FROM climbing_routes r
                              LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                              LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                              WHERE r.name LIKE ? AND r.active = 1";
                
                $routeParams = [$searchTerm];
                
                // Filtres additionnels
                if ($filters['difficulty']) {
                    $routeQuery .= " AND r.difficulty LIKE ?";
                    $routeParams[] = $filters['difficulty'] . '%';
                }
                if ($filters['region']) {
                    $routeQuery .= " AND reg.id = ?";
                    $routeParams[] = (int)$filters['region'];
                }
                if ($filters['style']) {
                    $routeQuery .= " AND r.style LIKE ?";
                    $routeParams[] = '%' . $filters['style'] . '%';
                }
                
                $routeQuery .= " ORDER BY r.name LIMIT 20";
                $results['routes'] = $this->db->fetchAll($routeQuery, $routeParams);
            }
            
            // Recherche dans les secteurs
            if (!$filters['type'] || $filters['type'] === 'sector') {
                $sectorQuery = "SELECT s.id, s.name, s.description, reg.name as region_name,
                                      COUNT(r.id) as routes_count
                               FROM climbing_sectors s
                               LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                               LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                               WHERE s.name LIKE ? AND s.active = 1";
                
                $sectorParams = [$searchTerm];
                
                if ($filters['region']) {
                    $sectorQuery .= " AND reg.id = ?";
                    $sectorParams[] = (int)$filters['region'];
                }
                
                $sectorQuery .= " GROUP BY s.id ORDER BY s.name LIMIT 20";
                $results['sectors'] = $this->db->fetchAll($sectorQuery, $sectorParams);
            }
            
            // Recherche dans les sites
            if (!$filters['type'] || $filters['type'] === 'site') {
                $siteQuery = "SELECT si.id, si.name, si.description, reg.name as region_name
                             FROM climbing_sites si
                             LEFT JOIN climbing_regions reg ON si.region_id = reg.id
                             WHERE si.name LIKE ? AND si.active = 1";
                
                $siteParams = [$searchTerm];
                
                if ($filters['region']) {
                    $siteQuery .= " AND reg.id = ?";
                    $siteParams[] = (int)$filters['region'];
                }
                
                $siteQuery .= " ORDER BY si.name LIMIT 10";
                $results['sites'] = $this->db->fetchAll($siteQuery, $siteParams);
            }
            
            // Recherche dans les régions
            if (!$filters['type'] || $filters['type'] === 'region') {
                $regionQuery = "SELECT r.id, r.name, r.description
                               FROM climbing_regions r
                               WHERE r.name LIKE ? AND r.active = 1
                               ORDER BY r.name LIMIT 10";
                
                $results['regions'] = $this->db->fetchAll($regionQuery, [$searchTerm]);
            }
            
        } catch (\Exception $e) {
            error_log('SearchController::performSearch error: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Génère des suggestions pour l'autocomplétion
     */
    private function getSuggestions(string $query, string $type, int $limit): array
    {
        $suggestions = [];
        $searchTerm = $query . '%'; // Préfixe pour autocomplétion
        
        try {
            if (!$type || $type === 'route') {
                $routes = $this->db->fetchAll(
                    "SELECT 'route' as type, r.id, r.name, r.difficulty, s.name as sector_name
                     FROM climbing_routes r
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     WHERE r.name LIKE ? AND r.active = 1
                     ORDER BY r.name
                     LIMIT ?",
                    [$searchTerm, $limit]
                );
                
                foreach ($routes as $route) {
                    $suggestions[] = [
                        'type' => 'route',
                        'id' => $route['id'],
                        'name' => $route['name'],
                        'subtitle' => $route['difficulty'] . ' - ' . $route['sector_name'],
                        'url' => "/routes/{$route['id']}"
                    ];
                }
            }
            
            if (!$type || $type === 'sector') {
                $sectors = $this->db->fetchAll(
                    "SELECT 'sector' as type, s.id, s.name, reg.name as region_name
                     FROM climbing_sectors s
                     LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                     WHERE s.name LIKE ? AND s.active = 1
                     ORDER BY s.name
                     LIMIT ?",
                    [$searchTerm, $limit]
                );
                
                foreach ($sectors as $sector) {
                    $suggestions[] = [
                        'type' => 'sector',
                        'id' => $sector['id'],
                        'name' => $sector['name'],
                        'subtitle' => $sector['region_name'],
                        'url' => "/sectors/{$sector['id']}"
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log('SearchController::getSuggestions error: ' . $e->getMessage());
        }
        
        return array_slice($suggestions, 0, $limit);
    }
    
    /**
     * Calcule les statistiques des résultats de recherche
     */
    private function calculateSearchStats(array $results): array
    {
        return [
            'total' => count($results['routes']) + count($results['sectors']) + count($results['sites']) + count($results['regions']),
            'routes' => count($results['routes']),
            'sectors' => count($results['sectors']),
            'sites' => count($results['sites']),
            'regions' => count($results['regions'])
        ];
    }
    
    /**
     * Récupère les options pour les filtres de recherche
     */
    private function getSearchOptions(): array
    {
        try {
            // Récupérer les régions actives
            $regions = $this->db->fetchAll(
                "SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name"
            );
            
            // Difficultés communes
            $difficulties = [
                '3a', '3b', '3c', '4a', '4b', '4c',
                '5a', '5b', '5c', '6a', '6a+', '6b', '6b+', '6c', '6c+',
                '7a', '7a+', '7b', '7b+', '7c', '7c+',
                '8a', '8a+', '8b', '8b+', '8c', '8c+',
                '9a'
            ];
            
            // Styles d'escalade
            $styles = [
                'dalle' => 'Dalle',
                'vertical' => 'Vertical', 
                'léger_devers' => 'Léger dévers',
                'devers' => 'Dévers',
                'toit' => 'Toit',
                'fissure' => 'Fissure',
                'adhérence' => 'Adhérence'
            ];
            
            return [
                'regions' => $regions,
                'difficulties' => $difficulties,
                'styles' => $styles
            ];
            
        } catch (\Exception $e) {
            error_log('SearchController::getSearchOptions error: ' . $e->getMessage());
            return [
                'regions' => [],
                'difficulties' => [],
                'styles' => []
            ];
        }
    }
}