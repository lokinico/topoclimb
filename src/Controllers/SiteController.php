<?php
// src/Controllers/SiteController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Security\CsrfManager;

class SiteController extends BaseController
{
    public function __construct(View $view, Session $session, CsrfManager $csrfManager)
    {
        parent::__construct($view, $session, $csrfManager);
    }

    public function index(): Response
    {
        return $this->redirect('/');
    }
}
