<?php

declare(strict_types=1);

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Request;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Region;
use TopoclimbCH\Models\Sector;

class SiteController extends BaseController
{
    private RegionService $regionService;
    private SectorService $sectorService;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        RegionService $regionService,
        SectorService $sectorService
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->regionService = $regionService;
        $this->sectorService = $sectorService;
    }

    /**
     * Liste tous les sites avec filtrage par région
     */
    public function index(Request $request): Response
    {
        $regionId = $request->getQuery('region_id');
        $search = $request->getQuery('search', '');

        // Si pas de région spécifiée, rediriger vers les régions
        if (!$regionId) {
            return $this->redirect('/regions');
        }

        try {
            $region = Region::find($regionId);
            if (!$region) {
                $this->session->flash('error', 'Région non trouvée');
                return $this->redirect('/regions');
            }

            $sites = Site::getByRegion($regionId);

            // Filtrer par recherche si nécessaire
            if (!empty($search)) {
                $sites = array_filter($sites, function ($site) use ($search) {
                    return stripos($site['name'], $search) !== false ||
                        stripos($site['description'], $search) !== false;
                });
            }

            return $this->view->render('sites/index', [
                'sites' => $sites,
                'region' => $region,
                'search' => $search,
                'title' => 'Sites - ' . $region->name
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement des sites');
            return $this->redirect('/regions');
        }
    }

    /**
     * Affiche un site avec ses secteurs
     */
    public function show(Request $request): Response
    {
        $id = $request->getParam('id');

        try {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return $this->redirect('/regions');
            }

            $region = $site->region();
            $sectors = $site->getSectorsWithStats();

            return $this->view->render('sites/show', [
                'site' => $site,
                'region' => $region,
                'sectors' => $sectors,
                'totalRoutes' => $site->getTotalRoutes(),
                'avgDifficulty' => $site->getAverageDifficulty(),
                'title' => $site->name
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement du site');
            return $this->redirect('/regions');
        }
    }

    /**
     * Formulaire de création/édition de site
     */
    public function form(Request $request): Response
    {
        $id = $request->getParam('id');
        $regionId = $request->getQuery('region_id');

        $site = null;
        $region = null;

        // Mode édition
        if ($id) {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return $this->redirect('/regions');
            }
            $region = $site->region();
        }
        // Mode création
        elseif ($regionId) {
            $region = Region::find($regionId);
            if (!$region) {
                $this->session->flash('error', 'Région non trouvée');
                return $this->redirect('/regions');
            }
        } else {
            $this->session->flash('error', 'Région requise pour créer un site');
            return $this->redirect('/regions');
        }

        return $this->view->render('sites/form', [
            'site' => $site,
            'region' => $region,
            'isEdit' => $id !== null,
            'title' => $id ? 'Modifier le site' : 'Nouveau site',
            'csrfToken' => $this->csrfManager->generateToken()
        ]);
    }

    /**
     * Sauvegarde un site (création)
     */
    public function store(Request $request): Response
    {
        if (!$this->csrfManager->validateToken($request->getPost('csrf_token'))) {
            $this->session->flash('error', 'Token CSRF invalide');
            return $this->redirect('/sites');
        }

        try {
            $data = [
                'region_id' => $request->getPost('region_id'),
                'name' => $request->getPost('name'),
                'code' => $request->getPost('code'),
                'description' => $request->getPost('description'),
                'year' => $request->getPost('year'),
                'publisher' => $request->getPost('publisher'),
                'isbn' => $request->getPost('isbn'),
                'active' => 1
            ];

            $site = new Site();
            $site->fill($data);

            if ($site->save()) {
                $this->session->flash('success', 'Site créé avec succès');
                return $this->redirect("/sites/{$site->id}");
            } else {
                $this->session->flash('error', 'Erreur lors de la création du site');
                return $this->redirect()->back();
            }
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect()->back();
        }
    }

    /**
     * Met à jour un site
     */
    public function update(Request $request): Response
    {
        $id = $request->getParam('id');

        if (!$this->csrfManager->validateToken($request->getPost('csrf_token'))) {
            $this->session->flash('error', 'Token CSRF invalide');
            return $this->redirect("/sites/{$id}");
        }

        try {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return $this->redirect('/regions');
            }

            $data = [
                'name' => $request->getPost('name'),
                'code' => $request->getPost('code'),
                'description' => $request->getPost('description'),
                'year' => $request->getPost('year'),
                'publisher' => $request->getPost('publisher'),
                'isbn' => $request->getPost('isbn')
            ];

            $site->fill($data);

            if ($site->save()) {
                $this->session->flash('success', 'Site mis à jour avec succès');
                return $this->redirect("/sites/{$site->id}");
            } else {
                $this->session->flash('error', 'Erreur lors de la mise à jour du site');
                return $this->redirect()->back();
            }
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirect()->back();
        }
    }

    /**
     * OUTIL DE SÉLECTION HIÉRARCHIQUE
     * 
     * API endpoint pour obtenir la hiérarchie complète ou partielle
     */
    public function hierarchyApi(Request $request): Response
    {
        $level = $request->getQuery('level', 'regions'); // regions, sites, sectors, routes
        $parentId = $request->getQuery('parent_id');
        $search = $request->getQuery('search', '');

        try {
            $data = [];

            switch ($level) {
                case 'regions':
                    $data = $this->getRegionsData($search);
                    break;

                case 'sites':
                    if ($parentId) {
                        $data = $this->getSitesData($parentId, $search);
                    }
                    break;

                case 'sectors':
                    $data = $this->getSectorsData($parentId, $search);
                    break;

                case 'routes':
                    if ($parentId) {
                        $data = $this->getRoutesData($parentId, $search);
                    }
                    break;
            }

            return Response::json([
                'success' => true,
                'data' => $data,
                'level' => $level
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Outil de sélection interactif (page complète)
     */
    public function selector(Request $request): Response
    {
        $mode = $request->getQuery('mode', 'select'); // select, book, stats
        $preselected = $request->getQuery('preselected'); // format: region:1,site:2,sector:3

        return $this->view->render('sites/selector', [
            'mode' => $mode,
            'preselected' => $preselected,
            'title' => 'Sélecteur hiérarchique',
            'csrfToken' => $this->csrfManager->generateToken()
        ]);
    }

    /**
     * Méthodes privées pour l'API hiérarchique
     */

    private function getRegionsData(string $search = ''): array
    {
        $sql = "
            SELECT r.id, r.name, r.description,
                   COUNT(DISTINCT s.id) as site_count,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count
            FROM climbing_regions r
            LEFT JOIN climbing_sites s ON r.id = s.region_id AND s.active = 1
            LEFT JOIN climbing_sectors sec ON r.id = sec.region_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE r.active = 1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
            $params = ["%{$search}%", "%{$search}%"];
        }

        $sql .= " GROUP BY r.id ORDER BY r.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }

    private function getSitesData(int $regionId, string $search = ''): array
    {
        $sql = "
            SELECT s.id, s.name, s.description, s.code,
                   COUNT(DISTINCT sec.id) as sector_count,
                   COUNT(DISTINCT rt.id) as route_count
            FROM climbing_sites s
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE s.region_id = ? AND s.active = 1
        ";

        $params = [$regionId];
        if (!empty($search)) {
            $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " GROUP BY s.id ORDER BY s.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }

    private function getSectorsData(?int $parentId, string $search = ''): array
    {
        $sql = "
            SELECT sec.id, sec.name, sec.description, sec.code,
                   sec.site_id, sec.region_id,
                   s.name as site_name,
                   r.name as region_name,
                   COUNT(rt.id) as route_count
            FROM climbing_sectors sec
            LEFT JOIN climbing_sites s ON sec.site_id = s.id
            INNER JOIN climbing_regions r ON sec.region_id = r.id
            LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
            WHERE sec.active = 1
        ";

        $params = [];

        // Si parentId fourni, peut être site_id ou region_id
        if ($parentId) {
            $sql .= " AND (sec.site_id = ? OR (sec.site_id IS NULL AND sec.region_id = ?))";
            $params = [$parentId, $parentId];
        }

        if (!empty($search)) {
            $sql .= " AND (sec.name LIKE ? OR sec.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " GROUP BY sec.id ORDER BY sec.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }

    private function getRoutesData(int $sectorId, string $search = ''): array
    {
        $sql = "
            SELECT r.id, r.name, r.number, r.difficulty, r.beauty, r.style, r.length,
                   sec.name as sector_name
            FROM climbing_routes r
            INNER JOIN climbing_sectors sec ON r.sector_id = sec.id
            WHERE r.sector_id = ? AND r.active = 1
        ";

        $params = [$sectorId];
        if (!empty($search)) {
            $sql .= " AND (r.name LIKE ? OR r.difficulty = ?)";
            $params[] = "%{$search}%";
            $params[] = $search;
        }

        $sql .= " ORDER BY r.number, r.name";

        $db = new \TopoclimbCH\Core\Database();
        return $db->fetchAll($sql, $params);
    }
}
