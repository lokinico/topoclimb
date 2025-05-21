<?php
// src/Services/MediaService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use Intervention\Image\ImageManager;

class MediaService
{
    /**
     * @var Database
     */
    private Database $db;

    /**
     * @var string
     */
    private string $uploadsPath;

    /**
     * Constructor
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->uploadsPath = BASE_PATH . '/public/uploads';
    }

    /**
     * Get media by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getMediaById(int $id): ?array
    {
        $sql = "SELECT * FROM climbing_media WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Upload a new media file
     *
     * @param array $file File from $_FILES
     * @param array $data Additional data
     * @param int $userId ID of the user uploading the file
     * @return int|null ID of the new media or null on failure
     */
    public function uploadMedia(array $file, array $data, int $userId): ?int
    {
        // Check if file is valid
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        // Generate a unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('media_') . '.' . $extension;
        $year = date('Y');
        $month = date('m');
        $relativePath = "/media/$year/$month";
        $filePath = $this->uploadsPath . $relativePath;

        // Create directory if it doesn't exist
        if (!is_dir($filePath)) {
            mkdir($filePath, 0755, true);
        }

        // Full file path
        $fullPath = "$filePath/$filename";

        // Determine media type
        $mediaType = 'image';
        $mimeType = $file['type'];

        if (strpos($mimeType, 'application/pdf') === 0) {
            $mediaType = 'pdf';
        } elseif (strpos($mimeType, 'video/') === 0) {
            $mediaType = 'video';
        } elseif (isset($data['media_type']) && $data['media_type'] === 'topo') {
            $mediaType = 'topo';
        }

        // Move the uploaded file
        move_uploaded_file($file['tmp_name'], $fullPath);

        // Process image with Intervention\Image if needed
        $metadata = [];
        if ($mediaType === 'image' && class_exists('\Intervention\Image\ImageManager')) {
            $imageManager = new ImageManager(['driver' => 'gd']);
            $image = $imageManager->make($fullPath);

            // Resize if necessary
            if ($image->width() > 2000 || $image->height() > 2000) {
                $image->resize(2000, 2000, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $image->save($fullPath, 85);
            }

            // Create thumbnail
            $thumbPath = "$filePath/thumb_$filename";
            $image->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->save($thumbPath, 75);

            // Extract metadata
            $metadata = [
                'width' => $image->width(),
                'height' => $image->height(),
                'thumbnails' => [
                    'thumb' => "$relativePath/thumb_$filename"
                ]
            ];
        }

        // Insert into database
        $mediaData = [
            'media_type' => $mediaType,
            'filename' => $filename,
            'file_path' => "$relativePath/$filename",
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'is_public' => $data['is_public'] ?? 1,
            'is_featured' => $data['is_featured'] ?? 0,
            'storage_type' => 'local',
            'original_filename' => $file['name'],
            'metadata' => json_encode($metadata),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $mediaId = $this->db->insert('climbing_media', $mediaData);

        // If entity info is provided, create relationship
        if ($mediaId && isset($data['entity_type']) && isset($data['entity_id'])) {
            $this->associateMediaWithEntity(
                $mediaId,
                $data['entity_type'],
                (int) $data['entity_id'],
                $data['relationship_type'] ?? 'gallery',
                $data['sort_order'] ?? 0
            );
        }

        return $mediaId;
    }

    /**
     * Associate media with an entity
     *
     * @param int $mediaId
     * @param string $entityType
     * @param int $entityId
     * @param string $relationshipType
     * @param int $sortOrder
     * @return bool
     */
    public function associateMediaWithEntity(
        int $mediaId,
        string $entityType,
        int $entityId,
        string $relationshipType = 'gallery',
        int $sortOrder = 0
    ): bool {
        // Check if association already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM climbing_media_relationships 
             WHERE media_id = ? AND entity_type = ? AND entity_id = ? AND relationship_type = ?",
            [$mediaId, $entityType, $entityId, $relationshipType]
        );

        if ($existing) {
            return $this->db->update(
                'climbing_media_relationships',
                ['sort_order' => $sortOrder],
                "id = ?",
                [$existing['id']]
            ) > 0;
        }

        // If it's a 'main' relationship, we need to update any existing main relationships for this entity
        if ($relationshipType === 'main') {
            $this->db->update(
                'climbing_media_relationships',
                ['relationship_type' => 'gallery'],
                "entity_type = ? AND entity_id = ? AND relationship_type = 'main'",
                [$entityType, $entityId]
            );
        }

        // Create new association
        $data = [
            'media_id' => $mediaId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'relationship_type' => $relationshipType,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('climbing_media_relationships', $data) > 0;
    }

    /**
     * Delete media
     *
     * @param int $mediaId
     * @return bool
     */
    public function deleteMedia(int $mediaId): bool
    {
        // First get the media data
        $media = $this->getMediaById($mediaId);

        if (!$media) {
            return false;
        }

        // Delete the file
        $filePath = $this->uploadsPath . $media['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete thumbnails if they exist
        $metadata = json_decode($media['metadata'] ?? '{}', true);
        if (isset($metadata['thumbnails'])) {
            foreach ($metadata['thumbnails'] as $thumbPath) {
                $fullThumbPath = $this->uploadsPath . $thumbPath;
                if (file_exists($fullThumbPath)) {
                    unlink($fullThumbPath);
                }
            }
        }

        // Delete relationships
        $this->db->delete('climbing_media_relationships', "media_id = ?", [$mediaId]);

        // Delete the media record
        return $this->db->delete('climbing_media', "id = ?", [$mediaId]) > 0;
    }

    /**
     * Récupère les médias associés à une entité
     * 
     * @param string $entityType Type d'entité (sector, route, etc.)
     * @param int $entityId ID de l'entité
     * @param string|null $relationshipType Type de relation optionnel (main, gallery, etc.)
     * @return array
     */
    public function getMediaForEntity(string $entityType, int $entityId, ?string $relationshipType = null): array
    {
        $sql = "
        SELECT m.*, mr.relationship_type, mr.sort_order
        FROM climbing_media m
        JOIN climbing_media_relationships mr ON m.id = mr.media_id
        WHERE mr.entity_type = ? AND mr.entity_id = ?
    ";

        $params = [$entityType, $entityId];

        if ($relationshipType !== null) {
            $sql .= " AND mr.relationship_type = ?";
            $params[] = $relationshipType;
        }

        $sql .= " ORDER BY mr.relationship_type = 'main' DESC, mr.sort_order ASC, m.id DESC";

        $medias = $this->db->fetchAll($sql, $params);

        // Ajouter URLs complètes et métadonnées traitées
        foreach ($medias as &$media) {
            $this->enhanceMediaData($media);
        }

        return $medias;
    }

    /**
     * Récupère le média principal (main) pour une entité
     *
     * @param string $entityType Type d'entité
     * @param int $entityId ID de l'entité
     * @return array|null
     */
    public function getMainMediaForEntity(string $entityType, int $entityId): ?array
    {
        $media = $this->getMediaForEntity($entityType, $entityId, 'main');
        return $media[0] ?? null;
    }

    /**
     * Améliore les données d'un média avec des URLs et des métadonnées traitées
     *
     * @param array &$media Données du média à améliorer
     */
    private function enhanceMediaData(array &$media): void
    {
        // Ajouter l'URL complète
        $media['url'] = $this->getMediaUrl($media['file_path']);

        // Traiter les métadonnées
        if (!empty($media['metadata'])) {
            $metadata = json_decode($media['metadata'], true) ?: [];
            $media['metadata_array'] = $metadata;

            // Ajouter les URLs des miniatures
            if (isset($metadata['thumbnails'])) {
                $media['thumbnails'] = [];
                foreach ($metadata['thumbnails'] as $key => $path) {
                    $media['thumbnails'][$key] = $this->getMediaUrl($path);
                }
            }

            // Ajouter les dimensions
            if (isset($metadata['width']) && isset($metadata['height'])) {
                $media['width'] = $metadata['width'];
                $media['height'] = $metadata['height'];
            }
        }
    }

    /**
     * Convertit un chemin de fichier en URL complète
     *
     * @param string $filePath Chemin du fichier
     * @return string URL complète
     */
    public function getMediaUrl(string $filePath): string
    {
        // Si c'est déjà une URL, la retourner telle quelle
        if (filter_var($filePath, FILTER_VALIDATE_URL)) {
            return $filePath;
        }

        // Assurer que le chemin commence par '/'
        if ($filePath && $filePath[0] !== '/') {
            $filePath = '/' . $filePath;
        }

        // Construire l'URL complète
        return rtrim(BASE_URL, '/') . $filePath;
    }

    /**
     * Détecte le type MIME d'un fichier
     *
     * @param string $filePath Chemin du fichier
     * @return string Type MIME
     */
    public function detectMimeType(string $filePath): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        // Fallback basé sur l'extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Génère les chemins pour un nouveau média
     *
     * @param string $originalFilename Nom du fichier original
     * @param string $mediaType Type de média
     * @return array Tableau avec les chemins générés
     */
    public function generateMediaPaths(string $originalFilename, string $mediaType = 'image'): array
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = uniqid('media_') . '.' . $extension;
        $year = date('Y');
        $month = date('m');
        $relativePath = "/media/$year/$month";
        $filePath = $this->uploadsPath . $relativePath;

        return [
            'filename' => $filename,
            'relative_path' => "$relativePath/$filename",
            'full_path' => "$filePath/$filename",
            'directory' => $filePath,
        ];
    }

    /**
     * Retourne la liste de tous les médias, avec pagination
     *
     * @param int $page Page courante
     * @param int $perPage Nombre d'éléments par page
     * @param array $filters Filtres optionnels (media_type, entity_type, etc.)
     * @return array
     */
    public function getAllMedia(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $whereClause = "1=1";
        $params = [];

        // Appliquer les filtres
        if (!empty($filters['media_type'])) {
            $whereClause .= " AND media_type = ?";
            $params[] = $filters['media_type'];
        }

        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $whereClause .= " AND id IN (
            SELECT media_id FROM climbing_media_relationships 
            WHERE entity_type = ? AND entity_id = ?
        )";
            $params[] = $filters['entity_type'];
            $params[] = $filters['entity_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $whereClause .= " AND (title LIKE ? OR description LIKE ? OR filename LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Requête principale
        $sql = "SELECT * FROM climbing_media WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $medias = $this->db->fetchAll($sql, $params);

        // Compter le total
        $countSql = "SELECT COUNT(*) FROM climbing_media WHERE $whereClause";
        $total = (int)$this->db->fetchColumn($countSql, array_slice($params, 0, -2));

        // Enrichir les données
        foreach ($medias as &$media) {
            $this->enhanceMediaData($media);
        }

        return [
            'data' => $medias,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Met à jour les métadonnées d'un média
     *
     * @param int $mediaId ID du média
     * @param array $metadata Nouvelles métadonnées à fusionner
     * @return bool
     */
    public function updateMediaMetadata(int $mediaId, array $metadata): bool
    {
        // Récupérer les métadonnées existantes
        $media = $this->getMediaById($mediaId);
        if (!$media) {
            return false;
        }

        $existingMeta = [];
        if (!empty($media['metadata'])) {
            $existingMeta = json_decode($media['metadata'], true) ?: [];
        }

        // Fusionner avec les nouvelles métadonnées
        $newMetadata = array_merge($existingMeta, $metadata);

        // Mettre à jour dans la base de données
        return $this->db->update(
            'climbing_media',
            ['metadata' => json_encode($newMetadata), 'updated_at' => date('Y-m-d H:i:s')],
            "id = ?",
            [$mediaId]
        ) > 0;
    }

    /**
     * Met à jour les informations d'un média
     *
     * @param int $mediaId ID du média
     * @param array $data Nouvelles données
     * @return bool
     */
    public function updateMedia(int $mediaId, array $data): bool
    {
        $allowedFields = [
            'title',
            'description',
            'is_public',
            'is_featured'
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        if (empty($updateData)) {
            return false;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('climbing_media', $updateData, "id = ?", [$mediaId]) > 0;
    }
}
