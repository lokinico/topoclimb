<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Exceptions\ServiceException;

class SiteService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupérer tous les sites avec pagination et filtres
     */
    public function getAllSites(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $whereConditions = ['s.active = 1'];
            $params = [];

            // Filtrage par région
            if (!empty($filters['region_id'])) {
                $whereConditions[] = 's.region_id = ?';
                $params[] = (int)$filters['region_id'];
            }

            // Recherche textuelle
            if (!empty($filters['search'])) {
                $whereConditions[] = '(s.name LIKE ? OR s.code LIKE ? OR s.description LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);
            $offset = ($page - 1) * $perPage;

            // Compter le total
            $countSql = "SELECT COUNT(*) as total FROM climbing_sites s WHERE {$whereClause}";
            $totalResult = $this->db->fetchOne($countSql, $params);
            $total = $totalResult['total'];

            // Récupérer les sites avec statistiques
            $sql = "SELECT 
                        s.*,
                        r.name as region_name,
                        COUNT(DISTINCT sect.id) as sectors_count,
                        COUNT(DISTINCT rt.id) as routes_count
                    FROM climbing_sites s
                    LEFT JOIN climbing_regions r ON s.region_id = r.id
                    LEFT JOIN climbing_sectors sect ON s.id = sect.site_id AND sect.active = 1
                    LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                    WHERE {$whereClause}
                    GROUP BY s.id
                    ORDER BY s.name ASC
                    LIMIT {$perPage} OFFSET {$offset}";

            $sites = $this->db->fetchAll($sql, $params);

            return [
                'sites' => $sites,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ]
            ];
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la récupération des sites: " . $e->getMessage());
        }
    }

    /**
     * Récupérer un site par ID avec toutes ses informations
     */
    public function getSiteById(int $id): ?array
    {
        try {
            $site = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id
                 FROM climbing_sites s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );

            if (!$site) {
                return null;
            }

            // Enrichir avec les statistiques
            $site['statistics'] = $this->getSiteStatistics($id);

            return $site;
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la récupération du site: " . $e->getMessage());
        }
    }

    /**
     * Récupérer les secteurs d'un site
     */
    public function getSiteSectors(int $siteId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT sect.*, 
                        COUNT(rt.id) as routes_count,
                        AVG(CASE WHEN rt.beauty > 0 THEN rt.beauty END) as avg_beauty
                 FROM climbing_sectors sect
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE sect.site_id = ? AND sect.active = 1
                 GROUP BY sect.id
                 ORDER BY sect.name ASC",
                [$siteId]
            );
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la récupération des secteurs: " . $e->getMessage());
        }
    }

    /**
     * Calculer les statistiques d'un site
     */
    public function getSiteStatistics(int $siteId): array
    {
        try {
            // Statistiques de base
            $baseStats = $this->db->fetchOne(
                "SELECT 
                    COUNT(DISTINCT sect.id) as sectors_count,
                    COUNT(DISTINCT rt.id) as routes_count,
                    MIN(sect.altitude) as min_altitude,
                    MAX(sect.altitude) as max_altitude,
                    AVG(sect.altitude) as avg_altitude,
                    MIN(rt.length) as min_route_length,
                    MAX(rt.length) as max_route_length,
                    AVG(rt.length) as avg_route_length
                 FROM climbing_sectors sect
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE sect.site_id = ? AND sect.active = 1",
                [$siteId]
            );

            // Difficultés disponibles
            $difficulties = $this->db->fetchAll(
                "SELECT DISTINCT rt.difficulty, COUNT(*) as count
                 FROM climbing_routes rt
                 JOIN climbing_sectors sect ON rt.sector_id = sect.id
                 WHERE sect.site_id = ? AND rt.active = 1 AND rt.difficulty IS NOT NULL
                 GROUP BY rt.difficulty
                 ORDER BY rt.difficulty",
                [$siteId]
            );

            // Styles disponibles
            $styles = $this->db->fetchAll(
                "SELECT rt.style, COUNT(*) as count
                 FROM climbing_routes rt
                 JOIN climbing_sectors sect ON rt.sector_id = sect.id
                 WHERE sect.site_id = ? AND rt.active = 1 AND rt.style IS NOT NULL
                 GROUP BY rt.style",
                [$siteId]
            );

            // Expositions principales
            $exposures = $this->db->fetchAll(
                "SELECT e.name, e.code, COUNT(*) as sectors_count
                 FROM climbing_exposures e
                 JOIN climbing_sector_exposures se ON e.id = se.exposure_id
                 JOIN climbing_sectors sect ON se.sector_id = sect.id
                 WHERE sect.site_id = ? AND sect.active = 1
                 GROUP BY e.id
                 ORDER BY sectors_count DESC",
                [$siteId]
            );

            return [
                'sectors_count' => (int)$baseStats['sectors_count'],
                'routes_count' => (int)$baseStats['routes_count'],
                'min_altitude' => $baseStats['min_altitude'] ? (int)$baseStats['min_altitude'] : null,
                'max_altitude' => $baseStats['max_altitude'] ? (int)$baseStats['max_altitude'] : null,
                'avg_altitude' => $baseStats['avg_altitude'] ? round($baseStats['avg_altitude']) : null,
                'min_route_length' => $baseStats['min_route_length'] ? (int)$baseStats['min_route_length'] : null,
                'max_route_length' => $baseStats['max_route_length'] ? (int)$baseStats['max_route_length'] : null,
                'avg_route_length' => $baseStats['avg_route_length'] ? round($baseStats['avg_route_length']) : null,
                'difficulties' => $difficulties,
                'styles' => $styles,
                'exposures' => $exposures
            ];
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors du calcul des statistiques: " . $e->getMessage());
        }
    }

    /**
     * Créer un nouveau site
     */
    public function createSite(array $data, int $userId): int
    {
        try {
            $this->validateSiteData($data);

            // Vérifier l'unicité du code
            if (!$this->isCodeUnique($data['code'])) {
                throw new ServiceException("Le code '{$data['code']}' est déjà utilisé");
            }

            $siteData = [
                'name' => $data['name'],
                'code' => $data['code'],
                'region_id' => (int)$data['region_id'],
                'description' => $data['description'] ?? null,
                'coordinates_lat' => !empty($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => !empty($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'altitude' => !empty($data['altitude']) ? (int)$data['altitude'] : null,
                'access_info' => $data['access_info'] ?? null,
                'year' => !empty($data['year']) ? (int)$data['year'] : null,
                'publisher' => $data['publisher'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'active' => 1,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $siteId = $this->db->insert('climbing_sites', $siteData);

            if (!$siteId) {
                throw new ServiceException("Échec de la création du site");
            }

            return $siteId;
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la création du site: " . $e->getMessage());
        }
    }

    /**
     * Mettre à jour un site existant
     */
    public function updateSite(int $id, array $data, int $userId): bool
    {
        try {
            $this->validateSiteData($data);

            // Vérifier l'unicité du code (excluant le site actuel)
            if (!$this->isCodeUnique($data['code'], $id)) {
                throw new ServiceException("Le code '{$data['code']}' est déjà utilisé");
            }

            $updateData = [
                'name' => $data['name'],
                'code' => $data['code'],
                'region_id' => (int)$data['region_id'],
                'description' => $data['description'] ?? null,
                'coordinates_lat' => !empty($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => !empty($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'altitude' => !empty($data['altitude']) ? (int)$data['altitude'] : null,
                'access_info' => $data['access_info'] ?? null,
                'year' => !empty($data['year']) ? (int)$data['year'] : null,
                'publisher' => $data['publisher'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'active' => isset($data['active']) ? 1 : 0,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            return $this->db->update('climbing_sites', $updateData, 'id = ?', [$id]);
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la mise à jour du site: " . $e->getMessage());
        }
    }

    /**
     * Supprimer un site (soft delete)
     */
    public function deleteSite(int $id): bool
    {
        try {
            // Vérifier qu'il n'y a pas de secteurs actifs
            $sectorsCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_sectors WHERE site_id = ? AND active = 1",
                [$id]
            );

            if ($sectorsCount['count'] > 0) {
                throw new ServiceException("Impossible de supprimer le site car il contient des secteurs actifs");
            }

            return $this->db->update(
                'climbing_sites',
                ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$id]
            );
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la suppression du site: " . $e->getMessage());
        }
    }

    /**
     * Rechercher des sites avec autocomplétion
     */
    public function searchSites(string $query, int $limit = 10): array
    {
        try {
            if (strlen($query) < 2) {
                return [];
            }

            $searchTerm = '%' . $query . '%';

            return $this->db->fetchAll(
                "SELECT s.*, r.name as region_name
                 FROM climbing_sites s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.active = 1 
                 AND (s.name LIKE ? OR s.code LIKE ? OR s.description LIKE ?)
                 ORDER BY s.name ASC
                 LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            );
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la recherche de sites: " . $e->getMessage());
        }
    }

    /**
     * Récupérer les sites d'une région
     */
    public function getSitesByRegion(int $regionId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT s.*, 
                        COUNT(DISTINCT sect.id) as sectors_count,
                        COUNT(DISTINCT rt.id) as routes_count
                 FROM climbing_sites s
                 LEFT JOIN climbing_sectors sect ON s.id = sect.site_id AND sect.active = 1
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE s.region_id = ? AND s.active = 1
                 GROUP BY s.id
                 ORDER BY s.name ASC",
                [$regionId]
            );
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la récupération des sites par région: " . $e->getMessage());
        }
    }

    /**
     * Valider les données d'un site
     */
    private function validateSiteData(array $data): void
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = "Le nom est obligatoire";
        } elseif (strlen($data['name']) > 255) {
            $errors[] = "Le nom ne peut pas dépasser 255 caractères";
        }

        if (empty($data['code'])) {
            $errors[] = "Le code est obligatoire";
        } elseif (strlen($data['code']) > 50) {
            $errors[] = "Le code ne peut pas dépasser 50 caractères";
        }

        if (empty($data['region_id'])) {
            $errors[] = "La région est obligatoire";
        } elseif (!is_numeric($data['region_id'])) {
            $errors[] = "L'ID de région doit être numérique";
        }

        // Validation des coordonnées
        if (!empty($data['coordinates_lat'])) {
            $lat = (float)$data['coordinates_lat'];
            if ($lat < -90 || $lat > 90) {
                $errors[] = "La latitude doit être comprise entre -90 et 90";
            }
        }

        if (!empty($data['coordinates_lng'])) {
            $lng = (float)$data['coordinates_lng'];
            if ($lng < -180 || $lng > 180) {
                $errors[] = "La longitude doit être comprise entre -180 et 180";
            }
        }

        // Validation de l'altitude
        if (!empty($data['altitude'])) {
            $altitude = (int)$data['altitude'];
            if ($altitude < 0 || $altitude > 9000) {
                $errors[] = "L'altitude doit être comprise entre 0 et 9000 mètres";
            }
        }

        // Validation de l'année
        if (!empty($data['year'])) {
            $year = (int)$data['year'];
            if ($year < 1900 || $year > date('Y') + 5) {
                $errors[] = "L'année doit être comprise entre 1900 et " . (date('Y') + 5);
            }
        }

        if (!empty($errors)) {
            throw new ServiceException("Données invalides: " . implode(', ', $errors));
        }
    }

    /**
     * Vérifier l'unicité du code
     */
    private function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM climbing_sites WHERE code = ? AND active = 1";
            $params = [$code];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $result = $this->db->fetchOne($sql, $params);
            return $result['count'] == 0;
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la vérification de l'unicité du code: " . $e->getMessage());
        }
    }

    /**
     * Migrer les secteurs orphelins vers un site par défaut
     */
    public function migrateSectorsToSites(): array
    {
        try {
            $results = [
                'migrated' => 0,
                'errors' => [],
                'sites_created' => 0
            ];

            // Trouver les secteurs sans site_id
            $orphanSectors = $this->db->fetchAll(
                "SELECT sect.*, r.name as region_name
                 FROM climbing_sectors sect
                 LEFT JOIN climbing_regions r ON sect.region_id = r.id
                 WHERE sect.site_id IS NULL AND sect.active = 1"
            );

            foreach ($orphanSectors as $sector) {
                try {
                    // Créer un site par défaut pour cette région si nécessaire
                    $siteName = "Site par défaut - " . ($sector['region_name'] ?? 'Région inconnue');
                    $siteCode = "DEFAULT_" . ($sector['region_id'] ?? 0);

                    // Vérifier si le site par défaut existe déjà
                    $existingSite = $this->db->fetchOne(
                        "SELECT id FROM climbing_sites WHERE code = ? AND active = 1",
                        [$siteCode]
                    );

                    $siteId = null;
                    if ($existingSite) {
                        $siteId = $existingSite['id'];
                    } else {
                        // Créer le site par défaut
                        $siteId = $this->db->insert('climbing_sites', [
                            'name' => $siteName,
                            'code' => $siteCode,
                            'region_id' => $sector['region_id'] ?? 1,
                            'description' => "Site créé automatiquement lors de la migration",
                            'active' => 1,
                            'created_by' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        $results['sites_created']++;
                    }

                    // Associer le secteur au site
                    if ($siteId) {
                        $this->db->update(
                            'climbing_sectors',
                            ['site_id' => $siteId, 'updated_at' => date('Y-m-d H:i:s')],
                            'id = ?',
                            [$sector['id']]
                        );
                        $results['migrated']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Secteur {$sector['name']} (#{$sector['id']}): " . $e->getMessage();
                }
            }

            return $results;
        } catch (\Exception $e) {
            throw new ServiceException("Erreur lors de la migration: " . $e->getMessage());
        }
    }
}
