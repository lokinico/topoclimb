<?php
// src/Controllers/AscentController.php
namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

class AscentController extends BaseController
{
    // Option 1: Déclarer avec le même type que dans BaseController
    // protected ?Auth $auth;

    // Option 2 (recommandée): Ne pas redéclarer la propriété du tout
    // et simplement utiliser celle héritée de BaseController

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
