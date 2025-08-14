<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Controllers\BaseController;

class DiscoverController extends BaseController
{
    /**
     * Page principale découverte
     */
    public function index()
    {
        try {
            // Générer recommandations personnalisées
            $recommendations = $this->getPersonalizedRecommendations();
            
            // Voies populaires
            $popularRoutes = $this->getPopularRoutes();
            
            // Nouvelles voies ajoutées
            $newRoutes = $this->getNewRoutes();
            
            // Secteurs recommandés
            $recommendedSectors = $this->getRecommendedSectors();
            
            return $this->render('discover/index.twig', [
                'recommendations' => $recommendations,
                'popular_routes' => $popularRoutes,
                'new_routes' => $newRoutes,
                'recommended_sectors' => $recommendedSectors,
                'page_title' => 'Découvrir'
            ]);
        } catch (\Exception $e) {
            return $this->render('discover/index.twig', [
                'recommendations' => [],
                'popular_routes' => [],
                'new_routes' => [],
                'recommended_sectors' => [],
                'page_title' => 'Découvrir',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Découverte aléatoire
     */
    public function random(Request $request)
    {
        try {
            $type = $request->query->get('type', 'route');
            $difficulty = $request->query->get('difficulty', '');
            $region = $request->query->get('region', '');
            
            $randomItem = null;
            $itemType = $type;
            
            switch ($type) {
                case 'route':
                    $randomItem = $this->getRandomRoute($difficulty, $region);
                    break;
                case 'sector':
                    $randomItem = $this->getRandomSector($region);
                    break;
                case 'site':
                    $randomItem = $this->getRandomSite($region);
                    break;
                case 'any':
                    $types = ['route', 'sector', 'site'];
                    $randomType = $types[array_rand($types)];
                    $randomItem = $this->{'getRandom' . ucfirst($randomType)}($difficulty, $region);
                    $itemType = $randomType;
                    break;
            }
            
            // Générer suggestions similaires
            $similarItems = [];
            if ($randomItem) {
                $similarItems = $this->getSimilarItems($randomItem, $itemType);
            }
            
            return $this->render('discover/random.twig', [
                'random_item' => $randomItem,
                'item_type' => $itemType,
                'similar_items' => $similarItems,
                'filters' => [
                    'type' => $type,
                    'difficulty' => $difficulty,
                    'region' => $region
                ],
                'page_title' => 'Découverte Aléatoire'
            ]);
        } catch (\Exception $e) {
            return $this->render('discover/random.twig', [
                'random_item' => null,
                'item_type' => 'route',
                'similar_items' => [],
                'filters' => [],
                'page_title' => 'Découverte Aléatoire',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * API pour découverte aléatoire
     */
    public function apiRandom(Request $request)
    {
        try {
            $type = $request->query->get('type', 'route');
            $difficulty = $request->query->get('difficulty', '');
            $region = $request->query->get('region', '');
            
            $randomItem = null;
            $actualType = $type;
            
            switch ($type) {
                case 'route':
                    $randomItem = $this->getRandomRoute($difficulty, $region);
                    break;
                case 'sector':
                    $randomItem = $this->getRandomSector($region);
                    break;
                case 'site':
                    $randomItem = $this->getRandomSite($region);
                    break;
                case 'any':
                    $types = ['route', 'sector', 'site'];
                    $actualType = $types[array_rand($types)];
                    $randomItem = $this->{'getRandom' . ucfirst($actualType)}($difficulty, $region);
                    break;
            }
            
            if (!$randomItem) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucun élément trouvé avec ces critères'
                ], 404);
            }
            
            return $this->json([
                'success' => true,
                'item' => $randomItem,
                'type' => $actualType,
                'url' => "/{$actualType}s/{$randomItem['id']}"
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'DiscoverController::apiRandom');
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la découverte'
            ], 500);
        }
    }
    
    /**
     * Recommandations personnalisées basées sur l'historique
     */
    private function getPersonalizedRecommendations(): array
    {
        $recommendations = [];
        
        try {
            // Si utilisateur connecté, analyser ses favoris
            if ($this->auth && $this->auth->check()) {
                $user = $this->auth->user();
                $userId = $user->id;
                
                // Analyser les favoris pour recommandations
                $favoriteStyles = $this->db->fetchAll(
                    "SELECT r.style, COUNT(*) as count
                     FROM user_favorites f
                     JOIN climbing_routes r ON f.entity_id = r.id AND f.entity_type = 'route'
                     WHERE f.user_id = ?
                     GROUP BY r.style
                     ORDER BY count DESC
                     LIMIT 3",
                    [$userId]
                );
                
                foreach ($favoriteStyles as $style) {
                    if ($style['style']) {
                        $similarRoutes = $this->db->fetchAll(
                            "SELECT r.*, s.name as sector_name
                             FROM climbing_routes r
                             LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                             WHERE r.style LIKE ? AND r.active = 1
                             AND r.id NOT IN (
                                 SELECT entity_id FROM user_favorites 
                                 WHERE user_id = ? AND entity_type = 'route'
                             )
                             ORDER BY RANDOM()
                             LIMIT 3",
                            ['%' . $style['style'] . '%', $userId]
                        );
                        
                        if (!empty($similarRoutes)) {
                            $recommendations[] = [
                                'type' => 'style_based',
                                'title' => "Voies {$style['style']} recommandées",
                                'description' => "Basé sur vos {$style['count']} favoris en {$style['style']}",
                                'items' => $similarRoutes
                            ];
                        }
                    }
                }
            }
            
            // Recommandations générales si pas d'historique
            if (empty($recommendations)) {
                $recommendations = $this->getGeneralRecommendations();
            }
            
        } catch (\Exception $e) {
            error_log('DiscoverController::getPersonalizedRecommendations error: ' . $e->getMessage());
            $recommendations = $this->getGeneralRecommendations();
        }
        
        return $recommendations;
    }
    
    /**
     * Recommandations générales
     */
    private function getGeneralRecommendations(): array
    {
        return [
            [
                'type' => 'beginner_friendly',
                'title' => 'Voies Débutant Recommandées',
                'description' => 'Parfaites pour commencer l\'escalade',
                'items' => $this->db->fetchAll(
                    "SELECT r.*, s.name as sector_name, reg.name as region_name
                     FROM climbing_routes r
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                     WHERE r.difficulty IN ('4a', '4b', '4c', '5a') 
                     AND r.active = 1
                     ORDER BY RANDOM()
                     LIMIT 6"
                )
            ],
            [
                'type' => 'classic_routes',
                'title' => 'Voies Classiques Incontournables',
                'description' => 'Les must-do de l\'escalade suisse',
                'items' => $this->db->fetchAll(
                    "SELECT r.*, s.name as sector_name, reg.name as region_name
                     FROM climbing_routes r
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                     WHERE r.active = 1
                     ORDER BY RANDOM()
                     LIMIT 6"
                )
            ]
        ];
    }
    
    /**
     * Voies populaires
     */
    private function getPopularRoutes(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT r.*, s.name as sector_name, reg.name as region_name,
                        COUNT(f.id) as favorites_count
                 FROM climbing_routes r
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 LEFT JOIN user_favorites f ON r.id = f.entity_id AND f.entity_type = 'route'
                 WHERE r.active = 1
                 GROUP BY r.id
                 ORDER BY favorites_count DESC, r.created_at DESC
                 LIMIT 8"
            );
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Nouvelles voies ajoutées
     */
    private function getNewRoutes(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT r.*, s.name as sector_name, reg.name as region_name
                 FROM climbing_routes r
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 WHERE r.active = 1
                 ORDER BY r.created_at DESC
                 LIMIT 6"
            );
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Secteurs recommandés
     */
    private function getRecommendedSectors(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT s.*, reg.name as region_name,
                        COUNT(r.id) as routes_count
                 FROM climbing_sectors s
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                 WHERE s.active = 1
                 GROUP BY s.id
                 HAVING routes_count > 0
                 ORDER BY routes_count DESC, RANDOM()
                 LIMIT 6"
            );
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Voie aléatoire avec filtres
     */
    private function getRandomRoute(?string $difficulty = null, ?string $region = null): ?array
    {
        try {
            $query = "SELECT r.*, s.name as sector_name, reg.name as region_name
                      FROM climbing_routes r
                      LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                      LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                      WHERE r.active = 1";
            $params = [];
            
            if ($difficulty) {
                $query .= " AND r.difficulty LIKE ?";
                $params[] = $difficulty . '%';
            }
            
            if ($region) {
                $query .= " AND reg.id = ?";
                $params[] = (int)$region;
            }
            
            $query .= " ORDER BY RANDOM() LIMIT 1";
            
            return $this->db->fetchOne($query, $params) ?: null;
        } catch (\Exception $e) {
            error_log('DiscoverController::getRandomRoute error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Secteur aléatoire
     */
    private function getRandomSector(?string $region = null): ?array
    {
        try {
            $query = "SELECT s.*, reg.name as region_name,
                            COUNT(r.id) as routes_count
                      FROM climbing_sectors s
                      LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                      LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                      WHERE s.active = 1";
            $params = [];
            
            if ($region) {
                $query .= " AND reg.id = ?";
                $params[] = (int)$region;
            }
            
            $query .= " GROUP BY s.id HAVING routes_count > 0 ORDER BY RANDOM() LIMIT 1";
            
            return $this->db->fetchOne($query, $params) ?: null;
        } catch (\Exception $e) {
            error_log('DiscoverController::getRandomSector error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Site aléatoire
     */
    private function getRandomSite(?string $region = null): ?array
    {
        try {
            $query = "SELECT si.*, reg.name as region_name
                      FROM climbing_sites si
                      LEFT JOIN climbing_regions reg ON si.region_id = reg.id
                      WHERE si.active = 1";
            $params = [];
            
            if ($region) {
                $query .= " AND reg.id = ?";
                $params[] = (int)$region;
            }
            
            $query .= " ORDER BY RANDOM() LIMIT 1";
            
            return $this->db->fetchOne($query, $params) ?: null;
        } catch (\Exception $e) {
            error_log('DiscoverController::getRandomSite error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trouve des éléments similaires
     */
    private function getSimilarItems(array $item, string $type): array
    {
        try {
            switch ($type) {
                case 'route':
                    return $this->getSimilarRoutes($item);
                case 'sector':
                    return $this->getSimilarSectors($item);
                case 'site':
                    return $this->getSimilarSites($item);
            }
        } catch (\Exception $e) {
            error_log('DiscoverController::getSimilarItems error: ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Voies similaires
     */
    private function getSimilarRoutes(array $route): array
    {
        try {
            $similarRoutes = [];
            
            // Même secteur
            if ($route['sector_id']) {
                $sectorRoutes = $this->db->fetchAll(
                    "SELECT r.*, s.name as sector_name
                     FROM climbing_routes r
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     WHERE r.sector_id = ? AND r.id != ? AND r.active = 1
                     ORDER BY RANDOM()
                     LIMIT 3",
                    [$route['sector_id'], $route['id']]
                );
                
                if (!empty($sectorRoutes)) {
                    $similarRoutes[] = [
                        'title' => 'Même secteur',
                        'items' => $sectorRoutes
                    ];
                }
            }
            
            // Même difficulté
            if ($route['difficulty']) {
                $difficultyRoutes = $this->db->fetchAll(
                    "SELECT r.*, s.name as sector_name
                     FROM climbing_routes r
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     WHERE r.difficulty = ? AND r.id != ? AND r.active = 1
                     ORDER BY RANDOM()
                     LIMIT 3",
                    [$route['difficulty'], $route['id']]
                );
                
                if (!empty($difficultyRoutes)) {
                    $similarRoutes[] = [
                        'title' => 'Même difficulté',
                        'items' => $difficultyRoutes
                    ];
                }
            }
            
            return $similarRoutes;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Secteurs similaires
     */
    private function getSimilarSectors(array $sector): array
    {
        try {
            $similarSectors = [];
            
            // Même région
            if ($sector['region_id']) {
                $regionSectors = $this->db->fetchAll(
                    "SELECT s.*, reg.name as region_name,
                            COUNT(r.id) as routes_count
                     FROM climbing_sectors s
                     LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                     LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
                     WHERE s.region_id = ? AND s.id != ? AND s.active = 1
                     GROUP BY s.id
                     ORDER BY RANDOM()
                     LIMIT 4",
                    [$sector['region_id'], $sector['id']]
                );
                
                if (!empty($regionSectors)) {
                    $similarSectors[] = [
                        'title' => 'Même région',
                        'items' => $regionSectors
                    ];
                }
            }
            
            return $similarSectors;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Sites similaires
     */
    private function getSimilarSites(array $site): array
    {
        try {
            $similarSites = [];
            
            // Même région
            if ($site['region_id']) {
                $regionSites = $this->db->fetchAll(
                    "SELECT si.*, reg.name as region_name
                     FROM climbing_sites si
                     LEFT JOIN climbing_regions reg ON si.region_id = reg.id
                     WHERE si.region_id = ? AND si.id != ? AND si.active = 1
                     ORDER BY RANDOM()
                     LIMIT 4",
                    [$site['region_id'], $site['id']]
                );
                
                if (!empty($regionSites)) {
                    $similarSites[] = [
                        'title' => 'Même région',
                        'items' => $regionSites
                    ];
                }
            }
            
            return $similarSites;
        } catch (\Exception $e) {
            return [];
        }
    }
}