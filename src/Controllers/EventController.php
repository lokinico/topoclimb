<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Response;

class EventController extends BaseController
{
    /**
     * Liste des événements
     */
    public function index(Request $request): Response
    {
        try {
            // Pour le moment, rediriger vers la page d'accueil 
            // car la table events n'existe pas encore en production
            $this->session->flash('info', 'La section événements sera bientôt disponible');
            return Response::redirect('/');
            
        } catch (\Exception $e) {
            error_log("EventController::index error: " . $e->getMessage());
            return Response::redirect('/');
        }
    }

    /**
     * Formulaire de création d'événement
     */
    public function create(Request $request): Response
    {
        try {
            // Vérifier que les tables nécessaires existent
            // En production, la table regions utilise un nom différent
            $regions = [];
            
            try {
                // Essayer climbing_regions d'abord
                $regions = $this->db->fetchAll("SELECT id, name FROM climbing_regions ORDER BY name");
            } catch (\Exception $e) {
                error_log("EventController::create error: " . $e->getMessage());
                // Si les tables n'existent pas, rediriger
                $this->session->flash('error', 'Les données nécessaires ne sont pas disponibles');
                return Response::redirect('/events');
            }

            return $this->render('events/create', [
                'title' => 'Créer un événement',
                'regions' => $regions,
                'csrf_token' => $this->generateCsrfToken()
            ]);
            
        } catch (\Exception $e) {
            error_log("EventController::create error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/events');
        }
    }

    /**
     * Traitement création événement
     */
    public function store(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide');
            return Response::redirect('/events/create');
        }

        try {
            // Pour le moment, simuler la création
            $this->session->flash('success', 'Événement créé avec succès (fonctionnalité en développement)');
            return Response::redirect('/events');
            
        } catch (\Exception $e) {
            error_log("EventController::store error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la création');
            return Response::redirect('/events/create');
        }
    }
}
?>