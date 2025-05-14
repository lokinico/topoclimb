<?php
// src/Controllers/SiteController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;

class SiteController extends BaseController
{
    public function __construct(View $view, Session $session)
    {
        parent::__construct($view, $session);
    }
    
    public function index(): Response
    {
        return $this->redirect('/');
    }
}