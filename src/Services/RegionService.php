<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;

class RegionService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get regions with advanced filtering and sorting
     */
    public function getRegionsWithFilters(array $filters): array
    {
        $sql = "
            SELECT r.*, 
                   c.name as country_name,
                   c.code as country_code,
                   COUNT(DISTINCT s.id) as sectors_count,
                   COUNT(DISTINCT ro.id) as routes_count,
                   AVG(CAST(ro.difficulty AS DECIMAL(4,2))) as avg_difficulty,
                   MAX(s.altitude) as max_altitude,
                   MIN(s.altitude) as min_altitude
            FROM climbing_regions r
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            WHERE r.active = 1
        ";

        $params = [];

        // Apply filters
        if (!empty($filters['country_id'])) {
            $sql .= " AND r.country_id = ?";
            $params[] = $filters['country_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ? OR c.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['difficulty'])) {
            switch ($filters['difficulty']) {
                case 'beginner':
                    $sql .= " AND EXISTS (
                        SELECT 1 FROM climbing_routes r2 
                        JOIN climbing_sectors s2 ON r2.sector_id = s2.id 
                        WHERE s2.region_id = r.id 
                        AND CAST(r2.difficulty AS DECIMAL(4,2)) BETWEEN 3 AND 5
                    )";
                    break;
                case 'intermediate':
                    $sql .= " AND EXISTS (
                        SELECT 1 FROM climbing_routes r2 
                        JOIN climbing_sectors s2 ON r2.sector_id = s2.id 
                        WHERE s2.region_id = r.id 
                        AND CAST(r2.difficulty AS DECIMAL(4,2)) BETWEEN 5 AND 6.5
                    )";
                    break;
                case 'advanced':
                    $sql .= " AND EXISTS (
                        SELECT 1 FROM climbing_routes r2 
                        JOIN climbing_sectors s2 ON r2.sector_id = s2.id 
                        WHERE s2.region_id = r.id 
                        AND CAST(r2.difficulty AS DECIMAL(4,2)) > 6.5
                    )";
                    break;
            }
        }

        if (!empty($filters['season'])) {
            if ($filters['season'] !== 'year-round') {
                $sql .= " AND r.best_season = ?";
                $params[] = $filters['season'];
            }
        }

        $sql .= " GROUP BY r.id";

        // Apply sorting
        $sortOrder = $this->buildSortOrder($filters);
        $sql .= " ORDER BY " . $sortOrder;

        $results = $this->db->query($sql, $params);

        return array_map(function ($row) {
            return $this->enrichRegionData($row);
        }, $results);
    }

    /**
     * Get region with all related data
     */
    public function getRegionWithAllRelations(int $id): ?Region
    {
        $region = $this->getRegion($id);
        if (!$region) {
            return null;
        }

        // Load country
        $country = $this->db->fetchOne(
            "SELECT * FROM climbing_countries WHERE id = ?",
            [$region->country_id]
        );
        if ($country) {
            $region->country = (object) $country;
        }

        return $region;
    }

    /**
     * Get region sectors with statistics
     */
    public function getRegionSectorsWithStats(int $regionId): array
    {
        $sql = "
            SELECT s.*,
                   COUNT(DISTINCT r.id) as routes_count,
                   MIN(CAST(r.difficulty AS DECIMAL(4,2))) as min_difficulty,
                   MAX(CAST(r.difficulty AS DECIMAL(4,2))) as max_difficulty,
                   AVG(CAST(r.difficulty AS DECIMAL(4,2))) as avg_difficulty,
                   GROUP_CONCAT(DISTINCT r.style) as styles,
                   GROUP_CONCAT(DISTINCT se.exposure_id) as exposures
            FROM climbing_sectors s
            LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
            LEFT JOIN climbing_sector_exposures se ON s.id = se.sector_id
            WHERE s.region_id = ? AND s.active = 1
            GROUP BY s.id
            ORDER BY s.name
        ";

        $results = $this->db->query($sql, [$regionId]);

        return array_map(function ($row) {
            // Format difficulty range
            if ($row['min_difficulty'] && $row['max_difficulty']) {
                if ($row['min_difficulty'] == $row['max_difficulty']) {
                    $row['difficulty_range'] = (string) $row['min_difficulty'];
                } else {
                    $row['difficulty_range'] = $row['min_difficulty'] . ' - ' . $row['max_difficulty'];
                }
            }

            // Parse styles
            $row['climbing_styles'] = !empty($row['styles']) ?
                array_unique(explode(',', $row['styles'])) : [];

            // Get exposure names
            if (!empty($row['exposures'])) {
                $exposureIds = explode(',', $row['exposures']);
                $exposureNames = $this->getExposureNames($exposureIds);
                $row['exposure'] = implode(', ', $exposureNames);
            }

            return $row;
        }, $results);
    }

    /**
     * Get detailed statistics for a region
     */
    public function getRegionDetailedStatistics(int $regionId): array
    {
        $stats = [];

        // Basic counts
        $counts = $this->db->fetchOne("
            SELECT 
                COUNT(DISTINCT s.id) as sectors_count,
                COUNT(DISTINCT r.id) as routes_count,
                COUNT(DISTINCT CASE WHEN r.style = 'sport' THEN r.id END) as sport_routes,
                COUNT(DISTINCT CASE WHEN r.style = 'trad' THEN r.id END) as trad_routes,
                COUNT(DISTINCT CASE WHEN r.style = 'boulder' THEN r.id END) as boulder_routes
            FROM climbing_sectors s
            LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
            WHERE s.region_id = ? AND s.active = 1
        ", [$regionId]);

        $stats = array_merge($stats, $counts ?: []);

        // Difficulty distribution
        $difficulties = $this->db->query("
            SELECT 
                CASE 
                    WHEN CAST(r.difficulty AS DECIMAL(4,2)) <= 5 THEN 'beginner'
                    WHEN CAST(r.difficulty AS DECIMAL(4,2)) <= 6.5 THEN 'intermediate'
                    ELSE 'advanced'
                END as difficulty_level,
                COUNT(*) as count
            FROM climbing_routes r
            JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.region_id = ? AND r.active = 1 AND s.active = 1
            GROUP BY difficulty_level
        ", [$regionId]);

        $stats['difficulty_distribution'] = [];
        foreach ($difficulties as $diff) {
            $stats['difficulty_distribution'][$diff['difficulty_level']] = (int) $diff['count'];
        }

        // Average difficulty
        $avgDiff = $this->db->fetchOne("
            SELECT AVG(CAST(r.difficulty AS DECIMAL(4,2))) as avg_difficulty
            FROM climbing_routes r
            JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.region_id = ? AND r.active = 1 AND s.active = 1
            AND r.difficulty IS NOT NULL AND r.difficulty != ''
        ", [$regionId]);

        if ($avgDiff && $avgDiff['avg_difficulty']) {
            $stats['avg_difficulty'] = round($avgDiff['avg_difficulty'], 1);
        }

        // Altitude range
        $altitudes = $this->db->fetchOne("
            SELECT 
                MIN(altitude) as min_altitude,
                MAX(altitude) as max_altitude,
                AVG(altitude) as avg_altitude
            FROM climbing_sectors 
            WHERE region_id = ? AND active = 1 AND altitude IS NOT NULL
        ", [$regionId]);

        if ($altitudes) {
            $stats = array_merge($stats, $altitudes);
        }

        // Most common exposures
        $exposures = $this->db->query("
            SELECT e.name, e.code, COUNT(*) as count
            FROM climbing_sector_exposures se
            JOIN climbing_exposures e ON se.exposure_id = e.id
            JOIN climbing_sectors s ON se.sector_id = s.id
            WHERE s.region_id = ? AND s.active = 1
            GROUP BY e.id
            ORDER BY count DESC
            LIMIT 3
        ", [$regionId]);

        $stats['main_exposures'] = $exposures;

        return $stats;
    }

    /**
     * Get overall statistics for all regions
     */
    public function getOverallStatistics(): array
    {
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(DISTINCT r.id) as total_regions,
                COUNT(DISTINCT s.id) as total_sectors,
                COUNT(DISTINCT ro.id) as total_routes,
                COUNT(DISTINCT c.id) as total_countries
            FROM climbing_regions r
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            WHERE r.active = 1
        ");

        return $stats ?: [
            'total_regions' => 0,
            'total_sectors' => 0,
            'total_routes' => 0,
            'total_countries' => 0
        ];
    }

    /**
     * Get popular regions by activity
     */
    public function getPopularRegions(int $limit = 5): array
    {
        $sql = "
            SELECT r.*, 
                   c.name as country_name,
                   COUNT(DISTINCT s.id) as sectors_count,
                   COUNT(DISTINCT ro.id) as routes_count
            FROM climbing_regions r
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            WHERE r.active = 1
            GROUP BY r.id
            ORDER BY routes_count DESC, sectors_count DESC
            LIMIT ?
        ";

        return $this->db->query($sql, [$limit]);
    }

    /**
     * Get active countries
     */
    public function getActiveCountries(): array
    {
        return $this->db->query("
            SELECT c.*, COUNT(r.id) as regions_count
            FROM climbing_countries c
            LEFT JOIN climbing_regions r ON c.id = r.country_id AND r.active = 1
            WHERE c.active = 1
            GROUP BY c.id
            ORDER BY c.name
        ");
    }

    /**
     * Get regions for API responses (lightweight)
     */
    public function getRegionsForApi(array $filters): array
    {
        $sql = "
            SELECT r.id, r.name, r.coordinates_lat, r.coordinates_lng,
                   c.name as country_name,
                   COUNT(DISTINCT s.id) as sectors_count,
                   COUNT(DISTINCT ro.id) as routes_count
            FROM climbing_regions r
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            WHERE r.active = 1
        ";

        $params = [];

        if (!empty($filters['country_id'])) {
            $sql .= " AND r.country_id = ?";
            $params[] = $filters['country_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND r.name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " GROUP BY r.id ORDER BY r.name";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }

        return $this->db->query($sql, $params);
    }

    /**
     * Search regions for autocomplete
     */
    public function searchRegions(string $query, int $limit = 10): array
    {
        $sql = "
            SELECT r.id, r.name, c.name as country_name,
                   COUNT(DISTINCT s.id) as sectors_count
            FROM climbing_regions r
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            WHERE r.active = 1 
            AND (r.name LIKE ? OR c.name LIKE ?)
            GROUP BY r.id
            ORDER BY 
                CASE WHEN r.name LIKE ? THEN 1 ELSE 2 END,
                sectors_count DESC,
                r.name
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';
        $exactTerm = $query . '%';

        return $this->db->query($sql, [$searchTerm, $searchTerm, $exactTerm, $limit]);
    }

    /**
     * Get upcoming events for a region
     */
    public function getUpcomingEvents(int $regionId, int $limit = 10): array
    {
        $sql = "
            SELECT e.*, 
                   COUNT(ep.id) as participants_count
            FROM climbing_events e
            LEFT JOIN climbing_event_participants ep ON e.id = ep.event_id 
                AND ep.status IN ('registered', 'confirmed')
            LEFT JOIN climbing_event_locations el ON e.id = el.event_id
            LEFT JOIN climbing_sectors s ON el.entity_type = 'sector' AND el.entity_id = s.id
            WHERE s.region_id = ?
            AND e.start_datetime > NOW()
            AND e.status = 'published'
            GROUP BY e.id
            ORDER BY e.start_datetime
            LIMIT ?
        ";

        $events = $this->db->query($sql, [$regionId, $limit]);

        return array_map(function ($event) {
            $event['formatted_date'] = date('d M Y', strtotime($event['start_datetime']));
            return $event;
        }, $events);
    }

    /**
     * Get related regions (same country, similar characteristics)
     */
    public function getRelatedRegions(Region $region, int $limit = 4): array
    {
        $sql = "
            SELECT r.*, c.name as country_name,
                   COUNT(DISTINCT s.id) as sectors_count,
                   ABS(r.altitude - ?) as altitude_diff
            FROM climbing_regions r
            LEFT JOIN climbing_countries c ON r.country_id = c.id
            LEFT JOIN climbing_sectors s ON r.id = s.region_id AND s.active = 1
            WHERE r.active = 1 
            AND r.id != ?
            AND (r.country_id = ? OR r.altitude BETWEEN ? AND ?)
            GROUP BY r.id
            ORDER BY 
                CASE WHEN r.country_id = ? THEN 1 ELSE 2 END,
                altitude_diff,
                sectors_count DESC
            LIMIT ?
        ";

        $altitudeRange = 500; // meters
        $altitude = $region->altitude ?: 1000;

        return $this->db->query($sql, [
            $altitude,
            $region->id,
            $region->country_id,
            $altitude - $altitudeRange,
            $altitude + $altitudeRange,
            $region->country_id,
            $limit
        ]);
    }

    /**
     * Get region parking areas
     */
    public function getRegionParking(int $regionId): array
    {
        $sql = "
            SELECT p.*, ps.distance_metres, ps.temps_marche, ps.difficulte_acces
            FROM parking p
            JOIN parking_secteur ps ON p.id = ps.parking_id
            JOIN climbing_sectors s ON ps.secteur_id = s.id
            WHERE s.region_id = ?
            GROUP BY p.id
            ORDER BY p.nom
        ";

        return $this->db->query($sql, [$regionId]);
    }

    /**
     * Get sectors with coordinates for export
     */
    public function getRegionSectorsWithCoordinates(int $regionId): array
    {
        $sql = "
            SELECT s.*, COUNT(r.id) as routes_count
            FROM climbing_sectors s
            LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
            WHERE s.region_id = ? 
            AND s.active = 1 
            AND s.coordinates_lat IS NOT NULL 
            AND s.coordinates_lng IS NOT NULL
            GROUP BY s.id
            ORDER BY s.name
        ";

        return $this->db->query($sql, [$regionId]);
    }

    /**
     * Check dependencies before deletion
     */
    public function checkDependencies(int $regionId): array
    {
        $dependencies = [];

        // Check sectors
        $sectors = $this->db->query(
            "SELECT id, name FROM climbing_sectors WHERE region_id = ? AND active = 1 LIMIT 5",
            [$regionId]
        );
        $dependencies['sectors'] = $sectors;

        // Check routes
        $routes = $this->db->query("
            SELECT r.id, r.name, s.name as sector_name
            FROM climbing_routes r
            JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.region_id = ? AND r.active = 1 AND s.active = 1
            LIMIT 5
        ", [$regionId]);
        $dependencies['routes'] = $routes;

        return $dependencies;
    }

    /**
     * Create new region
     */
    public function createRegion(array $data): Region
    {
        // Ensure boolean values are properly set
        $data['active'] = isset($data['active']) ? 1 : 0;

        $region = new Region();
        $region->fill($data);
        $region->save();

        return $region;
    }

    /**
     * Update existing region
     */
    public function updateRegion(Region $region, array $data): Region
    {
        // Ensure boolean values are properly set
        $data['active'] = isset($data['active']) ? 1 : 0;

        $region->fill($data);
        $region->save();

        return $region;
    }

    /**
     * Delete region
     */
    public function deleteRegion(Region $region): bool
    {
        return $region->delete();
    }

    /**
     * Get single region
     */
    public function getRegion(int $id): ?Region
    {
        return Region::find($id);
    }

    /**
     * Generate GPX export
     */
    public function generateGpxExport(Region $region, array $sectors): string
    {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $gpx .= '<gpx version="1.1" creator="TopoclimbCH" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
        $gpx .= '  <metadata>' . "\n";
        $gpx .= '    <name>' . htmlspecialchars($region->name) . '</name>' . "\n";
        $gpx .= '    <desc>' . htmlspecialchars($region->description ?: 'Région d\'escalade') . '</desc>' . "\n";
        $gpx .= '    <time>' . date('c') . '</time>' . "\n";
        $gpx .= '  </metadata>' . "\n";

        foreach ($sectors as $sector) {
            $gpx .= '  <wpt lat="' . $sector['coordinates_lat'] . '" lon="' . $sector['coordinates_lng'] . '">' . "\n";
            $gpx .= '    <name>' . htmlspecialchars($sector['name']) . '</name>' . "\n";
            $gpx .= '    <desc>' . htmlspecialchars($sector['description'] ?: '') . '</desc>' . "\n";
            if ($sector['altitude']) {
                $gpx .= '    <ele>' . $sector['altitude'] . '</ele>' . "\n";
            }
            $gpx .= '    <type>climbing_sector</type>' . "\n";
            $gpx .= '  </wpt>' . "\n";
        }

        $gpx .= '</gpx>';

        return $gpx;
    }

    /**
     * Generate KML export
     */
    public function generateKmlExport(Region $region, array $sectors): string
    {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n";
        $kml .= '  <Document>' . "\n";
        $kml .= '    <name>' . htmlspecialchars($region->name) . '</name>' . "\n";
        $kml .= '    <description>' . htmlspecialchars($region->description ?: 'Région d\'escalade') . '</description>' . "\n";

        foreach ($sectors as $sector) {
            $kml .= '    <Placemark>' . "\n";
            $kml .= '      <name>' . htmlspecialchars($sector['name']) . '</name>' . "\n";
            $kml .= '      <description>' . htmlspecialchars($sector['description'] ?: '') . '</description>' . "\n";
            $kml .= '      <Point>' . "\n";
            $kml .= '        <coordinates>' . $sector['coordinates_lng'] . ',' . $sector['coordinates_lat'];
            if ($sector['altitude']) {
                $kml .= ',' . $sector['altitude'];
            }
            $kml .= '</coordinates>' . "\n";
            $kml .= '      </Point>' . "\n";
            $kml .= '    </Placemark>' . "\n";
        }

        $kml .= '  </Document>' . "\n";
        $kml .= '</kml>';

        return $kml;
    }

    /**
     * Generate GeoJSON export
     */
    public function generateGeoJsonExport(Region $region, array $sectors): array
    {
        $features = [];

        foreach ($sectors as $sector) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $sector['coordinates_lng'],
                        (float) $sector['coordinates_lat']
                    ]
                ],
                'properties' => [
                    'name' => $sector['name'],
                    'description' => $sector['description'] ?: '',
                    'altitude' => $sector['altitude'] ? (int) $sector['altitude'] : null,
                    'routes_count' => (int) $sector['routes_count'],
                    'type' => 'climbing_sector'
                ]
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'properties' => [
                'region' => [
                    'name' => $region->name,
                    'description' => $region->description,
                    'country_id' => $region->country_id
                ],
                'generated_at' => date('c')
            ]
        ];
    }

    /**
     * Get comprehensive region data for export
     */
    public function getRegionExportData(int $regionId): array
    {
        $region = $this->getRegionWithAllRelations($regionId);
        $sectors = $this->getRegionSectorsWithStats($regionId);
        $stats = $this->getRegionDetailedStatistics($regionId);

        return [
            'region' => $region->toArray(),
            'sectors' => $sectors,
            'statistics' => $stats,
            'exported_at' => date('c')
        ];
    }

    // Helper methods

    protected function buildSortOrder(array $filters): string
    {
        $sort = $filters['sort'] ?? 'name';
        $order = strtoupper($filters['order'] ?? 'asc');

        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }

        switch ($sort) {
            case 'routes_count':
                return "routes_count {$order}, r.name ASC";
            case 'sectors_count':
                return "sectors_count {$order}, r.name ASC";
            case 'country':
                return "c.name {$order}, r.name ASC";
            case 'altitude':
                return "max_altitude {$order}, r.name ASC";
            case 'difficulty':
                return "avg_difficulty {$order}, r.name ASC";
            default:
                return "r.name {$order}";
        }
    }

    protected function enrichRegionData(array $row): array
    {
        // Add computed properties
        $row['difficulty_level'] = $this->getDifficultyLevel($row['avg_difficulty'] ?? 0);

        // Format altitude
        if ($row['max_altitude'] && $row['min_altitude']) {
            if ($row['max_altitude'] == $row['min_altitude']) {
                $row['altitude_display'] = $row['max_altitude'] . 'm';
            } else {
                $row['altitude_display'] = $row['min_altitude'] . '-' . $row['max_altitude'] . 'm';
            }
        }

        // Add country object for compatibility
        if ($row['country_name']) {
            $row['country'] = [
                'name' => $row['country_name'],
                'code' => $row['country_code']
            ];
        }

        return $row;
    }

    protected function getDifficultyLevel(float $avgDifficulty): string
    {
        if ($avgDifficulty <= 5) return 'beginner';
        if ($avgDifficulty <= 6.5) return 'intermediate';
        return 'advanced';
    }

    protected function getExposureNames(array $exposureIds): array
    {
        if (empty($exposureIds)) return [];

        $placeholders = str_repeat('?,', count($exposureIds) - 1) . '?';
        $sql = "SELECT name FROM climbing_exposures WHERE id IN ({$placeholders})";

        $results = $this->db->query($sql, $exposureIds);

        return array_column($results, 'name');
    }
}
