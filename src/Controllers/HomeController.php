<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController
{
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
        $content = $this->renderView('home/index.php', $data);
        $response->setContent($content);
        
        return $response;
    }
    
    /**
     * Méthode d'aide pour le rendu des vues
     *
     * @param string $view
     * @param array $data
     * @return string
     */
    private function renderView(string $view, array $data = []): string
    {
        $viewPath = BASE_PATH . '/resources/views/' . $view;
        
        if (!file_exists($viewPath)) {
            return 'Error: View file not found';
        }
        
        extract($data);
        
        ob_start();
        include $viewPath;
        return ob_get_clean();
    }
}