<?php
// src/Controllers/SiteController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Security\CsrfManager;

class SiteController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        MediaService $mediaService,
        RegionService $regionService,
        Database $db
    ) {
        parent::__construct($view, $session, $csrfManager);
        $this->mediaService = $mediaService;
        $this->regionService = $regionService;
        $this->db = $db;
    }

    public function index(): Response
    {
        return $this->redirect('/');
    }
}
