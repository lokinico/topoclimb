<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Security\CsrfManager;

class SiteController extends BaseController
{
    private MediaService $mediaService;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,    // Position 3 ✅
        RegionService $regionService, // Position 4 ✅ (container injecte RegionService en 4ème)
        SectorService $sectorService  // Position 5 ✅ (container injecte SectorService en 5ème)
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->regionService = $regionService;
        $this->sectorService = $sectorService;

        // MediaService peut être récupéré via le container dans les méthodes si nécessaire
        // ou instancié manuellement si pas injecté
        $this->mediaService = Container::getInstance()->get(MediaService::class);
        // $this->db est déjà disponible via BaseController
    }

    /**
     * Liste tous les sites
     */
    public function index(Request $request): Response
    {
        try {
            $page = (int) $request->query->get('page', 1);
            $perPage = (int) $request->query->get('per_page', 20);
            $sortBy = $request->query->get('sort_by', 'name');
            $sortDir = $request->query->get('sort_dir', 'ASC');
            $regionId = $request->query->get('region_id');
            $search = $request->query->get('search');

            // Construction de la requête avec jointures
            $whereConditions = ['s.active = 1'];
            $params = [];

            if ($regionId) {
                $whereConditions[] = 's.region_id = ?';
                $params[] = (int)$regionId;
            }

            if ($search) {
                $whereConditions[] = '(s.name LIKE ? OR s.code LIKE ? OR s.description LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Requête pour compter le total
            $countSql = "SELECT COUNT(*) as total 
                        FROM climbing_sites s 
                        LEFT JOIN climbing_regions r ON s.region_id = r.id 
                        WHERE {$whereClause}";
            $totalResult = $this->db->fetchOne($countSql, $params);
            $total = $totalResult['total'];

            // Requête paginée avec statistiques
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT 
                        s.*,
                        r.name as region_name,
                        r.id as region_id,
                        COUNT(DISTINCT sect.id) as sectors_count,
                        COUNT(DISTINCT rt.id) as routes_count
                    FROM climbing_sites s
                    LEFT JOIN climbing_regions r ON s.region_id = r.id
                    LEFT JOIN climbing_sectors sect ON s.id = sect.site_id AND sect.active = 1
                    LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                    WHERE {$whereClause}
                    GROUP BY s.id
                    ORDER BY {$sortBy} {$sortDir}
                    LIMIT {$perPage} OFFSET {$offset}";

            $sites = $this->db->fetchAll($sql, $params);

            // Pagination info
            $pagination = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ];

            // Données pour les filtres
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");

            return $this->render('sites/index', [
                'title' => 'Sites d\'escalade',
                'sites' => $sites,
                'pagination' => $pagination,
                'regions' => $regions,
                'currentFilters' => [
                    'region_id' => $regionId,
                    'search' => $search,
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir
                ]
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des sites: ' . $e->getMessage());
            return $this->render('sites/index', [
                'sites' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Affiche un site spécifique
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du site non spécifié');
            return Response::redirect('/sites');
        }

        try {
            // Récupérer le site avec sa région
            $siteData = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id
                 FROM climbing_sites s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.id = ? AND s.active = 1",
                [(int)$id]
            );

            if (!$siteData) {
                $this->session->flash('error', 'Site non trouvé');
                return Response::redirect('/sites');
            }

            // Récupérer les secteurs du site
            $sectors = $this->db->fetchAll(
                "SELECT sect.*, COUNT(rt.id) as routes_count
                 FROM climbing_sectors sect
                 LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
                 WHERE sect.site_id = ? AND sect.active = 1
                 GROUP BY sect.id
                 ORDER BY sect.name ASC",
                [(int)$id]
            );

            // Récupérer les médias du site
            $media = $this->mediaService->getMediaForEntity('site', (int)$id);

            // Calculer les statistiques
            $stats = [
                'sectors_count' => count($sectors),
                'routes_count' => array_sum(array_column($sectors, 'routes_count')),
                'min_altitude' => null,
                'max_altitude' => null,
                'difficulties' => []
            ];

            // Analyser les secteurs pour les stats
            foreach ($sectors as $sector) {
                if (!is_null($sector['altitude'])) {
                    if (is_null($stats['min_altitude']) || $sector['altitude'] < $stats['min_altitude']) {
                        $stats['min_altitude'] = $sector['altitude'];
                    }
                    if (is_null($stats['max_altitude']) || $sector['altitude'] > $stats['max_altitude']) {
                        $stats['max_altitude'] = $sector['altitude'];
                    }
                }
            }

            // Récupérer toutes les difficultés des voies de ce site
            if ($stats['routes_count'] > 0) {
                $difficulties = $this->db->fetchAll(
                    "SELECT DISTINCT rt.difficulty
                     FROM climbing_routes rt
                     JOIN climbing_sectors sect ON rt.sector_id = sect.id
                     WHERE sect.site_id = ? AND rt.active = 1 AND rt.difficulty IS NOT NULL
                     ORDER BY rt.difficulty",
                    [(int)$id]
                );
                $stats['difficulties'] = array_column($difficulties, 'difficulty');
            }

            return $this->render('sites/show', [
                'title' => $siteData['name'],
                'site' => $siteData,
                'sectors' => $sectors,
                'media' => $media,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/sites');
        }
    }

    /**
     * Formulaire de création/édition d'un site
     */
    public function form(Request $request): Response
    {
        $id = $request->attributes->get('id');
        $isEdit = !is_null($id);

        try {
            $site = null;
            if ($isEdit) {
                $site = $this->db->fetchOne(
                    "SELECT * FROM climbing_sites WHERE id = ? AND active = 1",
                    [(int)$id]
                );

                if (!$site) {
                    $this->session->flash('error', 'Site non trouvé');
                    return Response::redirect('/sites');
                }
            }

            // Données pour le formulaire
            $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions WHERE active = 1 ORDER BY name ASC");

            // Médias existants si édition
            $media = $isEdit ? $this->mediaService->getMediaForEntity('site', (int)$id) : [];

            return $this->render('sites/form', [
                'title' => $isEdit ? 'Modifier le site ' . $site['name'] : 'Créer un nouveau site',
                'site' => $site,
                'regions' => $regions,
                'media' => $media,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return Response::redirect('/sites');
        }
    }

    /**
     * Création d'un nouveau site
     */
    public function store(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sites/create');
        }

        $data = $request->request->all();

        // Validation basique
        if (empty($data['name']) || empty($data['code']) || empty($data['region_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires (nom, code, région)');
            return Response::redirect('/sites/create');
        }

        try {
            $data['created_by'] = $_SESSION['auth_user_id'] ?? 1;

            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sites/create');
            }

            // Vérifier l'unicité du code
            $existingCode = $this->db->fetchOne(
                "SELECT id FROM climbing_sites WHERE code = ? AND active = 1",
                [$data['code']]
            );

            if ($existingCode) {
                $this->db->rollBack();
                $this->session->flash('error', 'Le code "' . $data['code'] . '" est déjà utilisé');
                return Response::redirect('/sites/create');
            }

            // Préparer les données
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
                'active' => isset($data['active']) ? 1 : 0,
                'created_by' => $data['created_by'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $siteId = $this->db->insert('climbing_sites', $siteData);

            if (!$siteId) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la création du site');
                return Response::redirect('/sites/create');
            }

            // Traitement des médias
            $mediaFile = $_FILES['media_file'] ?? null;
            if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name'])) {
                $this->mediaService->uploadMedia($mediaFile, [
                    'title' => $data['media_title'] ?? $data['name'],
                    'description' => "Image pour le site: {$data['name']}",
                    'is_public' => 1,
                    'media_type' => 'image',
                    'entity_type' => 'site',
                    'entity_id' => $siteId,
                    'relationship_type' => $data['media_relationship_type'] ?? 'main'
                ], $data['created_by']);
            }

            if (!$this->db->commit()) {
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            $this->session->flash('success', 'Site créé avec succès');
            return Response::redirect('/sites/' . $siteId);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->session->flash('error', 'Erreur lors de la création du site: ' . $e->getMessage());
            return Response::redirect('/sites/create');
        }
    }

    /**
     * Mise à jour d'un site existant
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du site non spécifié');
            return Response::redirect('/sites');
        }

        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sites/' . $id . '/edit');
        }

        $data = $request->request->all();

        // Validation basique
        if (empty($data['name']) || empty($data['code']) || empty($data['region_id'])) {
            $this->session->flash('error', 'Veuillez remplir tous les champs obligatoires (nom, code, région)');
            return Response::redirect('/sites/' . $id . '/edit');
        }

        try {
            $data['updated_by'] = $_SESSION['auth_user_id'] ?? 1;

            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sites/' . $id . '/edit');
            }

            // Vérifier l'unicité du code (excluant le site actuel)
            $existingCode = $this->db->fetchOne(
                "SELECT id FROM climbing_sites WHERE code = ? AND active = 1 AND id != ?",
                [$data['code'], (int)$id]
            );

            if ($existingCode) {
                $this->db->rollBack();
                $this->session->flash('error', 'Le code "' . $data['code'] . '" est déjà utilisé');
                return Response::redirect('/sites/' . $id . '/edit');
            }

            // Préparer les données de mise à jour
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
                'updated_by' => $data['updated_by'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $success = $this->db->update('climbing_sites', $updateData, 'id = ?', [(int)$id]);

            if (!$success) {
                throw new \Exception("Échec de la mise à jour du site");
            }

            // Traitement des nouveaux médias
            $mediaFile = $_FILES['media_file'] ?? null;
            if ($mediaFile && isset($mediaFile['tmp_name']) && is_uploaded_file($mediaFile['tmp_name']) && $mediaFile['error'] === UPLOAD_ERR_OK) {
                $mediaTitle = $data['media_title'] ?? null;
                $relationshipType = $data['media_relationship_type'] ?? 'gallery';

                $mediaId = $this->mediaService->uploadMedia($mediaFile, [
                    'title' => $mediaTitle ?? $data['name'],
                    'description' => "Image pour le site: {$data['name']}",
                    'is_public' => 1,
                    'media_type' => 'image',
                    'entity_type' => 'site',
                    'entity_id' => (int)$id,
                    'relationship_type' => $relationshipType
                ], $data['updated_by']);

                if ($mediaId && $relationshipType === 'main') {
                    $this->db->update(
                        'climbing_media_relationships',
                        ['relationship_type' => 'gallery'],
                        'entity_type = ? AND entity_id = ? AND relationship_type = ? AND media_id != ?',
                        ['site', (int)$id, 'main', $mediaId]
                    );
                }
            }

            if (!$this->db->commit()) {
                throw new \Exception("Échec lors de l'enregistrement final des modifications");
            }

            $this->session->flash('success', 'Site mis à jour avec succès');
            return Response::redirect('/sites/' . $id);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return Response::redirect('/sites/' . $id . '/edit');
        }
    }

    /**
     * Suppression d'un site
     */
    public function destroy(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du site non spécifié');
            return Response::redirect('/sites');
        }

        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide, veuillez réessayer');
            return Response::redirect('/sites/' . $id);
        }

        try {
            // Vérifier que le site existe
            $site = $this->db->fetchOne(
                "SELECT * FROM climbing_sites WHERE id = ? AND active = 1",
                [(int)$id]
            );

            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return Response::redirect('/sites');
            }

            // Vérifier s'il y a des secteurs associés
            $sectorsCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_sectors WHERE site_id = ? AND active = 1",
                [(int)$id]
            );

            if ($sectorsCount['count'] > 0) {
                $this->session->flash('error', 'Impossible de supprimer le site car il contient des secteurs actifs');
                return Response::redirect('/sites/' . $id);
            }

            if (!$this->db->beginTransaction()) {
                $this->session->flash('error', 'Erreur de base de données: impossible de démarrer la transaction');
                return Response::redirect('/sites/' . $id);
            }

            // Désactiver le site (soft delete)
            $success = $this->db->update(
                'climbing_sites',
                ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [(int)$id]
            );

            if (!$success) {
                $this->db->rollBack();
                $this->session->flash('error', 'Erreur lors de la suppression du site');
                return Response::redirect('/sites/' . $id);
            }

            if (!$this->db->commit()) {
                throw new \Exception("Échec lors de la suppression finale");
            }

            $this->session->flash('success', 'Site supprimé avec succès');
            return Response::redirect('/sites');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->session->flash('error', 'Erreur lors de la suppression du site: ' . $e->getMessage());
            return Response::redirect('/sites/' . $id);
        }
    }

    /**
     * API: Recherche de sites avec autocomplétion
     */
    public function apiSearch(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $limit = min((int)$request->query->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return Response::json([
                'success' => true,
                'data' => []
            ]);
        }

        try {
            $sites = Site::search($query, $limit);

            return Response::json([
                'success' => true,
                'data' => $sites
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * API: Informations d'un site pour AJAX
     */
    public function apiShow(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$id) {
            return Response::json([
                'success' => false,
                'error' => 'ID du site non spécifié'
            ], 400);
        }

        try {
            $site = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name, r.id as region_id
                 FROM climbing_sites s
                 LEFT JOIN climbing_regions r ON s.region_id = r.id
                 WHERE s.id = ? AND s.active = 1",
                [(int)$id]
            );

            if (!$site) {
                return Response::json([
                    'success' => false,
                    'error' => 'Site non trouvé'
                ], 404);
            }

            return Response::json([
                'success' => true,
                'data' => $site
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la récupération du site'
            ], 500);
        }
    }

    /**
     * API: Liste des sites par région
     */
    public function apiIndex(Request $request): Response
    {
        $regionId = $request->query->get('region_id');
        $search = $request->query->get('search');

        try {
            $whereConditions = ['s.active = 1'];
            $params = [];

            if ($regionId) {
                $whereConditions[] = 's.region_id = ?';
                $params[] = (int)$regionId;
            }

            if ($search) {
                $whereConditions[] = '(s.name LIKE ? OR s.code LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT s.*, r.name as region_name
                    FROM climbing_sites s
                    LEFT JOIN climbing_regions r ON s.region_id = r.id
                    WHERE {$whereClause}
                    ORDER BY s.name ASC";

            $sites = $this->db->fetchAll($sql, $params);

            return Response::json([
                'success' => true,
                'data' => $sites
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des sites'
            ], 500);
        }
    }
}
