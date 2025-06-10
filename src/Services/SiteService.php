<?php

declare(strict_types=1);

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Exceptions\ServiceException;

/**
 * Service pour la gestion des sites d'escalade
 * 
 * Les sites sont des sous-zones optionnelles des régions qui regroupent plusieurs secteurs
 */
class SiteService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtenir tous les sites d'une région avec statistiques
     */
    public function getSitesByRegion(int $regionId, array $filters = []): array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count,
                   AVG(CAST(rt.beauty AS UNSIGNED)) as avg_beauty,
                   MIN(rt.difficulty) as min_difficulty,
                   MAX(rt.difficulty) as max_difficulty
            FROM climbing_sites s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.region_id = ? AND s.active = 1
        ";

        $params = [$regionId];

        // Filtrage par recherche
        if (!empty($filters['search'])) {
            $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " GROUP BY s.id ORDER BY s.name";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Obtenir un site avec ses détails complets
     */
    public function getSiteWithDetails(int $siteId): ?array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   r.id as region_id,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count
            FROM climbing_sites s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.id = ? AND s.active = 1
            GROUP BY s.id
        ";

        return $this->db->fetchOne($sql, [$siteId]);
    }

    /**
     * Obtenir les secteurs d'un site avec statistiques
     */
    public function getSiteSectors(int $siteId): array
    {
        $sql = "
            SELECT sec.*, 
                   COUNT(rt.id) as route_count,
                   AVG(CAST(rt.beauty AS UNSIGNED)) as avg_beauty,
                   MIN(rt.difficulty) as min_difficulty,
                   MAX(rt.difficulty) as max_difficulty
            FROM climbing_sectors sec
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE sec.site_id = ? AND sec.active = 1
            GROUP BY sec.id
            ORDER BY sec.name
        ";

        return $this->db->fetchAll($sql, [$siteId]);
    }

    /**
     * Créer un nouveau site
     */
    public function createSite(array $data): int
    {
        // Validation des données requises
        if (empty($data['region_id']) || empty($data['name'])) {
            throw new ServiceException('Region ID et nom requis pour créer un site');
        }

        // Vérifier que la région existe
        $region = Region::find($data['region_id']);
        if (!$region) {
            throw new ServiceException('Région non trouvée');
        }

        // Générer un code unique si pas fourni
        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueCode($data['name']);
        }

        try {
            $site = new Site();
            $site->fill([
                'region_id' => $data['region_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'year' => $data['year'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'active' => 1
            ]);

            if ($site->save()) {
                return $site->id;
            }

            throw new ServiceException('Erreur lors de la sauvegarde du site');
        } catch (\Exception $e) {
            throw new ServiceException('Erreur lors de la création du site: ' . $e->getMessage());
        }
    }

    /**
     * Mettre à jour un site
     */
    public function updateSite(int $siteId, array $data): bool
    {
        $site = Site::find($siteId);
        if (!$site) {
            throw new ServiceException('Site non trouvé');
        }

        try {
            $site->fill([
                'name' => $data['name'] ?? $site->name,
                'code' => $data['code'] ?? $site->code,
                'description' => $data['description'] ?? $site->description,
                'year' => $data['year'] ?? $site->year,
                'publisher' => $data['publisher'] ?? $site->publisher,
                'isbn' => $data['isbn'] ?? $site->isbn
            ]);

            return $site->save();
        } catch (\Exception $e) {
            throw new ServiceException('Erreur lors de la mise à jour du site: ' . $e->getMessage());
        }
    }

    /**
     * Supprimer un site (soft delete)
     */
    public function deleteSite(int $siteId): bool
    {
        $site = Site::find($siteId);
        if (!$site) {
            throw new ServiceException('Site non trouvé');
        }

        // Vérifier s'il y a des secteurs attachés
        $sectorsCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_sectors WHERE site_id = ? AND active = 1",
            [$siteId]
        );

        if ($sectorsCount['count'] > 0) {
            throw new ServiceException('Impossible de supprimer un site contenant des secteurs');
        }

        try {
            $site->active = 0;
            return $site->save();
        } catch (\Exception $e) {
            throw new ServiceException('Erreur lors de la suppression du site: ' . $e->getMessage());
        }
    }

    /**
     * Rechercher des sites dans toutes les régions
     */
    public function searchSites(string $query, int $limit = 50): array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count
            FROM climbing_sites s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.active = 1 
            AND (s.name LIKE ? OR s.description LIKE ? OR s.code LIKE ?)
            GROUP BY s.id
            ORDER BY s.name
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }

    /**
     * Obtenir les statistiques globales des sites
     */
    public function getSitesStats(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT s.id) as total_sites,
                COUNT(DISTINCT s.region_id) as regions_with_sites,
                COUNT(DISTINCT sec.id) as total_sectors_in_sites,
                COUNT(DISTINCT rt.id) as total_routes_in_sites,
                AVG(CAST(rt.beauty AS UNSIGNED)) as avg_beauty
            FROM climbing_sites s
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.active = 1
        ";

        return $this->db->fetchOne($sql);
    }

    /**
     * Déplacer tous les secteurs d'un site vers une autre organisation
     */
    public function moveSiteSectors(int $siteId, ?int $newSiteId = null, ?int $newRegionId = null): bool
    {
        if ($newSiteId && $newRegionId) {
            throw new ServiceException('Spécifiez soit un nouveau site, soit une nouvelle région, pas les deux');
        }

        if (!$newSiteId && !$newRegionId) {
            throw new ServiceException('Nouveau site ou nouvelle région requis');
        }

        try {
            if ($newSiteId) {
                // Déplacer vers un autre site
                $sql = "UPDATE climbing_sectors SET site_id = ? WHERE site_id = ? AND active = 1";
                return $this->db->query($sql, [$newSiteId, $siteId]);
            } else {
                // Déplacer directement vers une région (site_id = NULL)
                $sql = "UPDATE climbing_sectors SET site_id = NULL, region_id = ? WHERE site_id = ? AND active = 1";
                return $this->db->query($sql, [$newRegionId, $siteId]);
            }
        } catch (\Exception $e) {
            throw new ServiceException('Erreur lors du déplacement des secteurs: ' . $e->getMessage());
        }
    }

    /**
     * Générer un code unique pour un site
     */
    private function generateUniqueCode(string $name): string
    {
        $baseName = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
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
        $sql = "SELECT COUNT(*) as count FROM climbing_sites WHERE code = ? AND active = 1";
        $result = $this->db->fetchOne($sql, [$code]);
        return $result['count'] > 0;
    }

    /**
     * Obtenir les sites populaires (par nombre de voies)
     */
    public function getPopularSites(int $limit = 10): array
    {
        $sql = "
            SELECT s.*, 
                   r.name as region_name,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count,
                   AVG(CAST(rt.beauty AS UNSIGNED)) as avg_beauty
            FROM climbing_sites s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.active = 1
            GROUP BY s.id
            HAVING route_count > 0
            ORDER BY route_count DESC, avg_beauty DESC
            LIMIT ?
        ";

        return $this->db->fetchAll($sql, [$limit]);
    }
}
