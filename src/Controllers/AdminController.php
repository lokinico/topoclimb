<?php
// src/Controllers/AdminController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;

class AdminController extends BaseController
{
    public function __construct(View $view, Session $session)
    {
        parent::__construct($view, $session);
    }
    
    public function index(): Response
    {
        return $this->render('admin/index.twig', [
            'title' => 'Administration'
        ]);
    }
    
    public function users(): Response
    {
        // Logique pour afficher la liste des utilisateurs
        return $this->render('admin/users.twig', [
            'title' => 'Gestion des utilisateurs'
        ]);
    }
}