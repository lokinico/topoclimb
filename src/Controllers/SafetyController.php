<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Controllers\BaseController;

class SafetyController extends BaseController
{
    /**
     * Page principale sécurité
     */
    public function index()
    {
        try {
            $safetyTopics = $this->getSafetyTopics();
            $emergencyContacts = $this->getEmergencyContacts();
            $checklistItems = $this->getSafetyChecklist();
            
            return $this->render('safety/index.twig', [
                'safety_topics' => $safetyTopics,
                'emergency_contacts' => $emergencyContacts,
                'checklist_items' => $checklistItems,
                'page_title' => 'Sécurité en Escalade'
            ]);
        } catch (\Exception $e) {
            return $this->render('safety/index.twig', [
                'safety_topics' => [],
                'emergency_contacts' => [],
                'checklist_items' => [],
                'page_title' => 'Sécurité en Escalade',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Page urgences et secours
     */
    public function emergency()
    {
        try {
            $emergencyProcedures = $this->getEmergencyProcedures();
            $firstAidBasics = $this->getFirstAidBasics();
            $evacuationTechniques = $this->getEvacuationTechniques();
            
            return $this->render('safety/emergency.twig', [
                'emergency_procedures' => $emergencyProcedures,
                'first_aid_basics' => $firstAidBasics,
                'evacuation_techniques' => $evacuationTechniques,
                'page_title' => 'Urgences et Secours'
            ]);
        } catch (\Exception $e) {
            return $this->render('safety/emergency.twig', [
                'emergency_procedures' => [],
                'first_aid_basics' => [],
                'evacuation_techniques' => [],
                'page_title' => 'Urgences et Secours',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Évaluation des conditions actuelles
     */
    public function conditions(Request $request)
    {
        try {
            $sectorId = $request->query->get('sector');
            $date = $request->query->get('date', date('Y-m-d'));
            
            $conditionsData = [];
            if ($sectorId) {
                $conditionsData = $this->evaluateConditions((int)$sectorId, $date);
            }
            
            // Récupérer les secteurs pour le sélecteur
            $sectors = $this->db->fetchAll(
                "SELECT id, name, region_id FROM climbing_sectors WHERE active = 1 ORDER BY name LIMIT 100"
            );
            
            return $this->render('safety/conditions.twig', [
                'sectors' => $sectors,
                'selected_sector' => $sectorId,
                'selected_date' => $date,
                'conditions_data' => $conditionsData,
                'page_title' => 'Conditions et Météo'
            ]);
        } catch (\Exception $e) {
            return $this->render('safety/conditions.twig', [
                'sectors' => [],
                'conditions_data' => [],
                'page_title' => 'Conditions et Météo',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Récupère les sujets de sécurité
     */
    private function getSafetyTopics(): array
    {
        return [
            'equipment' => [
                'title' => 'Vérification du Matériel',
                'icon' => 'fa-tools',
                'priority' => 'high',
                'items' => [
                    'Inspection visuelle avant chaque sortie',
                    'Vérification des coutures et sangles',
                    'Test des systèmes de fermeture',
                    'Contrôle de l\'usure des cordes',
                    'Vérification des dates de péremption'
                ]
            ],
            'weather' => [
                'title' => 'Conditions Météorologiques',
                'icon' => 'fa-cloud-sun',
                'priority' => 'high',
                'items' => [
                    'Consultation météo avant départ',
                    'Évaluation risque orageux',
                    'Conditions de vent et visibilité',
                    'Température et risque hypothermie',
                    'Prévisions à court terme'
                ]
            ],
            'technique' => [
                'title' => 'Techniques Sécuritaires',
                'icon' => 'fa-climbing',
                'priority' => 'medium',
                'items' => [
                    'Nœuds de sécurité corrects',
                    'Communication claire avec partenaire',
                    'Position d\'assurage optimale',
                    'Gestion de la corde',
                    'Techniques de secours de base'
                ]
            ],
            'planning' => [
                'title' => 'Planification Sortie',
                'icon' => 'fa-map',
                'priority' => 'medium',
                'items' => [
                    'Évaluation niveau difficulté/expérience',
                    'Itinéraire de sortie communiqué',
                    'Matériel adapté au programme',
                    'Estimation durée réaliste',
                    'Plan B en cas de problème'
                ]
            ],
            'environment' => [
                'title' => 'Environnement et Accès',
                'icon' => 'fa-mountain',
                'priority' => 'low',
                'items' => [
                    'Respect des restrictions locales',
                    'Période de nidification oiseaux',
                    'État des sentiers d\'accès',
                    'Risques géologiques (éboulement)',
                    'Impact environnemental minimal'
                ]
            ]
        ];
    }
    
    /**
     * Contacts d'urgence suisses
     */
    private function getEmergencyContacts(): array
    {
        return [
            'primary' => [
                'title' => 'Urgences Principales',
                'contacts' => [
                    '112' => 'Numéro d\'urgence européen',
                    '117' => 'Police',
                    '118' => 'Pompiers',
                    '144' => 'Ambulance/SMUR',
                    '145' => 'Toxicologie'
                ]
            ],
            'mountain' => [
                'title' => 'Secours en Montagne',
                'contacts' => [
                    '1414' => 'Rega - Garde aérienne suisse',
                    '0844 834 844' => 'Air-Glaciers Valais',
                    '0041 33 856 56 56' => 'Alpine Rescue Oberland',
                    '0041 81 257 73 33' => 'Secours Grisons'
                ]
            ],
            'info' => [
                'title' => 'Informations Conditions',
                'contacts' => [
                    '162' => 'MétéoSuisse prévisions',
                    '187' => 'Informations routes/trafic',
                    'app.meteoswiss.ch' => 'Application météo officielle',
                    'www.slf.ch' => 'Institut avalanches (hiver)'
                ]
            ]
        ];
    }
    
    /**
     * Checklist de sécurité
     */
    private function getSafetyChecklist(): array
    {
        return [
            'before_departure' => [
                'title' => 'Avant le Départ',
                'items' => [
                    ['text' => 'Météo consultée et favorable', 'critical' => true],
                    ['text' => 'Itinéraire communiqué à une tierce personne', 'critical' => true],
                    ['text' => 'Matériel complet et vérifié', 'critical' => true],
                    ['text' => 'Niveau adapté à tous les participants', 'critical' => false],
                    ['text' => 'Téléphone chargé + contacts urgence', 'critical' => true],
                    ['text' => 'Heure de retour prévue communiquée', 'critical' => false]
                ]
            ],
            'on_site' => [
                'title' => 'Sur le Site',
                'items' => [
                    ['text' => 'Évaluation conditions locales', 'critical' => true],
                    ['text' => 'Inspection approche et descente', 'critical' => true],
                    ['text' => 'Communication claire établie', 'critical' => true],
                    ['text' => 'Casques portés en permanence', 'critical' => true],
                    ['text' => 'Nœuds d\'encordement vérifiés', 'critical' => true],
                    ['text' => 'Position partenaire sécurisée', 'critical' => false]
                ]
            ],
            'during_climb' => [
                'title' => 'Pendant l\'Escalade',
                'items' => [
                    ['text' => 'Communication constante maintenue', 'critical' => true],
                    ['text' => 'Assurage attentif et continu', 'critical' => true],
                    ['text' => 'Protection placée régulièrement', 'critical' => false],
                    ['text' => 'Évolution conditions surveillée', 'critical' => false],
                    ['text' => 'Décision retour prise si nécessaire', 'critical' => true]
                ]
            ]
        ];
    }
    
    /**
     * Procédures d'urgence
     */
    private function getEmergencyProcedures(): array
    {
        return [
            'accident_assessment' => [
                'title' => '1. Évaluation de la Situation',
                'steps' => [
                    'Sécuriser la zone (pas d\'autres chutes)',
                    'Évaluer l\'état de la victime',
                    'Déterminer l\'accessibilité pour secours',
                    'Évaluer les ressources disponibles'
                ],
                'duration' => '1-2 minutes'
            ],
            'alert' => [
                'title' => '2. Donner l\'Alerte',
                'steps' => [
                    'Composer 112 ou 144 (urgences)',
                    'Donner position GPS précise',
                    'Décrire nature blessures',
                    'Indiquer nombre de personnes impliquées',
                    'Préciser conditions météo/accès'
                ],
                'duration' => '3-5 minutes'
            ],
            'first_aid' => [
                'title' => '3. Premiers Secours',
                'steps' => [
                    'Position latérale sécurisée si inconscient',
                    'Contrôler hémorragies importantes',
                    'Maintenir au chaud (couverture)',
                    'Rassurer et parler à la victime',
                    'Ne pas déplacer si traumatisme rachidien'
                ],
                'duration' => 'Continu jusqu\'aux secours'
            ],
            'evacuation' => [
                'title' => '4. Préparation Évacuation',
                'steps' => [
                    'Baliser zone atterrissage hélico si possible',
                    'Préparer sac médical de fortune',
                    'Organiser points de repère visibles',
                    'Désigner personne contact secours',
                    'Documenter événements (photos si approprié)'
                ],
                'duration' => 'En attente secours'
            ]
        ];
    }
    
    /**
     * Bases premiers secours
     */
    private function getFirstAidBasics(): array
    {
        return [
            'fractures' => [
                'title' => 'Fractures',
                'symptoms' => ['Douleur intense', 'Déformation visible', 'Impossibilité bouger membre'],
                'actions' => [
                    'Immobiliser membre dans position trouvée',
                    'Utiliser matériel escalade comme attelles',
                    'Sangles ou cordes pour maintien',
                    'Ne pas essayer remettre en place'
                ],
                'avoid' => ['Déplacer la fracture', 'Donner à boire/manger']
            ],
            'head_trauma' => [
                'title' => 'Traumatisme Crânien',
                'symptoms' => ['Perte connaissance', 'Confusion', 'Nausées/vomissements', 'Maux de tête intenses'],
                'actions' => [
                    'Maintenir tête immobile',
                    'Surveiller respiration',
                    'Position demi-assise si conscient',
                    'Évacuation urgente nécessaire'
                ],
                'avoid' => ['Laisser seul', 'Donner médicaments']
            ],
            'hypothermia' => [
                'title' => 'Hypothermie',
                'symptoms' => ['Frissons violents', 'Confusion', 'Maladresse', 'Fatigue extrême'],
                'actions' => [
                    'Isoler du froid (vêtements secs)',
                    'Apporter chaleur corporelle',
                    'Boissons chaudes si conscient',
                    'Réchauffement graduel'
                ],
                'avoid' => ['Réchauffement trop rapide', 'Alcool', 'Massage']
            ]
        ];
    }
    
    /**
     * Techniques d'évacuation
     */
    private function getEvacuationTechniques(): array
    {
        return [
            'rappel_assisté' => [
                'title' => 'Rappel Assisté',
                'description' => 'Descendre une victime consciente mais blessée',
                'equipment' => ['2 cordes', 'Descendeur', 'Baudrier supplémentaire', 'Mousquetons'],
                'steps' => [
                    'Installer point d\'ancrage solide',
                    'Équiper victime d\'un baudrier',
                    'Système de rappel avec contrôle',
                    'Descente lente et contrôlée'
                ],
                'difficulty' => 'Intermédiaire'
            ],
            'mouflage' => [
                'title' => 'Système de Mouflage',
                'description' => 'Remonter une victime ou du matériel',
                'equipment' => ['Cordes', 'Poulies ou mousquetons', 'Bloqueurs'],
                'steps' => [
                    'Ancrage solide au sommet',
                    'Système démultiplication force',
                    'Progression par segments',
                    'Assurance continue'
                ],
                'difficulty' => 'Avancé'
            ],
            'portage' => [
                'title' => 'Portage d\'Urgence',
                'description' => 'Transport à pied sur terrain facile',
                'equipment' => ['Sac à dos solide', 'Sangles', 'Vêtements chauds'],
                'steps' => [
                    'Immobilisation préalable',
                    'Répartition poids plusieurs personnes',
                    'Progression très lente',
                    'Pauses fréquentes'
                ],
                'difficulty' => 'Débutant'
            ]
        ];
    }
    
    /**
     * Évalue les conditions pour un secteur donné
     */
    private function evaluateConditions(int $sectorId, string $date): array
    {
        try {
            // Récupérer informations secteur
            $sector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name
                 FROM climbing_sectors s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.id = ?",
                [$sectorId]
            );
            
            if (!$sector) {
                return [];
            }
            
            $conditions = [
                'sector' => $sector,
                'date' => $date,
                'overall_rating' => 'good',
                'factors' => []
            ];
            
            // Évaluation météo si coordonnées disponibles
            if ($sector['coordinates_lat'] && $sector['coordinates_lng']) {
                $weatherData = $this->evaluateWeatherConditions(
                    $sector['coordinates_lat'],
                    $sector['coordinates_lng'],
                    $date
                );
                $conditions['weather'] = $weatherData;
                $conditions['factors'][] = $weatherData;
            }
            
            // Évaluation saisonnière
            $seasonalData = $this->evaluateSeasonalConditions($date, $sector);
            $conditions['seasonal'] = $seasonalData;
            $conditions['factors'][] = $seasonalData;
            
            // Évaluation accès
            $accessData = $this->evaluateAccessConditions($sector);
            $conditions['access'] = $accessData;
            $conditions['factors'][] = $accessData;
            
            // Calcul note globale
            $conditions['overall_rating'] = $this->calculateOverallRating($conditions['factors']);
            
            return $conditions;
            
        } catch (\Exception $e) {
            error_log('SafetyController::evaluateConditions error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Évaluation météo pour conditions
     */
    private function evaluateWeatherConditions(float $lat, float $lng, string $date): array
    {
        // Simulation données météo - en production utiliser vraie API
        $isToday = $date === date('Y-m-d');
        
        $weather = [
            'type' => 'weather',
            'title' => 'Conditions Météorologiques',
            'rating' => 'good',
            'factors' => []
        ];
        
        if ($isToday) {
            $weather['factors'][] = [
                'name' => 'Température',
                'value' => rand(10, 25) . '°C',
                'status' => 'good'
            ];
            $weather['factors'][] = [
                'name' => 'Vent',
                'value' => rand(5, 20) . ' km/h',
                'status' => rand(1, 10) > 7 ? 'warning' : 'good'
            ];
            $weather['factors'][] = [
                'name' => 'Précipitations',
                'value' => rand(0, 5) > 3 ? 'Risque averses' : 'Sec',
                'status' => rand(0, 5) > 3 ? 'warning' : 'good'
            ];
        } else {
            $weather['factors'][] = [
                'name' => 'Prévisions',
                'value' => 'Consultez météo jour J',
                'status' => 'info'
            ];
        }
        
        return $weather;
    }
    
    /**
     * Évaluation saisonnière
     */
    private function evaluateSeasonalConditions(string $date, array $sector): array
    {
        $month = (int)date('n', strtotime($date));
        
        $seasonal = [
            'type' => 'seasonal',
            'title' => 'Conditions Saisonnières',
            'rating' => 'good',
            'factors' => []
        ];
        
        // Évaluation selon mois
        if ($month >= 3 && $month <= 5) { // Printemps
            $seasonal['factors'][] = [
                'name' => 'Saison',
                'value' => 'Printemps - Bonnes conditions',
                'status' => 'good'
            ];
        } elseif ($month >= 6 && $month <= 8) { // Été
            $seasonal['factors'][] = [
                'name' => 'Saison',
                'value' => 'Été - Chaud, commencer tôt',
                'status' => 'warning'
            ];
        } elseif ($month >= 9 && $month <= 11) { // Automne
            $seasonal['factors'][] = [
                'name' => 'Saison',
                'value' => 'Automne - Excellentes conditions',
                'status' => 'good'
            ];
        } else { // Hiver
            $seasonal['factors'][] = [
                'name' => 'Saison',
                'value' => 'Hiver - Conditions difficiles',
                'status' => 'danger'
            ];
        }
        
        // Période nidification
        if ($month >= 3 && $month <= 6) {
            $seasonal['factors'][] = [
                'name' => 'Nidification',
                'value' => 'Période sensible oiseaux',
                'status' => 'warning'
            ];
        }
        
        return $seasonal;
    }
    
    /**
     * Évaluation accès
     */
    private function evaluateAccessConditions(array $sector): array
    {
        return [
            'type' => 'access',
            'title' => 'Conditions d\'Accès',
            'rating' => 'good',
            'factors' => [
                [
                    'name' => 'Sentier',
                    'value' => 'Accessible',
                    'status' => 'good'
                ],
                [
                    'name' => 'Parking',
                    'value' => 'Disponible',
                    'status' => 'good'
                ]
            ]
        ];
    }
    
    /**
     * Calcule note globale
     */
    private function calculateOverallRating(array $factors): string
    {
        $dangerCount = 0;
        $warningCount = 0;
        $totalFactors = 0;
        
        foreach ($factors as $category) {
            foreach ($category['factors'] as $factor) {
                $totalFactors++;
                if ($factor['status'] === 'danger') {
                    $dangerCount++;
                } elseif ($factor['status'] === 'warning') {
                    $warningCount++;
                }
            }
        }
        
        if ($dangerCount > 0) {
            return 'danger';
        } elseif ($warningCount > $totalFactors / 2) {
            return 'warning';
        }
        
        return 'good';
    }
}