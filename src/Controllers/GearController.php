<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Controllers\BaseController;

class GearController extends BaseController
{
    /**
     * Page principale matériel et équipement
     */
    public function index()
    {
        try {
            // Catégories d'équipement
            $gearCategories = $this->getGearCategories();
            
            // Recommandations par type d'escalade
            $recommendations = $this->getClimbingRecommendations();
            
            return $this->render('gear/index.twig', [
                'gear_categories' => $gearCategories,
                'recommendations' => $recommendations,
                'page_title' => 'Matériel et Équipement'
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'GearController::index');
            return $this->render('gear/index.twig', [
                'gear_categories' => [],
                'recommendations' => [],
                'page_title' => 'Matériel et Équipement',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Calculateur d'équipement pour une voie
     */
    public function calculator(Request $request)
    {
        try {
            $routeId = $request->query->get('route_id');
            $climbingType = $request->query->get('type', 'sport');
            $difficulty = $request->query->get('difficulty', '6a');
            $length = (int)$request->query->get('length', 30);
            
            $route = null;
            if ($routeId) {
                $route = $this->db->fetchOne(
                    "SELECT r.*, s.name as sector_name 
                     FROM climbing_routes r 
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     WHERE r.id = ?",
                    [(int)$routeId]
                );
            }
            
            // Calcul automatique de l'équipement recommandé
            $gearList = $this->calculateGearForRoute($climbingType, $difficulty, $length, $route);
            
            return $this->render('gear/calculator.twig', [
                'route' => $route,
                'climbing_type' => $climbingType,
                'difficulty' => $difficulty,
                'length' => $length,
                'gear_list' => $gearList,
                'page_title' => 'Calculateur d\'Équipement'
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'GearController::calculator');
            return $this->render('gear/calculator.twig', [
                'route' => null,
                'gear_list' => [],
                'page_title' => 'Calculateur d\'Équipement',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Équipement requis pour une voie spécifique (API)
     */
    public function apiRouteGear($routeId)
    {
        try {
            $routeId = $this->validateId($routeId, 'Route ID');
            
            // Récupérer les informations de la voie
            $route = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name
                 FROM climbing_routes r
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 WHERE r.id = ?",
                [$routeId]
            );
            
            if (!$route) {
                return $this->json([
                    'success' => false,
                    'error' => 'Voie non trouvée'
                ], 404);
            }
            
            // Analyser l'équipement nécessaire
            $gearAnalysis = $this->analyzeRouteGear($route);
            
            return $this->json([
                'success' => true,
                'route' => [
                    'id' => $route['id'],
                    'name' => $route['name'],
                    'difficulty' => $route['difficulty'],
                    'style' => $route['style']
                ],
                'gear_analysis' => $gearAnalysis
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'GearController::apiRouteGear');
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'analyse'
            ], 500);
        }
    }
    
    /**
     * Récupère les catégories d'équipement
     */
    private function getGearCategories(): array
    {
        return [
            'protection' => [
                'name' => 'Protection',
                'icon' => 'fa-shield-alt',
                'items' => [
                    'Baudrier' => [
                        'description' => 'Baudrier d\'escalade certifié CE',
                        'price_range' => '50-150 CHF',
                        'essential' => true
                    ],
                    'Casque' => [
                        'description' => 'Protection tête contre chutes de pierres',
                        'price_range' => '60-120 CHF',
                        'essential' => true
                    ],
                    'Chaussons' => [
                        'description' => 'Chaussures spécialisées escalade',
                        'price_range' => '80-200 CHF',
                        'essential' => true
                    ]
                ]
            ],
            'cording' => [
                'name' => 'Cordes et Sangles',
                'icon' => 'fa-link',
                'items' => [
                    'Corde dynamique' => [
                        'description' => 'Corde 9.5-10.5mm, 60-80m',
                        'price_range' => '150-300 CHF',
                        'essential' => true
                    ],
                    'Sangles' => [
                        'description' => 'Sangles 60cm, 120cm',
                        'price_range' => '5-15 CHF/pièce',
                        'essential' => false
                    ],
                    'Cordelettes' => [
                        'description' => 'Cordelette 6-8mm pour rappels',
                        'price_range' => '2-5 CHF/mètre',
                        'essential' => false
                    ]
                ]
            ],
            'hardware' => [
                'name' => 'Matériel Métallique',
                'icon' => 'fa-tools',
                'items' => [
                    'Mousquetons' => [
                        'description' => 'Mousquetons à vis et droits',
                        'price_range' => '8-25 CHF/pièce',
                        'essential' => true
                    ],
                    'Friends' => [
                        'description' => 'Coinceurs mécaniques',
                        'price_range' => '50-80 CHF/pièce',
                        'essential' => false
                    ],
                    'Pitons' => [
                        'description' => 'Pitons pour fissures',
                        'price_range' => '10-20 CHF/pièce',
                        'essential' => false
                    ]
                ]
            ],
            'belay' => [
                'name' => 'Assurage',
                'icon' => 'fa-anchor',
                'items' => [
                    'Dispositif d\'assurage' => [
                        'description' => 'ATC, GriGri ou similaire',
                        'price_range' => '30-150 CHF',
                        'essential' => true
                    ],
                    'Descendeur' => [
                        'description' => 'Pour rappels et descentes',
                        'price_range' => '40-80 CHF',
                        'essential' => false
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Recommandations par type d'escalade
     */
    private function getClimbingRecommendations(): array
    {
        return [
            'sport' => [
                'name' => 'Escalade Sportive',
                'description' => 'Sur points fixes (spits, goujons)',
                'essential_gear' => [
                    'Baudrier',
                    'Casque', 
                    'Chaussons',
                    'Corde 60-70m',
                    '12-16 dégaines',
                    'Dispositif d\'assurage'
                ],
                'optional_gear' => [
                    'Magnésie',
                    'Brosse',
                    'Sac à corde'
                ],
                'budget_estimate' => '500-800 CHF'
            ],
            'trad' => [
                'name' => 'Escalade Traditionnelle',
                'description' => 'Avec placement de protection amovible',
                'essential_gear' => [
                    'Baudrier',
                    'Casque',
                    'Chaussons',
                    'Corde 60-80m',
                    'Jeu de Friends',
                    'Jeu de coinceurs',
                    '8-12 dégaines',
                    'Sangles diverses'
                ],
                'optional_gear' => [
                    'Pitons',
                    'Marteau',
                    'Tire-piton'
                ],
                'budget_estimate' => '1200-2000 CHF'
            ],
            'multi' => [
                'name' => 'Grande Voie',
                'description' => 'Escalade sur plusieurs longueurs',
                'essential_gear' => [
                    'Baudrier',
                    'Casque',
                    'Chaussons',
                    'Corde double ou à simple',
                    'Jeu complet protection',
                    'Descendeur',
                    'Lampe frontale'
                ],
                'optional_gear' => [
                    'Sac d\'escalade',
                    'Trousse secours',
                    'Ravitaillement'
                ],
                'budget_estimate' => '1500-2500 CHF'
            ],
            'ice' => [
                'name' => 'Escalade sur Glace',
                'description' => 'Escalade de cascades de glace',
                'essential_gear' => [
                    'Piolets techniques',
                    'Crampons',
                    'Baudrier',
                    'Casque',
                    'Corde',
                    'Broches à glace',
                    'Vêtements chauds'
                ],
                'optional_gear' => [
                    'Piolet-marteau',
                    'Broches supplémentaires'
                ],
                'budget_estimate' => '1000-1800 CHF'
            ]
        ];
    }
    
    /**
     * Calcule l'équipement recommandé pour une voie
     */
    private function calculateGearForRoute(string $type, string $difficulty, int $length, ?array $route): array
    {
        $gearList = [
            'essential' => [],
            'recommended' => [],
            'optional' => [],
            'warnings' => []
        ];
        
        // Équipement de base toujours nécessaire
        $gearList['essential'] = [
            'Baudrier d\'escalade',
            'Casque',
            'Chaussons d\'escalade',
            'Dispositif d\'assurage'
        ];
        
        // Corde selon longueur
        if ($length <= 30) {
            $gearList['essential'][] = 'Corde 60m minimum';
        } elseif ($length <= 40) {
            $gearList['essential'][] = 'Corde 70m minimum';
        } else {
            $gearList['essential'][] = 'Corde 80m minimum';
            $gearList['warnings'][] = 'Voie longue - vérifier longueur exacte';
        }
        
        // Équipement selon type
        switch ($type) {
            case 'sport':
                $degainesCount = max(8, min(20, intval($length / 3) + 4));
                $gearList['essential'][] = "{$degainesCount} dégaines minimum";
                $gearList['recommended'][] = '2-3 dégaines supplémentaires';
                break;
                
            case 'trad':
                $gearList['essential'] = array_merge($gearList['essential'], [
                    'Jeu de Friends (0.5-3)',
                    'Jeu de coinceurs',
                    'Sangles 60cm et 120cm'
                ]);
                $gearList['recommended'] = array_merge($gearList['recommended'], [
                    'Friends supplémentaires',
                    'Pitons selon terrain'
                ]);
                break;
                
            case 'multi':
                $gearList['essential'] = array_merge($gearList['essential'], [
                    'Descendeur',
                    'Lampe frontale',
                    'Trousse de secours de base'
                ]);
                $gearList['recommended'][] = 'Sac d\'escalade';
                break;
        }
        
        // Ajustements selon difficulté
        $difficultyNum = $this->parseDifficulty($difficulty);
        if ($difficultyNum >= 6.5) {
            $gearList['recommended'][] = 'Magnésie supplémentaire';
        }
        if ($difficultyNum >= 7.0) {
            $gearList['recommended'][] = 'Chaussons techniques';
            $gearList['warnings'][] = 'Difficulté élevée - expérience requise';
        }
        
        // Recommandations générales
        $gearList['recommended'] = array_merge($gearList['recommended'], [
            'Magnésie',
            'Brosse',
            'Sac à corde',
            'Trousse premiers secours'
        ]);
        
        $gearList['optional'] = [
            'Approche shoes pour marche',
            'Sac à dos',
            'Eau et collations',
            'Appareil photo'
        ];
        
        // Ajouter informations spécifiques à la voie si disponible
        if ($route) {
            if (stripos($route['style'], 'fissure') !== false) {
                $gearList['essential'][] = 'Jeu de coinceurs étendu';
            }
            if (stripos($route['description'] ?? '', 'exposé') !== false) {
                $gearList['warnings'][] = 'Voie exposée - attention au vide';
            }
        }
        
        return $gearList;
    }
    
    /**
     * Analyse l'équipement pour une voie spécifique
     */
    private function analyzeRouteGear(array $route): array
    {
        $analysis = [
            'climbing_type' => $this->determineClimbingType($route),
            'protection_style' => $this->determineProtectionStyle($route),
            'gear_requirements' => [],
            'difficulty_considerations' => [],
            'safety_notes' => []
        ];
        
        // Déterminer le type de protection
        if (stripos($route['description'] ?? '', 'spit') !== false || 
            stripos($route['description'] ?? '', 'goujon') !== false) {
            $analysis['protection_style'] = 'sport';
        } elseif (stripos($route['description'] ?? '', 'trad') !== false ||
                  stripos($route['description'] ?? '', 'friend') !== false) {
            $analysis['protection_style'] = 'traditional';
        }
        
        // Exigences selon le style de la voie
        if (stripos($route['style'], 'dalle') !== false) {
            $analysis['gear_requirements'][] = 'Chaussons adhérence';
            $analysis['difficulty_considerations'][] = 'Escalade en adhérence';
        }
        
        if (stripos($route['style'], 'devers') !== false) {
            $analysis['gear_requirements'][] = 'Chaussons techniques';
            $analysis['gear_requirements'][] = 'Magnésie importante';
            $analysis['difficulty_considerations'][] = 'Force dans les bras requise';
        }
        
        if (stripos($route['style'], 'fissure') !== false) {
            $analysis['gear_requirements'][] = 'Jeu de coinceurs';
            $analysis['difficulty_considerations'][] = 'Technique de fissure';
        }
        
        // Considérations de sécurité
        $difficultyNum = $this->parseDifficulty($route['difficulty']);
        if ($difficultyNum >= 7.0) {
            $analysis['safety_notes'][] = 'Niveau expert requis';
        }
        
        if ($route['length'] && $route['length'] > 40) {
            $analysis['safety_notes'][] = 'Voie longue - prévoir temps suffisant';
        }
        
        return $analysis;
    }
    
    /**
     * Détermine le type d'escalade depuis la route
     */
    private function determineClimbingType(array $route): string
    {
        $description = strtolower($route['description'] ?? '');
        
        if (strpos($description, 'grande voie') !== false || 
            strpos($description, 'plusieurs longueurs') !== false) {
            return 'multi';
        }
        
        if (strpos($description, 'trad') !== false ||
            strpos($description, 'friend') !== false ||
            strpos($description, 'coinceur') !== false) {
            return 'trad';
        }
        
        if (strpos($description, 'glace') !== false ||
            strpos($description, 'cascade') !== false) {
            return 'ice';
        }
        
        return 'sport'; // Par défaut
    }
    
    /**
     * Détermine le style de protection
     */
    private function determineProtectionStyle(array $route): string
    {
        $description = strtolower($route['description'] ?? '');
        
        if (strpos($description, 'spit') !== false ||
            strpos($description, 'goujon') !== false ||
            strpos($description, 'fixe') !== false) {
            return 'fixed';
        }
        
        if (strpos($description, 'friend') !== false ||
            strpos($description, 'coinceur') !== false ||
            strpos($description, 'trad') !== false) {
            return 'removable';
        }
        
        return 'mixed';
    }
    
    /**
     * Parse la difficulté en nombre pour comparaisons
     */
    private function parseDifficulty(string $difficulty): float
    {
        // Convertir les difficultés françaises en nombres
        $grades = [
            '3a' => 3.1, '3b' => 3.2, '3c' => 3.3,
            '4a' => 4.1, '4b' => 4.2, '4c' => 4.3,
            '5a' => 5.1, '5b' => 5.2, '5c' => 5.3,
            '6a' => 6.1, '6a+' => 6.15, '6b' => 6.2, '6b+' => 6.25, '6c' => 6.3, '6c+' => 6.35,
            '7a' => 7.1, '7a+' => 7.15, '7b' => 7.2, '7b+' => 7.25, '7c' => 7.3, '7c+' => 7.35,
            '8a' => 8.1, '8a+' => 8.15, '8b' => 8.2, '8b+' => 8.25, '8c' => 8.3, '8c+' => 8.35,
            '9a' => 9.1
        ];
        
        return $grades[strtolower($difficulty)] ?? 5.0;
    }
}