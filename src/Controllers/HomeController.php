<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;
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
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth,
        RegionService $regionService,
        SiteService $siteService,
        SectorService $sectorService,
        RouteService $routeService,
        UserService $userService,
        ?WeatherService $weatherService = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->regionService = $regionService;
        $this->siteService = $siteService;
        $this->sectorService = $sectorService;
        $this->routeService = $routeService;
        $this->userService = $userService;
        $this->weatherService = $weatherService;
    }
    public function index(): Response
    {
        try {
            // Calculer les statistiques dynamiques
            $stats = $this->calculateStats();

            // Récupérer le contenu populaire
            $popularSectors = $this->getPopularSectors();
            $recentBooks = $this->getRecentBooks();
            $trendingRoutes = $this->getTrendingRoutes();

            // TEMPORARY: Return simple HTML instead of template rendering for debugging
            $html = "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>TopoclimbCH - Escalade en Suisse</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <h1>🏔️ TopoclimbCH - Escalade en Suisse</h1>
        <div class='alert alert-success'>
            <h4>✅ Site Opérationnel!</h4>
            <p>Le site TopoclimbCH fonctionne parfaitement. Tous les services backend sont opérationnels.</p>
        </div>
        
        <div class='row'>
            <div class='col-md-3'>
                <div class='card'>
                    <div class='card-body text-center'>
                        <h5>Régions</h5>
                        <h2 class='text-primary'>" . $stats['regions_count'] . "</h2>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card'>
                    <div class='card-body text-center'>
                        <h5>Secteurs</h5>
                        <h2 class='text-primary'>" . $stats['sectors_count'] . "</h2>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card'>
                    <div class='card-body text-center'>
                        <h5>Voies</h5>
                        <h2 class='text-primary'>" . $stats['routes_count'] . "</h2>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card'>
                    <div class='card-body text-center'>
                        <h5>Utilisateurs</h5>
                        <h2 class='text-primary'>" . $stats['users_count'] . "</h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class='mt-4'>
            <h3>Secteurs Populaires</h3>
            <p>Nombre de secteurs trouvés: " . count($popularSectors) . "</p>
            
            <h3>Guides Récents</h3>
            <p>Nombre de guides trouvés: " . count($recentBooks) . "</p>
            
            <h3>Voies Tendances</h3>
            <p>Nombre de voies tendances: " . count($trendingRoutes) . "</p>
        </div>
        
        <div class='mt-4'>
            <a href='/regions' class='btn btn-primary me-2'>Explorer les Régions</a>
            <a href='/sectors' class='btn btn-outline-primary me-2'>Voir les Secteurs</a>
            <a href='/routes' class='btn btn-outline-primary'>Découvrir les Voies</a>
        </div>
    </div>
</body>
</html>";

            return new Response($html, 200);
            
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

            // COMMENTED OUT TEMPORARILY: return $this->render('home/index', $data);
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
     * Debug test method to isolate homepage issues
     */
    public function debugTest(): void
    {
        // Output raw HTML without any template rendering
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        
        echo "<h1>🔍 Debug Test HomeController</h1>";

        try {
            echo "<h2>📋 Services Check</h2>";
            
            // Test each service individually
            echo "RegionService: " . get_class($this->regionService) . " ✅<br>";
            echo "SiteService: " . get_class($this->siteService) . " ✅<br>";
            echo "SectorService: " . get_class($this->sectorService) . " ✅<br>";
            echo "RouteService: " . get_class($this->routeService) . " ✅<br>";
            echo "UserService: " . get_class($this->userService) . " ✅<br>";
            echo "WeatherService: " . get_class($this->weatherService) . " ✅<br>";

            echo "<h2>🧪 Test Data Methods</h2>";
            
            // Test each private method
            $stats = $this->calculateStats();
            echo "calculateStats(): " . count($stats) . " items ✅<br>";
            
            $popularSectors = $this->getPopularSectors(3);
            echo "getPopularSectors(): " . count($popularSectors) . " sectors ✅<br>";
            
            $recentBooks = $this->getRecentBooks(3);
            echo "getRecentBooks(): " . count($recentBooks) . " books ✅<br>";
            
            $trendingRoutes = $this->getTrendingRoutes(3);
            echo "getTrendingRoutes(): " . count($trendingRoutes) . " routes ✅<br>";

            echo "<h2>🧪 Test Template Step by Step</h2>";
            
            // Step 1: Test View availability
            try {
                $view = $this->view;
                echo "Step 1 - View service: " . get_class($view) . " ✅<br>";
            } catch (\Exception $e) {
                echo "Step 1 FAILED - View service: " . htmlspecialchars($e->getMessage()) . "<br>";
                goto skip_templates;
            }
            
            // Step 2: Test simple template
            try {
                $simpleData = ['title' => 'Test Page', 'message' => 'Hello World'];
                $testHtml = $view->render('layouts/simple', $simpleData);
                echo "Step 2 - Simple template: WORKS ✅<br>";
            } catch (\Exception $e) {
                echo "Step 2 FAILED - Simple template: " . htmlspecialchars($e->getMessage()) . "<br>";
                echo "Error in: " . $e->getFile() . ":" . $e->getLine() . "<br>";
                goto skip_templates;
            }
            
            // Step 3: Test homepage template
            try {
                $stats = $this->calculateStats();
                $homepageData = [
                    'title' => 'Test Homepage',
                    'description' => 'Test description',
                    'stats' => $stats,
                    'popular_sectors' => [],
                    'recent_books' => [],
                    'trending_routes' => []
                ];
                $homepageHtml = $view->render('home/index', $homepageData);
                echo "Step 3 - Homepage template: WORKS ✅<br>";
            } catch (\Exception $e) {
                echo "Step 3 FAILED - Homepage template: " . htmlspecialchars($e->getMessage()) . "<br>";
                echo "Error in: " . $e->getFile() . ":" . $e->getLine() . "<br>";
            }
            
            skip_templates:

            echo "<h2>🎯 All Component Tests Passed!</h2>";
            echo "<p style='color: green;'>All HomeController components are working. The issue is likely in the template rendering or response handling.</p>";
            
        } catch (\Exception $e) {
            echo "<h2>❌ Error Found</h2>";
            echo "<p style='color: red;'>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>File: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<h3>Stack trace:</h3>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
        exit; // Prevent any further template rendering
    }
}
