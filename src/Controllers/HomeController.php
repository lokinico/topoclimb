<?php
// src/Controllers/HomeController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;

class HomeController extends BaseController
{
    /**
     * Constructor
     *
     * @param View $view
     * @param Session $session
     */
    public function __construct(View $view, Session $session)
    {
        parent::__construct($view, $session, $csrfManager);
    }

    /**
     * Page d'accueil
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Récupérer les données pour la page d'accueil
        $data = [
            'title' => 'Bienvenue sur TopoclimbCH',
            'description' => 'La plateforme de gestion des sites d\'escalade en Suisse',
        ];

        // Utiliser la méthode render du BaseController
        return $this->render('home/index', $data);
    }
}
