<?php
// src/Controllers/HomeController.php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\View;

class HomeController
{
    /**
     * @var View
     */
    private View $view;

    /**
     * Constructor
     *
     * @param View $view
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Page d'accueil
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $response = new Response();
        
        // Récupérer les données pour la page d'accueil
        $data = [
            'title' => 'Bienvenue sur TopoclimbCH',
            'description' => 'La plateforme de gestion des sites d\'escalade en Suisse',
        ];
        
        // Rendre la vue
        $content = $this->view->render('home/index.php', $data);
        $response->setContent($content);
        
        return $response;
    }
}