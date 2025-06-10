<?php

declare(strict_types=1);

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * Sector Model - Secteurs d'escalade avec hiérarchie flexible
 * 
 * Un secteur peut être :
 * - Directement attaché à une région (site_id = NULL)
 * - Organisé dans un site (site_id = ID du site)
 */
class Sector extends Model
{
    protected static string $table = 'climbing_sectors';
    protected static string $primaryKey = 'id';

    protected array $fillable = [
        'book_id',
        'region_id',
        'site_id',
        'name',
        'code',
        'description',
        'access_info',
        'color',
        'access_time',
        'altitude',
        'approach',
        'height',
        'parking_info',
        'coordinates_lat',
        'coordinates_lng',
        'coordinates_swiss_e',
        'coordinates_swiss_n',
        'active'
    ];

    protected array $rules = [
        'region_id' => 'required|numeric',
        'site_id' => 'numeric', // Optionnel pour hiérarchie flexible
        'name' => 'required|min:2|max:255',
        'code' => 'required|min:1|max:50',
        'access_time' => 'numeric',
        'altitude' => 'numeric',
        'height' => 'numeric',
        'coordinates_lat' => 'numeric',
        'coordinates_lng' => 'numeric'
    ];

    /**
     * Relations
     */

    /**
     * Un secteur appartient à une région
     */
    public function region(): ?Region
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Un secteur peut appartenir à un site (optionnel)
     */
    public function site(): ?Site
    {
        if (!$this->site_id) {
            return null;
        }
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Un secteur peut appartenir à un book/topo
     */
    public function book(): ?Book
    {
        if (!$this->book_id) {
            return null;
        }
        return $this->belongsTo(Book::class, 'book_id');
    }

    /**
     * Un secteur a plusieurs voies
     */
    public function routes(): array
    {
        return $this->hasMany(Route::class, 'sector_id');
    }

    /**
     * Expositions du secteur (many-to-many)
     */
    public function exposures(): array
    {
        $sql = "
            SELECT e.*, se.is_primary, se.notes
            FROM climbing_exposures e
            INNER JOIN climbing_sector_exposures se ON e.id = se.exposure_id
            WHERE se.sector_id = ?
            ORDER BY se.is_primary DESC, e.sort_order
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Mois favorables du secteur (many-to-many)
     */
    public function months(): array
    {
        $sql = "
            SELECT m.*, sm.quality, sm.notes
            FROM climbing_months m
            INNER JOIN climbing_sector_months sm ON m.id = sm.month_id
            WHERE sm.sector_id = ?
            ORDER BY m.month_number
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Books qui incluent ce secteur
     */
    public function books(): array
    {
        $sql = "
            SELECT b.*, bs.sort_order, bs.is_complete, bs.notes
            FROM climbing_books b
            INNER JOIN climbing_book_sectors bs ON b.id = bs.book_id
            WHERE bs.sector_id = ? AND b.active = 1
            ORDER BY bs.sort_order, b.name
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Obtenir toutes les voies du secteur avec statistiques
     */
    public function getRoutesWithStats(): array
    {
        $sql = "
            SELECT r.*,
                   dg.numerical_value as difficulty_numeric,
                   ds.name as difficulty_system_name
            FROM climbing_routes r
            LEFT JOIN climbing_difficulty_grades dg ON r.difficulty = dg.value AND r.difficulty_system_id = dg.system_id
            LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
            WHERE r.sector_id = ? AND r.active = 1
            ORDER BY r.number, r.name
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Obtenir les statistiques du secteur
     */
    public function getStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_routes,
                AVG(CAST(beauty AS UNSIGNED)) as avg_beauty,
                MIN(dg.numerical_value) as min_difficulty_numeric,
                MAX(dg.numerical_value) as max_difficulty_numeric,
                MIN(r.difficulty) as min_difficulty,
                MAX(r.difficulty) as max_difficulty,
                AVG(r.length) as avg_length,
                COUNT(CASE WHEN r.style = 'sport' THEN 1 END) as sport_routes,
                COUNT(CASE WHEN r.style = 'trad' THEN 1 END) as trad_routes,
                COUNT(CASE WHEN r.style = 'boulder' THEN 1 END) as boulder_routes
            FROM climbing_routes r
            LEFT JOIN climbing_difficulty_grades dg ON r.difficulty = dg.value AND r.difficulty_system_id = dg.system_id
            WHERE r.sector_id = ? AND r.active = 1
        ";

        return $this->db->fetchOne($sql, [$this->id]);
    }

    /**
     * Vérifier si le secteur a des voies
     */
    public function hasRoutes(): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ? AND active = 1";
        $result = $this->db->fetchOne($sql, [$this->id]);
        return $result['count'] > 0;
    }

    /**
     * Obtenir la hiérarchie complète du secteur
     */
    public function getHierarchy(): array
    {
        $hierarchy = [];

        // Région (toujours présente)
        $region = $this->region();
        if ($region) {
            $hierarchy['region'] = [
                'id' => $region->id,
                'name' => $region->name,
                'type' => 'region'
            ];
        }

        // Site (optionnel)
        $site = $this->site();
        if ($site) {
            $hierarchy['site'] = [
                'id' => $site->id,
                'name' => $site->name,
                'type' => 'site'
            ];
        }

        // Secteur actuel
        $hierarchy['sector'] = [
            'id' => $this->id,
            'name' => $this->name,
            'type' => 'sector'
        ];

        return $hierarchy;
    }

    /**
     * Déplacer le secteur vers un autre site ou directement dans une région
     */
    public function moveTo(?int $newSiteId = null, ?int $newRegionId = null): bool
    {
        if ($newSiteId && $newRegionId) {
            throw new \InvalidArgumentException('Spécifiez soit un site, soit une région, pas les deux');
        }

        if (!$newSiteId && !$newRegionId) {
            throw new \InvalidArgumentException('Nouveau site ou nouvelle région requis');
        }

        try {
            if ($newSiteId) {
                // Vérifier que le site existe et obtenir sa région
                $site = Site::find($newSiteId);
                if (!$site) {
                    throw new \Exception('Site de destination non trouvé');
                }

                $this->site_id = $newSiteId;
                $this->region_id = $site->region_id;
            } else {
                // Déplacement direct vers une région
                $region = Region::find($newRegionId);
                if (!$region) {
                    throw new \Exception('Région de destination non trouvée');
                }

                $this->site_id = null;
                $this->region_id = $newRegionId;
            }

            return $this->save();
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du déplacement du secteur: ' . $e->getMessage());
        }
    }

    /**
     * Rechercher des secteurs
     */
    public static function search(string $query, ?int $regionId = null, ?int $siteId = null): array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   si.name as site_name,
                   COUNT(ro.id) as route_count
            FROM climbing_sectors s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites si ON s.site_id = si.id
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            WHERE s.active = 1 
            AND (s.name LIKE ? OR s.description LIKE ? OR s.code LIKE ?)
        ";

        $params = ["%{$query}%", "%{$query}%", "%{$query}%"];

        if ($regionId) {
            $sql .= " AND s.region_id = ?";
            $params[] = $regionId;
        }

        if ($siteId) {
            $sql .= " AND s.site_id = ?";
            $params[] = $siteId;
        }

        $sql .= " GROUP BY s.id ORDER BY s.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }

    /**
     * Obtenir les secteurs par critères avec hiérarchie
     */
    public static function getByCriteria(array $filters = []): array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   si.name as site_name,
                   COUNT(ro.id) as route_count,
                   AVG(CAST(ro.beauty AS UNSIGNED)) as avg_beauty
            FROM climbing_sectors s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites si ON s.site_id = si.id
            LEFT JOIN climbing_routes ro ON s.id = ro.sector_id AND ro.active = 1
            WHERE s.active = 1
        ";

        $params = [];

        // Filtrage par région
        if (!empty($filters['region_id'])) {
            $sql .= " AND s.region_id = ?";
            $params[] = $filters['region_id'];
        }

        // Filtrage par site
        if (!empty($filters['site_id'])) {
            $sql .= " AND s.site_id = ?";
            $params[] = $filters['site_id'];
        }

        // Secteurs sans site (directement dans région)
        if (isset($filters['no_site']) && $filters['no_site']) {
            $sql .= " AND s.site_id IS NULL";
        }

        // Filtrage par altitude
        if (!empty($filters['altitude_min'])) {
            $sql .= " AND s.altitude >= ?";
            $params[] = $filters['altitude_min'];
        }

        if (!empty($filters['altitude_max'])) {
            $sql .= " AND s.altitude <= ?";
            $params[] = $filters['altitude_max'];
        }

        $sql .= " GROUP BY s.id ORDER BY s.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }

    /**
     * Accesseurs
     */

    public function getFullNameAttribute(): string
    {
        $parts = [];

        $region = $this->region();
        if ($region) {
            $parts[] = $region->name;
        }

        $site = $this->site();
        if ($site) {
            $parts[] = $site->name;
        }

        $parts[] = $this->name;

        return implode(' - ', $parts);
    }

    public function getHierarchyPathAttribute(): string
    {
        $hierarchy = $this->getHierarchy();
        $path = [];

        foreach ($hierarchy as $level) {
            $path[] = $level['name'];
        }

        return implode(' > ', $path);
    }

    public function getAccessTimeDisplayAttribute(): string
    {
        if (!$this->access_time) {
            return 'Non spécifié';
        }

        if ($this->access_time < 60) {
            return $this->access_time . ' min';
        }

        $hours = floor($this->access_time / 60);
        $minutes = $this->access_time % 60;

        $display = $hours . 'h';
        if ($minutes > 0) {
            $display .= sprintf('%02d', $minutes);
        }

        return $display;
    }

    public function getAltitudeDisplayAttribute(): string
    {
        return $this->altitude ? $this->altitude . ' m' : 'Non spécifiée';
    }

    public function getCoordinatesDisplayAttribute(): string
    {
        if ($this->coordinates_lat && $this->coordinates_lng) {
            return sprintf('%.6f, %.6f', $this->coordinates_lat, $this->coordinates_lng);
        }

        if ($this->coordinates_swiss_e && $this->coordinates_swiss_n) {
            return "CH: {$this->coordinates_swiss_e}, {$this->coordinates_swiss_n}";
        }

        return 'Non spécifiées';
    }

    public function getGoogleMapsUrlAttribute(): string
    {
        if (!$this->coordinates_lat || !$this->coordinates_lng) {
            return '';
        }

        return "https://www.google.com/maps?q={$this->coordinates_lat},{$this->coordinates_lng}";
    }

    /**
     * Événements du modèle
     */

    protected function onCreating(): void
    {
        // Générer un code unique si pas fourni
        if (empty($this->code)) {
            $this->code = $this->generateUniqueCode();
        }

        // Valider la hiérarchie
        $this->validateHierarchy();
    }

    protected function onUpdating(): void
    {
        // Valider la hiérarchie lors des mises à jour
        $this->validateHierarchy();
    }

    /**
     * Valider la cohérence hiérarchique
     */
    private function validateHierarchy(): void
    {
        // Si un site est spécifié, vérifier qu'il appartient à la même région
        if ($this->site_id && $this->region_id) {
            $site = Site::find($this->site_id);
            if ($site && $site->region_id != $this->region_id) {
                throw new \Exception('Le site spécifié n\'appartient pas à la région indiquée');
            }
        }
    }

    /**
     * Générer un code unique pour le secteur
     */
    private function generateUniqueCode(): string
    {
        $baseName = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->name), 0, 3));
        if (strlen($baseName) < 3) {
            $baseName = str_pad($baseName, 3, 'X');
        }

        $counter = 1;
        $code = $baseName;

        while ($this->codeExists($code)) {
            $code = $baseName . sprintf('%02d', $counter);
            $counter++;
        }

        return $code;
    }

    /**
     * Vérifier si un code existe déjà
     */
    private function codeExists(string $code): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_sectors WHERE code = ? AND active = 1";
        if ($this->id) {
            $sql .= " AND id != ?";
            $result = $this->db->fetchOne($sql, [$code, $this->id]);
        } else {
            $result = $this->db->fetchOne($sql, [$code]);
        }

        return $result['count'] > 0;
    }
}
