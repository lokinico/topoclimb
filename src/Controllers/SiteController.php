<?php

declare(strict_types=1);

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Security\CsrfManager;
// CORRECTION 1: Utiliser Symfony Request comme les autres contrôleurs
use Symfony\Component\HttpFoundation\Request;
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
     * AMÉLIORATION: Gère le cas où aucune région n'est spécifiée
     */
    public function index(Request $request): Response
    {
        $regionId = $request->query->get('region_id');
        $search = $request->query->get('search', '');

        try {
            // CAS 1: Région spécifiée - Afficher les sites de cette région
            if ($regionId) {
                $region = Region::find($regionId);
                if (!$region) {
                    $this->session->flash('error', 'Région non trouvée');
                    return Response::redirect('/regions');
                }

                $sites = Site::getByRegion($regionId);

                // Filtrer par recherche si nécessaire
                if (!empty($search)) {
                    $sites = array_filter($sites, function ($site) use ($search) {
                        return stripos($site['name'], $search) !== false ||
                            stripos($site['description'], $search) !== false;
                    });
                }

                return $this->render('sites/index', [
                    'sites' => $sites,
                    'region' => $region,
                    'regions' => null, // Pas besoin de toutes les régions
                    'search' => $search,
                    'title' => 'Sites - ' . $region->name,
                    'show_region_selector' => false
                ]);
            }

            // CAS 2: Aucune région spécifiée - Afficher sélecteur + tous les sites ou redirection
            else {
                // Option A: Rediriger vers les régions (comportement actuel)
                // return Response::redirect('/regions');

                // Option B: Afficher tous les sites avec sélecteur de région (NOUVEAU)
                $regions = $this->db->fetchAll(
                    "SELECT r.*, c.name as country_name 
                     FROM climbing_regions r 
                     LEFT JOIN climbing_countries c ON r.country_id = c.id 
                     WHERE r.active = 1 
                     ORDER BY c.name, r.name"
                );

                // Récupérer tous les sites ou limiter à un nombre raisonnable
                $sql = "
                    SELECT s.*, r.name as region_name, r.id as region_id,
                           COUNT(DISTINCT sec.id) as sector_count,
                           COUNT(DISTINCT rt.id) as route_count
                    FROM climbing_sites s
                    INNER JOIN climbing_regions r ON s.region_id = r.id
                    LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
                    LEFT JOIN climbing_routes rt ON sec.id = rt.sector_id AND rt.active = 1
                    WHERE s.active = 1
                ";

                $params = [];
                if (!empty($search)) {
                    $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
                    $params = ["%{$search}%", "%{$search}%"];
                }

                $sql .= " GROUP BY s.id ORDER BY r.name, s.name LIMIT 50"; // Limiter pour performance

                $db = new \TopoclimbCH\Core\Database();
                $sites = $db->fetchAll($sql, $params);

                return $this->render('sites/index', [
                    'sites' => $sites,
                    'region' => null, // Aucune région spécifique
                    'regions' => $regions, // Toutes les régions pour le sélecteur
                    'search' => $search,
                    'title' => 'Tous les sites',
                    'show_region_selector' => true, // Afficher le sélecteur
                    'total_sites' => count($sites)
                ]);
            }
        } catch (\Exception $e) {
            error_log('SiteController::index error: ' . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement des sites');
            return Response::redirect('/regions');
        }
    }

    /**
     * Affiche un site avec ses secteurs
     */
    public function show(Request $request): Response
    {
        // CORRECTION 3: Utiliser attributes->get() pour les paramètres d'URL comme RouteController
        $id = $request->attributes->get('id');              // au lieu de $request->getParam('id')

        try {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return Response::redirect('/regions');
            }

            $region = $site->region();
            $sectors = $site->getSectorsWithStats();

            return $this->render('sites/show', [
                'site' => $site,
                'region' => $region,
                'sectors' => $sectors,
                'totalRoutes' => $site->getTotalRoutes(),
                'avgDifficulty' => $site->getAverageDifficulty(),
                'title' => $site->name
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors du chargement du site');
            return Response::redirect('/regions');
        }
    }

    /**
     * Formulaire de création/édition de site
     */
    public function form(Request $request): Response
    {
        // CORRECTION 4: Utiliser les bonnes méthodes Symfony Request
        $id = $request->attributes->get('id');              // au lieu de $request->getParam('id')
        $regionId = $request->query->get('region_id');      // au lieu de $request->getQuery('region_id')

        $site = null;
        $region = null;

        // Mode édition
        if ($id) {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return Response::redirect('/regions');
            }
            $region = $site->region();
        }
        // Mode création
        elseif ($regionId) {
            $region = Region::find($regionId);
            if (!$region) {
                $this->session->flash('error', 'Région non trouvée');
                return Response::redirect('/regions');
            }
        } else {
            $this->session->flash('error', 'Région requise pour créer un site');
            return Response::redirect('/regions');
        }

        return $this->render('sites/form', [
            'site' => $site,
            'region' => $region,
            'isEdit' => $id !== null,
            'title' => $id ? 'Modifier le site' : 'Nouveau site',
            'csrf_token' => $this->createCsrfToken()           // CORRECTION 5: Utiliser createCsrfToken() comme RouteController
        ]);
    }

    /**
     * Sauvegarde un site (création)
     */
    public function store(Request $request): Response
    {
        // CORRECTION 6: Valider CSRF comme RouteController
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token CSRF invalide');
            return Response::redirect('/sites');
        }

        try {
            // CORRECTION 7: Utiliser request->get() pour POST comme RouteController
            $data = [
                'region_id' => $request->request->get('region_id'),
                'name' => $request->request->get('name'),
                'code' => $request->request->get('code'),
                'description' => $request->request->get('description'),
                'year' => $request->request->get('year'),
                'publisher' => $request->request->get('publisher'),
                'isbn' => $request->request->get('isbn'),
                'active' => 1
            ];

            $site = new Site();
            $site->fill($data);

            if ($site->save()) {
                $this->session->flash('success', 'Site créé avec succès');
                return Response::redirect("/sites/{$site->id}");
            } else {
                $this->session->flash('error', 'Erreur lors de la création du site');
                return Response::redirect('/sites/create');               // CORRECTION 8: Redirection spécifique
            }
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return Response::redirect('/sites/create');                   // CORRECTION 9: Redirection spécifique
        }
    }

    /**
     * Met à jour un site
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');

        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token CSRF invalide');
            return Response::redirect("/sites/{$id}");
        }

        try {
            $site = Site::find($id);
            if (!$site) {
                $this->session->flash('error', 'Site non trouvé');
                return Response::redirect('/regions');
            }

            $data = [
                'name' => $request->request->get('name'),
                'code' => $request->request->get('code'),
                'description' => $request->request->get('description'),
                'year' => $request->request->get('year'),
                'publisher' => $request->request->get('publisher'),
                'isbn' => $request->request->get('isbn')
            ];

            $site->fill($data);

            if ($site->save()) {
                $this->session->flash('success', 'Site mis à jour avec succès');
                return Response::redirect("/sites/{$site->id}");
            } else {
                $this->session->flash('error', 'Erreur lors de la mise à jour du site');
                return Response::redirect("/sites/{$id}/edit");
            }
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur: ' . $e->getMessage());
            return Response::redirect("/sites/{$id}/edit");
        }
    }

    /**
     * API endpoint pour obtenir la hiérarchie complète ou partielle
     */
    public function hierarchyApi(Request $request): Response
    {
        // CORRECTION 10: Adapter pour Symfony Request
        $level = $request->query->get('level', 'regions');    // regions, sites, sectors, routes
        $parentId = $request->query->get('parent_id');
        $search = $request->query->get('search', '');

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
        $mode = $request->query->get('mode', 'select'); // select, book, stats
        $preselected = $request->query->get('preselected'); // format: region:1,site:2,sector:3

        return $this->render('sites/selector', [
            'mode' => $mode,
            'preselected' => $preselected,
            'title' => 'Sélecteur hiérarchique',
            'csrf_token' => $this->createCsrfToken()
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
