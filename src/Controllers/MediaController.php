<?php
// src/Controllers/MediaController.php - Version finale simplifiée

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

class MediaController extends BaseController
{
    private MediaService $mediaService;

    public function __construct(
        View $view,
        Session $session,
        MediaService $mediaService,
        Database $db,
        CsrfManager $csrfManager,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
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
        try {
            // Récupérer tous les médias
            $medias = $this->db->fetchAll("SELECT * FROM climbing_media ORDER BY created_at DESC");
            
            return $this->render('media/index', [
                'medias' => $medias,
                'title' => 'Gestion des médias'
            ]);
        } catch (\Exception $e) {
            return new Response('Gestion des médias - Fonctionnalité en cours de développement', 200);
        }
    }

    public function uploadForm(Request $request): Response
    {
        try {
            $regionId = $request->get('id', 1);
            
            // Vérifier que la région existe
            $region = $this->db->fetchOne("SELECT * FROM climbing_regions WHERE id = ?", [$regionId]);
            
            if (!$region) {
                return new Response('Région non trouvée', 404);
            }
            
            return $this->render('media/upload-form', [
                'region' => $region,
                'title' => 'Upload de médias - ' . $region['name']
            ]);
        } catch (\Exception $e) {
            return new Response('Upload de médias - Fonctionnalité en cours de développement', 200);
        }
    }

    public function update(Request $request): Response
    {
        $this->session->flash('info', 'Fonctionnalité en cours de développement');
        return $this->redirect('/sectors');
    }

    /**
     * API: Liste des médias
     */
    public function apiIndex(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        try {
            $entityType = $request->query->get('entity_type', '');
            $entityId = $request->query->get('entity_id', '');
            $limit = min((int)$request->query->get('limit', 50), 200);

            // Construction de la requête de base
            $sql = "SELECT m.id, m.filename, m.title, m.description, m.file_size, 
                           m.mime_type, m.is_public, m.created_at,
                           mr.entity_type, mr.entity_id, mr.relationship_type
                    FROM climbing_media m 
                    LEFT JOIN climbing_media_relationships mr ON m.id = mr.media_id 
                    WHERE m.active = 1";
            $params = [];

            // Filtres optionnels
            if ($entityType && in_array($entityType, ['region', 'site', 'sector', 'route', 'book'])) {
                $sql .= " AND mr.entity_type = ?";
                $params[] = $entityType;
                
                if ($entityId && is_numeric($entityId)) {
                    $sql .= " AND mr.entity_id = ?";
                    $params[] = (int)$entityId;
                }
            }

            $sql .= " ORDER BY m.created_at DESC LIMIT ?";
            $params[] = $limit;

            $media = $this->db->fetchAll($sql, $params);

            // Formatage sécurisé des données
            $data = array_map(function ($item) {
                return [
                    'id' => (int)$item['id'],
                    'filename' => $item['filename'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'file_size' => $item['file_size'] ? (int)$item['file_size'] : null,
                    'mime_type' => $item['mime_type'],
                    'is_public' => (bool)$item['is_public'],
                    'entity_type' => $item['entity_type'],
                    'entity_id' => $item['entity_id'] ? (int)$item['entity_id'] : null,
                    'relationship_type' => $item['relationship_type'],
                    'created_at' => $item['created_at'],
                    'url' => $this->generateMediaUrl($item['filename'])
                ];
            }, $media);

            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            error_log('MediaController::apiIndex error: ' . $e->getMessage());
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération des médias'
            ], 500);
        }
    }

    /**
     * Génère l'URL d'un média
     */
    private function generateMediaUrl(string $filename): string
    {
        // Construction de l'URL basée sur la configuration
        $baseUrl = $_ENV['MEDIA_BASE_URL'] ?? '/uploads';
        return rtrim($baseUrl, '/') . '/' . $filename;
    }
}
