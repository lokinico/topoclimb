<?php
// src/Controllers/AscentController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Auth;

class AscentController extends BaseController
{
    // Changez private en protected (ou public) selon la définition dans BaseController
    protected $auth;
    
    public function __construct(View $view, Session $session, Auth $auth)
    {
        parent::__construct($view, $session);
        $this->auth = $auth;
    }
    
    public function create(int $route_id): Response
    {
        return $this->render('ascents/create.twig', [
            'title' => 'Ajouter une ascension',
            'route_id' => $route_id
        ]);
    }
    
    public function store(): Response
    {
        // Logique pour enregistrer une nouvelle ascension
        $this->session->flash('success', 'Ascension enregistrée avec succès');
        return $this->redirect('/mes-voies');
    }
}