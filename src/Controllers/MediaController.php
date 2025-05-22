<?php
// src/Controllers/MediaController.php - Version corrigée

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Services\MediaService;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Media;

class MediaController extends BaseController
{
    private MediaService $mediaService;
    private Database $db;

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
     * Serve media files
     */
    public function serve(Request $request): Response
    {
        $path = $request->getPathInfo();
        $path = ltrim($path, '/');
        $uploadsPath = BASE_PATH . '/public/uploads';
        $fullPath = $uploadsPath . '/' . $path;

        $realUploadsPath = realpath($uploadsPath);
        $realFilePath = realpath($fullPath);

        if (!$realFilePath || !str_starts_with($realFilePath, $realUploadsPath)) {
            return new Response('Forbidden', 403);
        }

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return new Response('File not found', 404);
        }

        $mimeType = $this->mediaService->detectMimeType($fullPath);
        $response = new BinaryFileResponse($fullPath);
        $response->headers->set('Content-Type', $mimeType);

        if (str_starts_with($mimeType, 'image/')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000');
            $response->setLastModified(new \DateTime('@' . filemtime($fullPath)));
        }

        return $response;
    }

    /**
     * Show media details
     */
    public function show(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du média non spécifié');
            return $this->redirect('/media');
        }

        try {
            $media = $this->mediaService->getMediaById($id);

            if (!$media) {
                $this->session->flash('error', 'Média non trouvé');
                return $this->redirect('/media');
            }

            $relations = $this->db->fetchAll(
                "SELECT mr.*, 
                        CASE 
                            WHEN mr.entity_type = 'sector' THEN s.name
                            WHEN mr.entity_type = 'route' THEN r.name
                            ELSE CONCAT(mr.entity_type, ' #', mr.entity_id)
                        END as entity_name
                 FROM climbing_media_relationships mr
                 LEFT JOIN climbing_sectors s ON mr.entity_type = 'sector' AND mr.entity_id = s.id
                 LEFT JOIN climbing_routes r ON mr.entity_type = 'route' AND mr.entity_id = r.id
                 WHERE mr.media_id = ?
                 ORDER BY mr.entity_type, mr.entity_id",
                [$id]
            );

            return $this->render('media/show', [
                'media' => $media,
                'relations' => $relations,
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return $this->redirect('/media');
        }
    }

    /**
     * Update media
     */
    public function update(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du média non spécifié');
            return $this->redirect('/media');
        }

        if (!$this->validateCsrfToken($request)) {
            $this->session->flash('error', 'Token de sécurité invalide');
            return $this->redirect('/media/' . $id . '/edit');
        }

        try {
            $data = $request->request->all();

            $updated = $this->mediaService->updateMedia($id, [
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'is_public' => isset($data['is_public']) ? 1 : 0,
                'is_featured' => isset($data['is_featured']) ? 1 : 0
            ]);

            if (!$updated) {
                $this->session->flash('error', 'Erreur lors de la mise à jour du média');
                return $this->redirect('/media/' . $id . '/edit');
            }

            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                $currentMedia = $this->mediaService->getMediaById($id);

                if ($currentMedia) {
                    $oldPath = BASE_PATH . '/public/uploads' . $currentMedia['file_path'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }

                    $userId = $_SESSION['auth_user_id'] ?? 1;
                    $newMediaId = $this->mediaService->uploadMedia($_FILES['media_file'], [
                        'title' => $data['title'] ?? $currentMedia['title'],
                        'description' => $data['description'] ?? $currentMedia['description'],
                        'is_public' => isset($data['is_public']) ? 1 : 0,
                        'is_featured' => isset($data['is_featured']) ? 1 : 0,
                        'media_type' => $currentMedia['media_type']
                    ], $userId);

                    if ($newMediaId) {
                        $newMedia = $this->mediaService->getMediaById($newMediaId);
                        $this->db->update('climbing_media', [
                            'filename' => $newMedia['filename'],
                            'file_path' => $newMedia['file_path'],
                            'file_size' => $newMedia['file_size'],
                            'mime_type' => $newMedia['mime_type'],
                            'metadata' => $newMedia['metadata'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$id]);

                        $this->db->delete('climbing_media', 'id = ?', [$newMediaId]);
                    }
                }
            }

            if (isset($data['relation_types']) && is_array($data['relation_types'])) {
                foreach ($data['relation_types'] as $relationId => $relationshipType) {
                    $this->db->update(
                        'climbing_media_relationships',
                        ['relationship_type' => $relationshipType],
                        'id = ?',
                        [(int)$relationId]
                    );
                }
            }

            if (isset($data['delete_relations']) && is_array($data['delete_relations'])) {
                foreach ($data['delete_relations'] as $relationId) {
                    $this->db->delete('climbing_media_relationships', 'id = ?', [(int)$relationId]);
                }
            }

            $this->session->flash('success', 'Média mis à jour avec succès');
            return $this->redirect('/media/' . $id);
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
            return $this->redirect('/media/' . $id . '/edit');
        }
    }

    /**
     * Delete media - VERSION CORRIGÉE
     */
    public function delete(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');

        if (!$id) {
            $this->session->flash('error', 'ID du média non spécifié');
            return $this->redirect('/sectors');
        }

        // Récupérer l'information sur l'entité d'origine AVANT validation
        $mediaRelation = null;
        try {
            $mediaRelation = $this->db->fetchOne(
                "SELECT entity_type, entity_id FROM climbing_media_relationships WHERE media_id = ? LIMIT 1",
                [$id]
            );
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération de la relation média: " . $e->getMessage());
        }

        // CORRECTION PRINCIPALE : Récupérer le token depuis la query string
        $csrfToken = $request->query->get('csrf_token');

        // Utiliser la méthode héritée de BaseController avec le token récupéré
        if (!$this->session->validateCsrfToken($csrfToken)) {
            error_log("MediaController::delete - Token CSRF invalide. Token reçu: " . ($csrfToken ?: 'null'));
            $this->session->flash('error', 'Token de sécurité invalide');

            // Redirection intelligente vers l'entité d'origine
            if ($mediaRelation && $mediaRelation['entity_type'] === 'sector') {
                return $this->redirect('/sectors/' . $mediaRelation['entity_id'] . '/edit');
            }
            return $this->redirect('/sectors');
        }

        try {
            // Utiliser le modèle Media pour la suppression avec fichiers
            $deleted = Media::deleteWithFiles($id);

            if ($deleted) {
                $this->session->flash('success', 'Média supprimé avec succès');
            } else {
                $this->session->flash('error', 'Erreur lors de la suppression du média');
            }
        } catch (\Exception $e) {
            error_log("Erreur lors de la suppression du média $id: " . $e->getMessage());
            $this->session->flash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        // Redirection intelligente après suppression
        if ($mediaRelation && $mediaRelation['entity_type'] === 'sector') {
            return $this->redirect('/sectors/' . $mediaRelation['entity_id'] . '/edit');
        }

        return $this->redirect('/sectors');
    }

    /**
     * Liste tous les médias
     */
    public function index(Request $request): Response
    {
        try {
            $page = (int) $request->query->get('page', 1);
            $perPage = (int) $request->query->get('per_page', 20);

            $filters = [
                'media_type' => $request->query->get('media_type'),
                'entity_type' => $request->query->get('entity_type'),
                'search' => $request->query->get('search')
            ];

            $filters = array_filter($filters);
            $result = $this->mediaService->getAllMedia($page, $perPage, $filters);

            return $this->render('media/index', [
                'title' => 'Gestion des médias',
                'medias' => $result['data'],
                'pagination' => [
                    'current_page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'last_page' => $result['last_page']
                ],
                'filters' => $filters,
                'stats' => $this->mediaService->getMediaStats(),
                'csrf_token' => $this->createCsrfToken()
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans MediaController::index: " . $e->getMessage());
            $this->session->flash('error', 'Une erreur est survenue lors du chargement des médias');
            return $this->redirect('/sectors');
        }
    }
}
