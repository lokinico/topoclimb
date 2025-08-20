<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TopoclimbCH\Core\Response;

class AscentController extends BaseController
{
    /**
     * Liste des ascensions
     */
    public function index(Request $request): Response
    {
        try {
            // Pour le moment, rediriger vers la page d'accueil 
            // car la table ascents n'est pas encore implémentée en production
            $this->session->flash('info', 'La section ascensions sera bientôt disponible');
            return Response::redirect('/');
            
        } catch (\Exception $e) {
            error_log("AscentController::index error: " . $e->getMessage());
            return Response::redirect('/');
        }
    }

    /**
     * Formulaire de création d'ascension
     */
    public function create(Request $request): Response
    {
        try {
            // Vérifier que les tables nécessaires existent
            $routes = [];
            $climbers = [];
            
            try {
                // Essayer climbing_routes d'abord
                $routes = $this->db->fetchAll(
                    "SELECT r.id, r.name, r.difficulty, s.name as site_name, sect.name as sector_name
                     FROM climbing_routes r 
                     JOIN climbing_sectors sect ON r.sector_id = sect.id
                     JOIN climbing_sites s ON sect.site_id = s.id
                     WHERE r.active = 1 
                     ORDER BY s.name, sect.name, r.name 
                     LIMIT 100"
                );
                
                // Essayer climbing_users pour les grimpeurs
                $climbers = $this->db->fetchAll("SELECT id, username FROM climbing_users WHERE active = 1 ORDER BY username LIMIT 50");
                
            } catch (\Exception $e) {
                error_log("AscentController::create error: " . $e->getMessage());
                // Si les tables n'existent pas, rediriger
                $this->session->flash('error', 'Les données nécessaires ne sont pas disponibles');
                return Response::redirect('/ascents');
            }

            return $this->render('ascents/create', [
                'title' => 'Enregistrer une ascension',
                'routes' => $routes,
                'climbers' => $climbers,
                'csrf_token' => $this->generateCsrfToken()
            ]);
            
        } catch (\Exception $e) {
            error_log("AscentController::create error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors du chargement du formulaire');
            return Response::redirect('/ascents');
        }
    }

    /**
     * Traitement création ascension
     */
    public function store(Request $request): Response
    {
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide');
            return Response::redirect('/ascents/create');
        }

        try {
            // Pour le moment, simuler la création
            $this->session->flash('success', 'Ascension enregistrée avec succès (fonctionnalité en développement)');
            return Response::redirect('/ascents');
            
        } catch (\Exception $e) {
            error_log("AscentController::store error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de l\'enregistrement');
            return Response::redirect('/ascents/create');
        }
    }

    /**
     * Afficher une ascension
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        try {
            // Pour le moment, rediriger car pas encore implémenté
            $this->session->flash('info', 'Fonctionnalité bientôt disponible');
            return Response::redirect('/ascents');
            
        } catch (\Exception $e) {
            error_log("AscentController::show error: " . $e->getMessage());
            return Response::redirect('/ascents');
        }
    }

    /**
     * Formulaire d'édition d'ascension
     */
    public function edit(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        try {
            // Pour le moment, rediriger car pas encore implémenté
            $this->session->flash('info', 'Fonctionnalité d\'édition bientôt disponible');
            return Response::redirect('/ascents');
            
        } catch (\Exception $e) {
            error_log("AscentController::edit error: " . $e->getMessage());
            return Response::redirect('/ascents');
        }
    }

    /**
     * Mise à jour d'une ascension
     */
    public function update(Request $request): Response
    {
        $id = $request->attributes->get('id');
        
        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide');
            return Response::redirect('/ascents/' . $id . '/edit');
        }

        try {
            // Pour le moment, simuler la mise à jour
            $this->session->flash('success', 'Ascension mise à jour avec succès (fonctionnalité en développement)');
            return Response::redirect('/ascents/' . $id);
            
        } catch (\Exception $e) {
            error_log("AscentController::update error: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la mise à jour');
            return Response::redirect('/ascents/' . $id . '/edit');
        }
    }
}
