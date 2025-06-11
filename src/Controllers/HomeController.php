<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\View;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\SiteService;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Services\RouteService;
use TopoclimbCH\Services\UserService;
use TopoclimbCH\Services\WeatherService;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\User;
use TopoclimbCH\Models\UserAscent;
use TopoclimbCH\Models\Media;

class HomeController extends BaseController
{
    private RegionService $regionService;
    private SiteService $siteService;
    private SectorService $sectorService;
    private RouteService $routeService;
    private UserService $userService;
    private ?WeatherService $weatherService;

    public function __construct(
        View $view,                      // Position 1: pour BaseController
        Database $db,                    // Position 2
        RegionService $regionService,    // Position 3
        SiteService $siteService,        // Position 4
        SectorService $sectorService,    // Position 5
        RouteService $routeService,      // Position 6
        UserService $userService,        // Position 7
        ?WeatherService $weatherService = null // Position 8
    ) {
        parent::__construct($view);      // Passer View au BaseController
        $this->db = $db;                 // Stocker Database
        $this->regionService = $regionService;
        $this->siteService = $siteService;
        $this->sectorService = $sectorService;
        $this->routeService = $routeService;
        $this->userService = $userService;
        $this->weatherService = $weatherService;
    }

    public function index(): void
    {
        try {
            // Calculer les statistiques dynamiques
            $stats = $this->calculateStats();

            // Récupérer le contenu populaire
            $popularSectors = $this->getPopularSectors();
            $recentBooks = $this->getRecentBooks();
            $trendingRoutes = $this->getTrendingRoutes();

            // Variables pour la page
            $data = [
                'title' => 'Découvrez l\'escalade en Suisse',
                'description' => 'La plateforme de référence pour explorer les sites d\'escalade suisses. Plus de ' .
                    $stats['sectors_count'] . ' secteurs, ' . $stats['routes_count'] . ' voies et une communauté passionnée vous attendent.',
                'stats' => $stats,
                'popular_sectors' => $popularSectors,
                'recent_books' => $recentBooks,
                'trending_routes' => $trendingRoutes,
                // Ajout des breadcrumbs pour la page d'accueil
                'breadcrumbs' => [
                    ['title' => 'Accueil', 'url' => '/']
                ]
            ];

            $this->render('home/index', $data);
        } catch (\Exception $e) {
            $this->handleError($e, 'Erreur lors du chargement de la page d\'accueil');
        }
    }

    /**
     * Calcule les statistiques dynamiques pour la page d'accueil
     */
    private function calculateStats(): array
    {
        try {
            // Statistiques principales
            $regions_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions WHERE active = 1")['count'] ?? 0;
            $sites_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites WHERE active = 1")['count'] ?? 0;
            $sectors_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors WHERE active = 1")['count'] ?? 0;
            $routes_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_routes WHERE active = 1")['count'] ?? 0;
            $books_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_books WHERE active = 1")['count'] ?? 0;
            $users_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;

            // Statistiques secondaires
            $ascents_count = $this->db->fetchOne("SELECT COUNT(*) as count FROM user_ascents")['count'] ?? 0;

            // Utilisateurs actifs ce mois
            $active_users_month = $this->db->fetchOne("
                SELECT COUNT(DISTINCT user_id) as count 
                FROM user_ascents 
                WHERE ascent_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ")['count'] ?? 0;

            // Nouvelles voies ce mois
            $new_routes_month = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM climbing_routes 
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ")['count'] ?? 0;

            // Photos partagées
            $photos_count = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM climbing_media 
                WHERE media_type = 'image' AND is_public = 1
            ")['count'] ?? 0;

            return [
                'regions_count' => $this->formatNumber($regions_count),
                'sites_count' => $this->formatNumber($sites_count),
                'sectors_count' => $this->formatNumber($sectors_count),
                'routes_count' => $this->formatNumber($routes_count),
                'books_count' => $this->formatNumber($books_count),
                'users_count' => $this->formatNumber($users_count),
                'ascents_count' => $this->formatNumber($ascents_count),
                'active_users_month' => $this->formatNumber($active_users_month),
                'new_routes_month' => $this->formatNumber($new_routes_month),
                'photos_count' => $this->formatNumber($photos_count)
            ];
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            return [
                'regions_count' => '0',
                'sites_count' => '0',
                'sectors_count' => '0',
                'routes_count' => '0',
                'books_count' => '0',
                'users_count' => '0',
                'ascents_count' => '0',
                'active_users_month' => '0',
                'new_routes_month' => '0',
                'photos_count' => '0'
            ];
        }
    }

    /**
     * Récupère les secteurs populaires
     */
    private function getPopularSectors(int $limit = 6): array
    {
        try {
            $query = "
                SELECT 
                    s.*,
                    r.name as region_name,
                    st.name as site_name,
                    COUNT(ro.id) as routes_count,
                    MIN(ro.difficulty) as min_difficulty,
                    MAX(ro.difficulty) as max_difficulty,
                    AVG(ua.quality_rating) as avg_rating,
                    COUNT(ua.id) as ascents_count
                FROM climbing_sectors s
                LEFT JOIN climbing_regions r ON s.region_id = r.id
                LEFT JOIN climbing_sites st ON s.site_id = st.id
                LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
                LEFT JOIN user_ascents ua ON ro.id = ua.route_id
                WHERE s.active = 1
                GROUP BY s.id
                HAVING routes_count > 0
                ORDER BY ascents_count DESC, avg_rating DESC, routes_count DESC
                LIMIT ?
            ";

            $sectors = $this->db->fetchAll($query, [$limit]);

            foreach ($sectors as &$sector) {
                // Ajouter les informations de région/site
                $sector['region'] = $sector['region_name'] ? ['name' => $sector['region_name']] : null;
                $sector['site'] = $sector['site_name'] ? ['name' => $sector['site_name']] : null;

                // Formater les données
                $sector['routes_count'] = (int)$sector['routes_count'];
                $sector['ascents_count'] = (int)$sector['ascents_count'];
                $sector['avg_rating'] = $sector['avg_rating'] ? round((float)$sector['avg_rating'], 1) : null;
            }

            return $sectors;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les guides/books récents
     */
    private function getRecentBooks(int $limit = 6): array
    {
        try {
            $query = "
                SELECT 
                    b.*,
                    r.name as region_name,
                    COUNT(bs.sector_id) as sectors_count
                FROM climbing_books b
                LEFT JOIN climbing_regions r ON b.region_id = r.id
                LEFT JOIN climbing_book_sectors bs ON b.id = bs.book_id
                WHERE b.active = 1
                GROUP BY b.id
                ORDER BY b.created_at DESC, b.year DESC
                LIMIT ?
            ";

            $books = $this->db->fetchAll($query, [$limit]);

            foreach ($books as &$book) {
                // Ajouter les informations de région
                $book['region'] = $book['region_name'] ? ['name' => $book['region_name']] : null;

                // Formater les données
                $book['sectors_count'] = (int)$book['sectors_count'];
                $book['year'] = $book['year'] ? (int)$book['year'] : null;
            }

            return $books;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les voies tendances (plus escaladées récemment)
     */
    private function getTrendingRoutes(int $limit = 6): array
    {
        try {
            $query = "
                SELECT 
                    ro.*,
                    s.name as sector_name,
                    COUNT(ua.id) as recent_ascents,
                    AVG(ua.quality_rating) as avg_rating
                FROM climbing_routes ro
                LEFT JOIN climbing_sectors s ON ro.sector_id = s.id
                LEFT JOIN user_ascents ua ON ro.id = ua.route_id 
                    AND ua.ascent_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                WHERE ro.active = 1
                GROUP BY ro.id
                HAVING recent_ascents > 0
                ORDER BY recent_ascents DESC, avg_rating DESC
                LIMIT ?
            ";

            $routes = $this->db->fetchAll($query, [$limit]);

            foreach ($routes as &$route) {
                // Ajouter les informations de secteur
                $route['sector'] = $route['sector_name'] ? ['name' => $route['sector_name']] : null;

                // Formater les données
                $route['recent_ascents'] = (int)$route['recent_ascents'];
                $route['avg_rating'] = $route['avg_rating'] ? round((float)$route['avg_rating'], 1) : null;
                $route['beauty'] = $route['beauty'] ? (int)$route['beauty'] : null;
                $route['length'] = $route['length'] ? round((float)$route['length'], 1) : null;
            }

            return $routes;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Formate un nombre pour l'affichage
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000) {
            return number_format($number / 1000, 1) . 'k';
        }
        return (string)$number;
    }

    /**
     * Page À propos
     */
    public function about(): void
    {
        $this->render('pages/about', [
            'title' => 'À propos de TopoclimbCH',
            'breadcrumbs' => [
                ['title' => 'Accueil', 'url' => '/'],
                ['title' => 'À propos', 'url' => '/about']
            ]
        ]);
    }

    /**
     * Page Contact
     */
    public function contact(): void
    {
        $this->render('pages/contact', [
            'title' => 'Contact',
            'breadcrumbs' => [
                ['title' => 'Accueil', 'url' => '/'],
                ['title' => 'Contact', 'url' => '/contact']
            ]
        ]);
    }

    /**
     * Page Politique de confidentialité
     */
    public function privacy(): void
    {
        $this->render('pages/privacy', [
            'title' => 'Politique de confidentialité',
            'breadcrumbs' => [
                ['title' => 'Accueil', 'url' => '/'],
                ['title' => 'Confidentialité', 'url' => '/privacy']
            ]
        ]);
    }

    /**
     * Page Conditions d'utilisation
     */
    public function terms(): void
    {
        $this->render('pages/terms', [
            'title' => 'Conditions d\'utilisation',
            'breadcrumbs' => [
                ['title' => 'Accueil', 'url' => '/'],
                ['title' => 'Conditions', 'url' => '/terms']
            ]
        ]);
    }

    /**
     * Gestion d'erreur centralisée
     */
    private function handleError(\Exception $e, string $message): void
    {
        error_log($message . ': ' . $e->getMessage());

        // En développement, afficher l'erreur
        if ($_ENV['APP_DEBUG'] ?? false) {
            throw $e;
        }

        // En production, afficher une page d'erreur générique
        $this->render('home/index', [
            'title' => 'TopoclimbCH',
            'description' => 'Escalade en Suisse',
            'stats' => [
                'regions_count' => '0',
                'sites_count' => '0',
                'sectors_count' => '0',
                'routes_count' => '0',
                'books_count' => '0',
                'users_count' => '0',
                'ascents_count' => '0',
                'active_users_month' => '0',
                'new_routes_month' => '0',
                'photos_count' => '0'
            ],
            'popular_sectors' => [],
            'recent_books' => [],
            'trending_routes' => []
        ]);
    }
}
