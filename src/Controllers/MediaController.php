<?php
// src/Controllers/MediaController.php - Version finale simplifiée

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Security\CsrfManager;

class MediaController extends BaseController
{
    private MediaService $mediaService;

    public function __construct(
        View $view,
        Session $session,
        MediaService $mediaService,
        Database $db
    ) {
        parent::__construct($view, $session);
        $this->mediaService = $mediaService;
        $this->db = $db;
    }

    /**
     * Delete media - VERSION FINALE
     */
    public function delete(Request $request): Response
    {
        error_log("MediaController::delete - DÉBUT");

        try {
            $id = (int) $request->attributes->get('id');

            if (!$id) {
                $this->session->flash('error', 'ID du média non spécifié');
                return $this->redirect('/sectors');
            }

            // Récupérer l'entité d'origine pour la redirection
            $mediaRelation = $this->db->fetchOne(
                "SELECT entity_type, entity_id FROM climbing_media_relationships WHERE media_id = ? LIMIT 1",
                [$id]
            );

            // Validation CSRF
            if (!$this->validateCsrfToken($request)) {
                $this->session->flash('error', 'Token de sécurité invalide');

                if ($mediaRelation && $mediaRelation['entity_type'] === 'sector') {
                    return $this->redirect('/sectors/' . $mediaRelation['entity_id'] . '/edit');
                }
                return $this->redirect('/sectors');
            }

            // Suppression simplifiée
            $this->db->beginTransaction();

            // Récupérer les infos du média
            $media = $this->db->fetchOne("SELECT * FROM climbing_media WHERE id = ?", [$id]);

            if ($media) {
                // Supprimer le fichier physique
                $filePath = BASE_PATH . '/public/uploads' . $media['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Supprimer les relations et le média
                $this->db->delete('climbing_media_relationships', 'media_id = ?', [$id]);
                $this->db->delete('climbing_media_annotations', 'media_id = ?', [$id]);
                $this->db->delete('climbing_media_tags', 'media_id = ?', [$id]);
                $this->db->delete('climbing_media', 'id = ?', [$id]);

                $this->db->commit();
                $this->session->flash('success', 'Média supprimé avec succès');
            } else {
                $this->db->rollBack();
                $this->session->flash('error', 'Média non trouvé');
            }

            // Redirection intelligente
            if ($mediaRelation && $mediaRelation['entity_type'] === 'sector') {
                return $this->redirect('/sectors/' . $mediaRelation['entity_id'] . '/edit');
            }

            return $this->redirect('/sectors');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log("MediaController::delete - Erreur: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            return $this->redirect('/sectors');
        }
    }

    // Méthodes basiques pour éviter les erreurs
    public function show(Request $request): Response
    {
        $this->session->flash('info', 'Fonctionnalité en cours de développement');
        return $this->redirect('/sectors');
    }

    public function index(Request $request): Response
    {
        $this->session->flash('info', 'Fonctionnalité en cours de développement');
        return $this->redirect('/sectors');
    }

    public function update(Request $request): Response
    {
        $this->session->flash('info', 'Fonctionnalité en cours de développement');
        return $this->redirect('/sectors');
    }
}
