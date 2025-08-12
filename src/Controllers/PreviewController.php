<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;

/**
 * Controller pour les pages d'aperçu public (freemium)
 * Affiche du contenu limité pour inciter à l'inscription
 */
class PreviewController extends BaseController
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
     * Aperçu des secteurs - quelques exemples publics
     */
    public function sectorsPreview(Request $request): Response
    {
        try {
            // Récupérer quelques secteurs d'exemple (3-4 max)
            $previewSectors = $this->db->fetchAll("
                SELECT s.id, s.name, s.description, s.altitude, 
                       r.name as region_name,
                       COUNT(rt.id) as routes_count
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_routes rt ON s.id = rt.sector_id
                WHERE s.active = 1
                GROUP BY s.id, s.name, s.description, s.altitude, r.name
                ORDER BY s.created_at DESC
                LIMIT 3
            ");

            // Griser certaines informations
            foreach ($previewSectors as &$sector) {
                $sector['description'] = $this->limitText($sector['description'] ?? '', 100);
                $sector['is_preview'] = true;
                $sector['hidden_routes'] = max(0, ($sector['routes_count'] ?? 0) - 2);
            }

            // Stats générales limitées
            $stats = [
                'total_sectors' => '10+',
                'total_routes' => '50+', 
                'total_regions' => '5+',
                'preview_mode' => true
            ];

            return $this->render('preview/sectors', [
                'sectors' => $previewSectors,
                'stats' => $stats,
                'preview_mode' => true,
                'login_url' => '/login',
                'register_url' => '/register'
            ]);
            
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement de l\'aperçu secteurs');
            
            return $this->render('preview/sectors', [
                'sectors' => [],
                'stats' => ['total_sectors' => 0, 'total_routes' => 0],
                'preview_mode' => true,
                'error' => 'Impossible de charger l\'aperçu des secteurs.'
            ]);
        }
    }

    /**
     * Aperçu des routes - quelques exemples publics
     */
    public function routesPreview(Request $request): Response
    {
        try {
            // Récupérer quelques routes d'exemple (5-6 max)
            $previewRoutes = $this->db->fetchAll("
                SELECT r.id, r.name, r.difficulty, 
                       s.name as sector_name,
                       reg.name as region_name
                FROM climbing_routes r
                LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                WHERE r.active = 1 AND s.active = 1
                ORDER BY r.created_at DESC
                LIMIT 5
            ");

            // Masquer certaines informations détaillées
            foreach ($previewRoutes as &$route) {
                $route['is_preview'] = true;
                // Masquer des détails comme hauteur, coordonnées précises
                unset($route['length'], $route['coordinates_lat'], $route['coordinates_lng']);
            }

            return $this->render('preview/routes', [
                'routes' => $previewRoutes,
                'preview_mode' => true,
                'total_hidden' => '15+ routes cachées',
                'login_url' => '/login',
                'register_url' => '/register'
            ]);
            
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement de l\'aperçu routes');
            
            return $this->render('preview/routes', [
                'routes' => [],
                'preview_mode' => true,
                'error' => 'Impossible de charger l\'aperçu des routes.'
            ]);
        }
    }

    /**
     * Page d'aperçu principal - présentation du contenu
     */
    public function index(Request $request): Response
    {
        try {
            // Stats globales pour teaser
            $globalStats = $this->db->fetchOne("
                SELECT 
                    (SELECT COUNT(*) FROM climbing_sectors WHERE active = 1) as sectors,
                    (SELECT COUNT(*) FROM climbing_routes WHERE active = 1) as routes,
                    (SELECT COUNT(*) FROM climbing_regions WHERE active = 1) as regions
            ");

            // Quelques secteurs featued
            $featuredSectors = $this->db->fetchAll("
                SELECT s.id, s.name, s.altitude, r.name as region_name
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                WHERE s.active = 1
                ORDER BY RANDOM()
                LIMIT 2
            ");

            return $this->render('preview/index', [
                'stats' => [
                    'sectors' => $globalStats['sectors'] . '+ secteurs',
                    'routes' => $globalStats['routes'] . '+ voies', 
                    'regions' => $globalStats['regions'] . ' régions'
                ],
                'featured_sectors' => $featuredSectors,
                'preview_mode' => true,
                'benefits' => [
                    'Accès complet à toutes les voies',
                    'Coordonnées GPS précises',
                    'Météo en temps réel',
                    'Favoris et planification',
                    'Commentaires et évaluations'
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement de l\'aperçu');
            
            return $this->render('preview/index', [
                'stats' => [],
                'featured_sectors' => [],
                'preview_mode' => true,
                'error' => 'Impossible de charger l\'aperçu.'
            ]);
        }
    }

    /**
     * Limiter le texte pour l'aperçu
     */
    private function limitText(string $text, int $limit = 100): string
    {
        if (strlen($text) <= $limit) {
            return $text;
        }
        
        return substr($text, 0, $limit) . '... [Inscription requise pour voir plus]';
    }
}