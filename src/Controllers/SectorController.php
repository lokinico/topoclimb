<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;

class SectorController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->db = $db;
    }

    /**
     * Test simple de la page sectors
     */
    public function index(Request $request): Response
    {
        try {
            // Récupération simple des secteurs
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.description, s.altitude, s.created_at,
                        r.name as region_name, si.name as site_name
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 WHERE s.active = 1
                 ORDER BY s.name ASC 
                 LIMIT 100"
            );

            // Récupération des régions et sites pour les filtres
            $regions = $this->db->fetchAll("SELECT * FROM climbing_regions WHERE active = 1 ORDER BY name ASC");
            $sites = $this->db->fetchAll("SELECT * FROM climbing_sites WHERE active = 1 ORDER BY name ASC");

            return $this->render('sectors/index', [
                'sectors' => $sectors,
                'regions' => $regions,
                'sites' => $sites,
                'filters' => [],
                'stats' => ['total_sectors' => count($sectors), 'total_routes' => 0]
            ]);
        } catch (\Exception $e) {
            error_log("SectorController::index - Erreur: " . $e->getMessage());

            return $this->render('sectors/index', [
                'sectors' => [],
                'regions' => [],
                'sites' => [],
                'filters' => [],
                'stats' => ['total_sectors' => 0, 'total_routes' => 0],
                'error' => 'Impossible de charger les secteurs actuellement.'
            ]);
        }
    }

    /**
     * Affichage d'un secteur individuel
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            if (!$id || !is_numeric($id)) {
                $this->flash('error', 'ID de secteur invalide');
                return $this->redirect('/sectors');
            }
            
            $id = (int) $id;

            // Récupération du secteur avec ses détails
            $sector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id,
                        si.name as site_name, si.id as site_id
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );

            if (!$sector) {
                $this->flash('error', 'Secteur non trouvé');
                return $this->redirect('/sectors');
            }

            // Récupération des voies du secteur
            $routes = $this->db->fetchAll(
                "SELECT r.id, r.name, r.number, r.difficulty, r.length, r.beauty_rating, 
                        r.style, r.equipment, r.comment
                 FROM climbing_routes r 
                 WHERE r.sector_id = ?
                 ORDER BY r.number ASC, r.name ASC 
                 LIMIT 200",
                [$id]
            );

            $stats = [
                'routes_count' => count($routes),
                'min_difficulty' => null,
                'max_difficulty' => null,
                'avg_length' => null
            ];

            // Calcul des statistiques
            if (!empty($routes)) {
                $difficulties = array_filter(array_column($routes, 'difficulty'));
                if (!empty($difficulties)) {
                    $stats['min_difficulty'] = min($difficulties);
                    $stats['max_difficulty'] = max($difficulties);
                }

                $lengths = array_filter(array_column($routes, 'length'));
                if (!empty($lengths)) {
                    $stats['avg_length'] = round(array_sum($lengths) / count($lengths), 1);
                }
            }

            return $this->render('sectors/show', [
                'title' => $sector['name'],
                'sector' => $sector,
                'routes' => $routes,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            error_log("SectorController::show - Erreur: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement du secteur');
            return $this->redirect('/sectors');
        }
    }

    /**
     * API simple
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $sectors = $this->db->fetchAll(
                "SELECT s.id, s.name, s.coordinates_lat, s.coordinates_lng, 
                        r.name as region_name
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.active = 1
                 ORDER BY s.name ASC 
                 LIMIT 100"
            );

            return new JsonResponse([
                'success' => true,
                'data' => $sectors,
                'count' => count($sectors)
            ]);
        } catch (\Exception $e) {
            error_log('Erreur API secteurs: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }

    /**
     * API Show - détails d'un secteur spécifique
     */
    public function apiShow(Request $request): JsonResponse
    {
        try {
            $id = $request->attributes->get('id');

            if (!$id || !is_numeric($id)) {
                return new JsonResponse(['error' => 'ID de secteur invalide'], 400);
            }

            $id = (int) $id;

            $sector = $this->db->fetchOne(
                "SELECT s.id, s.name, s.description, s.coordinates_lat, s.coordinates_lng, 
                        s.altitude, s.created_at,
                        r.name as region_name, r.id as region_id,
                        si.name as site_name, si.id as site_id
                 FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 LEFT JOIN climbing_sites si ON s.site_id = si.id
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );

            if (!$sector) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Secteur non trouvé'
                ], 404);
            }

            // Récupérer les statistiques
            $routesCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ?",
                [$id]
            );

            // Formatage sécurisé des données
            $data = [
                'id' => (int)$sector['id'],
                'name' => $sector['name'],
                'description' => $sector['description'],
                'coordinates' => [
                    'lat' => $sector['coordinates_lat'] ? (float)$sector['coordinates_lat'] : null,
                    'lng' => $sector['coordinates_lng'] ? (float)$sector['coordinates_lng'] : null
                ],
                'altitude' => $sector['altitude'] ? (int)$sector['altitude'] : null,
                'region' => [
                    'id' => (int)$sector['region_id'],
                    'name' => $sector['region_name']
                ],
                'site' => [
                    'id' => (int)$sector['site_id'],
                    'name' => $sector['site_name']
                ],
                'stats' => [
                    'routes_count' => (int)($routesCount['count'] ?? 0)
                ],
                'created_at' => $sector['created_at']
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            error_log('Erreur récupération secteur: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur de service'], 500);
        }
    }
}