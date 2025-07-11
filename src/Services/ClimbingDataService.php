<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;
// use TopoclimbCH\Models\Book; // Modèle Book pas encore créé

/**
 * Service pour la gestion des données d'escalade
 * Centralise les opérations complexes sur les données d'escalade
 */
class ClimbingDataService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        
        // Injecter la base de données dans les modèles
        Region::setDatabase($db);
        Site::setDatabase($db);
        Sector::setDatabase($db);
        Route::setDatabase($db);
        // Book::setDatabase($db); // Modèle Book pas encore créé
    }

    /**
     * Récupère les statistiques globales d'escalade
     */
    public function getGlobalStats(): array
    {
        try {
            $stats = [
                'regions_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions WHERE active = 1")['count'] ?? 0,
                'sites_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_sites WHERE active = 1")['count'] ?? 0,
                'sectors_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors WHERE active = 1")['count'] ?? 0,
                'routes_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_routes WHERE active = 1")['count'] ?? 0,
                'books_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_books WHERE active = 1")['count'] ?? 0,
                'users_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0,
                'ascents_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM user_ascents")['count'] ?? 0,
            ];

            return $stats;
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des stats par défaut
            return [
                'regions_count' => 0,
                'sites_count' => 0,
                'sectors_count' => 0,
                'routes_count' => 0,
                'books_count' => 0,
                'users_count' => 0,
                'ascents_count' => 0,
            ];
        }
    }

    /**
     * Recherche de données d'escalade par terme
     */
    public function searchClimbingData(string $query, array $filters = []): array
    {
        try {
            $results = [
                'regions' => [],
                'sites' => [],
                'sectors' => [],
                'routes' => [],
                'books' => []
            ];

            // Recherche dans les régions
            $regions = $this->db->fetchAll(
                "SELECT * FROM climbing_regions WHERE name LIKE ? AND active = 1 LIMIT 10",
                ["%{$query}%"]
            );
            $results['regions'] = $regions;

            // Recherche dans les sites
            $sites = $this->db->fetchAll(
                "SELECT * FROM climbing_sites WHERE name LIKE ? AND active = 1 LIMIT 10",
                ["%{$query}%"]
            );
            $results['sites'] = $sites;

            // Recherche dans les secteurs
            $sectors = $this->db->fetchAll(
                "SELECT * FROM climbing_sectors WHERE name LIKE ? AND active = 1 LIMIT 10",
                ["%{$query}%"]
            );
            $results['sectors'] = $sectors;

            // Recherche dans les voies
            $routes = $this->db->fetchAll(
                "SELECT * FROM climbing_routes WHERE name LIKE ? AND active = 1 LIMIT 10",
                ["%{$query}%"]
            );
            $results['routes'] = $routes;

            // Recherche dans les guides
            $books = $this->db->fetchAll(
                "SELECT * FROM climbing_books WHERE title LIKE ? AND active = 1 LIMIT 10",
                ["%{$query}%"]
            );
            $results['books'] = $books;

            return $results;
        } catch (\Exception $e) {
            error_log("Erreur recherche ClimbingDataService: " . $e->getMessage());
            return [
                'regions' => [],
                'sites' => [],
                'sectors' => [],
                'routes' => [],
                'books' => []
            ];
        }
    }

    /**
     * Récupère les données d'escalade populaires
     */
    public function getPopularData(): array
    {
        try {
            // Secteurs populaires (avec le plus d'ascensions)
            $popularSectors = $this->db->fetchAll("
                SELECT 
                    s.*,
                    COUNT(ua.id) as ascents_count,
                    AVG(ua.quality_rating) as avg_rating
                FROM climbing_sectors s
                LEFT JOIN climbing_routes r ON s.id = r.sector_id
                LEFT JOIN user_ascents ua ON r.id = ua.route_id
                WHERE s.active = 1
                GROUP BY s.id
                ORDER BY ascents_count DESC, avg_rating DESC
                LIMIT 6
            ");

            // Voies tendances (escaladées récemment)
            $trendingRoutes = $this->db->fetchAll("
                SELECT 
                    r.*,
                    COUNT(ua.id) as recent_ascents,
                    AVG(ua.quality_rating) as avg_rating
                FROM climbing_routes r
                LEFT JOIN user_ascents ua ON r.id = ua.route_id 
                    AND ua.ascent_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                WHERE r.active = 1
                GROUP BY r.id
                HAVING recent_ascents > 0
                ORDER BY recent_ascents DESC, avg_rating DESC
                LIMIT 6
            ");

            // Guides récents
            $recentBooks = $this->db->fetchAll("
                SELECT * FROM climbing_books 
                WHERE active = 1
                ORDER BY created_at DESC, year DESC
                LIMIT 6
            ");

            return [
                'popular_sectors' => $popularSectors,
                'trending_routes' => $trendingRoutes,
                'recent_books' => $recentBooks
            ];
        } catch (\Exception $e) {
            error_log("Erreur getPopularData ClimbingDataService: " . $e->getMessage());
            return [
                'popular_sectors' => [],
                'trending_routes' => [],
                'recent_books' => []
            ];
        }
    }

    /**
     * Vérifie l'intégrité des données d'escalade
     */
    public function validateDataIntegrity(): array
    {
        $issues = [];

        try {
            // Vérifier les sites sans coordonnées
            $sitesWithoutCoords = $this->db->fetchAll(
                "SELECT id, name FROM climbing_sites WHERE (latitude IS NULL OR longitude IS NULL) AND active = 1"
            );
            if (!empty($sitesWithoutCoords)) {
                $issues[] = "Sites sans coordonnées: " . count($sitesWithoutCoords);
            }

            // Vérifier les secteurs sans voies
            $sectorsWithoutRoutes = $this->db->fetchAll("
                SELECT s.id, s.name 
                FROM climbing_sectors s
                LEFT JOIN climbing_routes r ON s.id = r.sector_id
                WHERE r.id IS NULL AND s.active = 1
            ");
            if (!empty($sectorsWithoutRoutes)) {
                $issues[] = "Secteurs sans voies: " . count($sectorsWithoutRoutes);
            }

            // Vérifier les voies sans difficulté
            $routesWithoutGrade = $this->db->fetchAll(
                "SELECT id, name FROM climbing_routes WHERE difficulty_grade IS NULL AND active = 1"
            );
            if (!empty($routesWithoutGrade)) {
                $issues[] = "Voies sans cotation: " . count($routesWithoutGrade);
            }

        } catch (\Exception $e) {
            $issues[] = "Erreur lors de la validation: " . $e->getMessage();
        }

        return $issues;
    }

    /**
     * Export des données d'escalade
     */
    public function exportClimbingData(string $format = 'json', array $filters = []): string
    {
        try {
            $data = [
                'export_date' => date('Y-m-d H:i:s'),
                'stats' => $this->getGlobalStats(),
                'regions' => Region::all(),
                'sites' => Site::all(),
                'sectors' => Sector::all(),
                'routes' => Route::all(),
                // 'books' => Book::all() // Modèle Book pas encore créé
            ];

            switch ($format) {
                case 'json':
                    return json_encode($data, JSON_PRETTY_PRINT);
                case 'csv':
                    // Implémentation CSV simplifiée
                    return $this->exportToCsv($data);
                default:
                    return json_encode($data);
            }
        } catch (\Exception $e) {
            error_log("Erreur export ClimbingDataService: " . $e->getMessage());
            return json_encode(['error' => 'Erreur lors de l\'export']);
        }
    }

    /**
     * Export CSV simplifié
     */
    private function exportToCsv(array $data): string
    {
        $csv = "Type,ID,Name,Description\n";
        
        foreach ($data['regions'] as $region) {
            $csv .= "Region," . $region['id'] . "," . $region['name'] . "," . ($region['description'] ?? '') . "\n";
        }
        
        foreach ($data['sites'] as $site) {
            $csv .= "Site," . $site['id'] . "," . $site['name'] . "," . ($site['description'] ?? '') . "\n";
        }
        
        return $csv;
    }

    /**
     * Récupère les données pour une région spécifique
     */
    public function getRegionData(int $regionId): array
    {
        try {
            $region = Region::find($regionId);
            if (!$region) {
                return ['error' => 'Région non trouvée'];
            }

            $sites = Site::where('region_id', $regionId)->get();
            $sectors = [];
            $routes = [];

            foreach ($sites as $site) {
                $siteSectors = Sector::where('site_id', $site['id'])->get();
                $sectors = array_merge($sectors, $siteSectors);
                
                foreach ($siteSectors as $sector) {
                    $sectorRoutes = Route::where('sector_id', $sector['id'])->get();
                    $routes = array_merge($routes, $sectorRoutes);
                }
            }

            return [
                'region' => $region,
                'sites' => $sites,
                'sectors' => $sectors,
                'routes' => $routes,
                'stats' => [
                    'sites_count' => count($sites),
                    'sectors_count' => count($sectors),
                    'routes_count' => count($routes)
                ]
            ];
        } catch (\Exception $e) {
            error_log("Erreur getRegionData ClimbingDataService: " . $e->getMessage());
            return ['error' => 'Erreur lors de la récupération des données'];
        }
    }
}