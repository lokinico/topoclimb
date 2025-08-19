<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

/**
 * Contrôleur pour les aperçus très limités
 * Contenu ultra-restreint pour visiteurs publics
 */
class PreviewController extends BaseController
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
     * Aperçu d'une région - très limité
     */
    public function region(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        if (!$id || !is_numeric($id)) {
            return $this->redirect('/demo/regions');
        }

        // Seules les 3 premières régions sont prévisualisables
        $allowedRegions = $this->db->fetchAll(
            "SELECT id FROM climbing_regions WHERE active = 1 ORDER BY name ASC LIMIT 3"
        );
        $allowedIds = array_column($allowedRegions, 'id');
        
        if (!in_array($id, $allowedIds)) {
            $this->flash('info', 'Cette région nécessite un compte gratuit pour être consultée.');
            return $this->redirect('/register');
        }

        $region = $this->db->fetchOne(
            "SELECT r.id, r.name, LEFT(r.description, 200) as description,
                    COUNT(DISTINCT s.id) as sites_count
             FROM climbing_regions r
             LEFT JOIN climbing_sites s ON r.id = s.region_id AND s.active = 1
             WHERE r.id = ? AND r.active = 1
             GROUP BY r.id",
            [$id]
        );

        if (!$region) {
            return $this->redirect('/demo/regions');
        }

        // Quelques sites d'exemple seulement
        $sampleSites = $this->db->fetchAll(
            "SELECT s.id, s.name, LEFT(s.description, 150) as description
             FROM climbing_sites s
             WHERE s.region_id = ? AND s.active = 1
             ORDER BY s.name ASC
             LIMIT 2",
            [$id]
        );

        // Masquer toute information sensible
        foreach ($sampleSites as &$site) {
            $site['preview_only'] = true;
            $site['access_blocked'] = true;
        }

        return $this->render('preview/region', [
            'title' => 'Aperçu - ' . $region['name'],
            'region' => $region,
            'sample_sites' => $sampleSites,
            'preview_mode' => true,
            'total_hidden' => max(0, $region['sites_count'] - 2),
            'cta_message' => 'Créez un compte gratuit pour découvrir les ' . $region['sites_count'] . ' sites d\'escalade de cette région'
        ]);
    }

    /**
     * Page "Accès bloqué" pour contenu nécessitant inscription
     */
    public function blocked(Request $request): Response
    {
        $type = $request->query->get('type', 'content');
        $name = $request->query->get('name', 'ce contenu');

        return $this->render('preview/blocked', [
            'title' => 'Inscription requise',
            'content_type' => $type,
            'content_name' => $name,
            'benefits' => [
                'Accès complet à toutes les régions d\'escalade',
                'Coordonnées GPS précises des sites',
                'Descriptions détaillées des voies',
                'Conditions météo en temps réel',
                'Guides d\'escalade complets',
                'Communauté de grimpeurs'
            ]
        ]);
    }
}