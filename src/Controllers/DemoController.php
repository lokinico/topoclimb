<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\AccessControl;

/**
 * Contrôleur pour les pages de démonstration
 * Contenu limité et contrôlé pour utilisateurs non-connectés
 */
class DemoController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * Page d'accueil des démonstrations
     */
    public function index(Request $request): Response
    {
        $accessControl = $request->attributes->get('access_control');
        $limits = $accessControl ? $accessControl->getContentLimits() : [];

        return $this->render('demo/index', [
            'title' => 'Découvrez TopoclimbCH - Démonstration',
            'limits' => $limits,
            'demo_mode' => true
        ]);
    }

    /**
     * Régions de démonstration (limité aux 3 premières)
     */
    public function regions(Request $request): Response
    {
        $accessControl = $request->attributes->get('access_control');
        $limits = $accessControl ? $accessControl->getContentLimits() : ['regions_max' => 3];

        // Récupérer seulement les régions de démo
        $regions = $this->db->fetchAll(
            "SELECT r.id, r.name, r.description,
                    COUNT(DISTINCT s.id) as sites_count,
                    COUNT(DISTINCT sect.id) as sectors_count,
                    COUNT(DISTINCT rt.id) as routes_count
             FROM climbing_regions r
             LEFT JOIN climbing_sites s ON r.id = s.region_id AND s.active = 1
             LEFT JOIN climbing_sectors sect ON s.id = sect.site_id AND sect.active = 1  
             LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
             WHERE r.active = 1
             GROUP BY r.id
             ORDER BY r.name ASC
             LIMIT ?",
            [$limits['regions_max']]
        );

        // Appliquer les watermarks et limitations visuelles
        foreach ($regions as &$region) {
            $region['demo_mode'] = true;
            $region['limited_access'] = true;
            // Masquer les coordonnées précises
            unset($region['coordinates_lat'], $region['coordinates_lng']);
        }

        return $this->render('demo/regions', [
            'title' => 'Régions d\'escalade - Aperçu',
            'regions' => $regions,
            'demo_mode' => true,
            'limits' => $limits,
            'upgrade_message' => 'Créez un compte gratuit pour accéder aux ' . count($regions) . '+ régions complètes'
        ]);
    }

    /**
     * Sites de démonstration pour une région
     */
    public function sites(Request $request): Response
    {
        $regionId = $request->query->get('region_id');
        $accessControl = $request->attributes->get('access_control');
        $limits = $accessControl ? $accessControl->getContentLimits() : ['sites_per_region' => 2];

        if (!$regionId || !is_numeric($regionId)) {
            return $this->redirect('/demo/regions');
        }

        // Vérifier que la région fait partie des régions de démo autorisées
        $allowedRegions = $this->db->fetchAll(
            "SELECT id FROM climbing_regions WHERE active = 1 ORDER BY name ASC LIMIT 3"
        );
        $allowedRegionIds = array_column($allowedRegions, 'id');
        
        if (!in_array($regionId, $allowedRegionIds)) {
            $this->flash('warning', 'Cette région nécessite un compte pour être accessible.');
            return $this->redirect('/demo/regions');
        }

        $region = $this->db->fetchOne(
            "SELECT * FROM climbing_regions WHERE id = ? AND active = 1",
            [$regionId]
        );

        if (!$region) {
            return $this->redirect('/demo/regions');
        }

        // Sites limités pour la démo
        $sites = $this->db->fetchAll(
            "SELECT s.id, s.name, s.description,
                    COUNT(DISTINCT sect.id) as sectors_count,
                    COUNT(DISTINCT rt.id) as routes_count
             FROM climbing_sites s
             LEFT JOIN climbing_sectors sect ON s.id = sect.site_id AND sect.active = 1
             LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
             WHERE s.region_id = ? AND s.active = 1
             GROUP BY s.id
             ORDER BY s.name ASC
             LIMIT ?",
            [$regionId, $limits['sites_per_region']]
        );

        // Appliquer les limitations de démo
        foreach ($sites as &$site) {
            $site['demo_mode'] = true;
            $site['access_info'] = 'Informations d\'accès disponibles pour les membres';
            unset($site['coordinates_lat'], $site['coordinates_lng'], $site['access_time']);
        }

        return $this->render('demo/sites', [
            'title' => 'Sites d\'escalade - ' . $region['name'] . ' (Aperçu)',
            'region' => $region,
            'sites' => $sites,
            'demo_mode' => true,
            'limits' => $limits,
            'upgrade_message' => 'Accédez aux ' . count($sites) . '+ sites complets avec coordonnées GPS'
        ]);
    }

    /**
     * Secteurs de démonstration pour un site
     */
    public function sectors(Request $request): Response
    {
        $siteId = $request->query->get('site_id');
        $accessControl = $request->attributes->get('access_control');
        $limits = $accessControl ? $accessControl->getContentLimits() : ['sectors_per_site' => 1];

        if (!$siteId || !is_numeric($siteId)) {
            return $this->redirect('/demo/regions');
        }

        // Vérifier que le site fait partie des sites de démo autorisés
        $site = $this->db->fetchOne(
            "SELECT s.*, r.name as region_name 
             FROM climbing_sites s
             JOIN climbing_regions r ON s.region_id = r.id
             WHERE s.id = ? AND s.active = 1
             AND r.id IN (SELECT id FROM climbing_regions WHERE active = 1 ORDER BY name ASC LIMIT 3)",
            [$siteId]
        );

        if (!$site) {
            $this->flash('warning', 'Ce site nécessite un compte pour être accessible.');
            return $this->redirect('/demo/regions');
        }

        // Secteurs limités pour la démo
        $sectors = $this->db->fetchAll(
            "SELECT sect.id, sect.name, sect.description,
                    COUNT(rt.id) as routes_count
             FROM climbing_sectors sect
             LEFT JOIN climbing_routes rt ON sect.id = rt.sector_id AND rt.active = 1
             WHERE sect.site_id = ? AND sect.active = 1
             GROUP BY sect.id
             ORDER BY sect.name ASC
             LIMIT ?",
            [$siteId, $limits['sectors_per_site']]
        );

        // Appliquer les limitations de démo
        foreach ($sectors as &$sector) {
            $sector['demo_mode'] = true;
            $sector['difficulty_range'] = 'Informations disponibles pour les membres';
            unset($sector['coordinates_lat'], $sector['coordinates_lng']);
        }

        return $this->render('demo/sectors', [
            'title' => 'Secteurs d\'escalade - ' . $site['name'] . ' (Aperçu)', 
            'site' => $site,
            'sectors' => $sectors,
            'demo_mode' => true,
            'limits' => $limits,
            'upgrade_message' => 'Découvrez tous les secteurs avec coordonnées exactes'
        ]);
    }

    /**
     * Voies de démonstration pour un secteur
     */
    public function routes(Request $request): Response
    {
        $sectorId = $request->query->get('sector_id');
        $accessControl = $request->attributes->get('access_control');
        $limits = $accessControl ? $accessControl->getContentLimits() : ['routes_per_sector' => 5];

        if (!$sectorId || !is_numeric($sectorId)) {
            return $this->redirect('/demo/regions');
        }

        // Vérifier que le secteur fait partie des secteurs de démo autorisés
        $sector = $this->db->fetchOne(
            "SELECT sect.*, s.name as site_name, r.name as region_name
             FROM climbing_sectors sect
             JOIN climbing_sites s ON sect.site_id = s.id
             JOIN climbing_regions r ON s.region_id = r.id
             WHERE sect.id = ? AND sect.active = 1
             AND r.id IN (SELECT id FROM climbing_regions WHERE active = 1 ORDER BY name ASC LIMIT 3)",
            [$sectorId]
        );

        if (!$sector) {
            $this->flash('warning', 'Ce secteur nécessite un compte pour être accessible.');
            return $this->redirect('/demo/regions');
        }

        // Voies limitées pour la démo
        $routes = $this->db->fetchAll(
            "SELECT rt.id, rt.name, rt.difficulty, rt.length
             FROM climbing_routes rt
             WHERE rt.sector_id = ? AND rt.active = 1
             ORDER BY rt.name ASC
             LIMIT ?",
            [$sectorId, $limits['routes_per_sector']]
        );

        // Appliquer les limitations de démo
        foreach ($routes as &$route) {
            $route['demo_mode'] = true;
            $route['description'] = 'Description complète disponible pour les membres';
            $route['first_ascent'] = null;
            $route['equipment'] = null;
        }

        return $this->render('demo/routes', [
            'title' => 'Voies d\'escalade - ' . $sector['name'] . ' (Aperçu)',
            'sector' => $sector,
            'routes' => $routes,
            'demo_mode' => true,
            'limits' => $limits,
            'upgrade_message' => 'Accédez aux descriptions complètes, équipements et conseils'
        ]);
    }
}