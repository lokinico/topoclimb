<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Controllers\BaseController;

class PhotoController extends BaseController
{
    /**
     * Galerie photos générale
     */
    public function index()
    {
        try {
            // Récupérer les photos récentes avec informations associées
            $photos = $this->db->fetchAll(
                "SELECT p.*, u.username, r.name as route_name, s.name as sector_name 
                 FROM photos p
                 LEFT JOIN users u ON p.user_id = u.id
                 LEFT JOIN climbing_routes r ON p.route_id = r.id
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 WHERE p.active = 1
                 ORDER BY p.created_at DESC
                 LIMIT 24"
            );
            
            return $this->render('photos/index.twig', [
                'photos' => $photos,
                'page_title' => 'Galerie Photos'
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur (table photos n'existe pas encore), afficher une page vide
            return $this->render('photos/index.twig', [
                'photos' => [],
                'page_title' => 'Galerie Photos',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Détails d'une photo
     */
    public function show($id)
    {
        try {
            $photoId = $this->validateId($id, 'Photo ID');
            
            $photo = $this->db->fetchOne(
                "SELECT p.*, u.username, r.name as route_name, s.name as sector_name, s.id as sector_id
                 FROM photos p
                 LEFT JOIN users u ON p.user_id = u.id
                 LEFT JOIN climbing_routes r ON p.route_id = r.id
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 WHERE p.id = ? AND p.active = 1",
                [$photoId]
            );
            
            if (!$photo) {
                $this->flash('error', 'Photo non trouvée');
                return $this->redirect('/photos');
            }
            
            return $this->render('photos/show.twig', [
                'photo' => $photo,
                'page_title' => 'Photo - ' . ($photo['route_name'] ?: 'Sans titre')
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'PhotoController::show');
            return $this->redirect('/photos');
        }
    }
    
    /**
     * Photos d'une voie spécifique
     */
    public function byRoute($routeId)
    {
        try {
            $routeId = $this->validateId($routeId, 'Route ID');
            
            // Récupérer les informations de la voie
            $route = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name 
                 FROM climbing_routes r
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 WHERE r.id = ?",
                [$routeId]
            );
            
            if (!$route) {
                $this->flash('error', 'Voie non trouvée');
                return $this->redirect('/photos');
            }
            
            // Récupérer les photos de cette voie
            $photos = $this->db->fetchAll(
                "SELECT p.*, u.username 
                 FROM photos p
                 LEFT JOIN users u ON p.user_id = u.id
                 WHERE p.route_id = ? AND p.active = 1
                 ORDER BY p.created_at DESC",
                [$routeId]
            );
            
            return $this->render('photos/by-route.twig', [
                'photos' => $photos,
                'route' => $route,
                'page_title' => 'Photos - ' . $route['name']
            ]);
        } catch (\Exception $e) {
            return $this->render('photos/by-route.twig', [
                'photos' => [],
                'route' => ['name' => 'Voie inconnue'],
                'page_title' => 'Photos de voie',
                'coming_soon' => true
            ]);
        }
    }
    
    /**
     * Upload de photo (nécessite authentification)
     */
    public function upload()
    {
        $this->requireAuth();
        
        try {
            // Récupérer les voies pour le sélecteur
            $routes = $this->db->fetchAll(
                "SELECT r.id, r.name, s.name as sector_name
                 FROM climbing_routes r
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 ORDER BY s.name, r.name
                 LIMIT 100"
            );
            
            return $this->render('photos/upload.twig', [
                'routes' => $routes,
                'page_title' => 'Ajouter des Photos',
                'coming_soon' => true // Feature pas encore implémentée
            ]);
        } catch (\Exception $e) {
            $this->handleError($e, 'PhotoController::upload');
            return $this->redirect('/photos');
        }
    }
    
    /**
     * Traitement upload de photo
     */
    public function store(Request $request)
    {
        $this->requireAuth();
        $this->requireCsrfToken($request);
        
        try {
            // TODO: Implémenter l'upload réel de photos
            $this->flash('info', 'Fonctionnalité d\'upload en cours de développement');
            return $this->redirect('/photos');
            
        } catch (\Exception $e) {
            $this->handleError($e, 'PhotoController::store');
            $this->flash('error', 'Erreur lors de l\'upload');
            return $this->redirect('/photos/upload');
        }
    }
}