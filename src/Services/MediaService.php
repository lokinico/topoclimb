<?php
// src/Services/MediaService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

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

        // Créer le dossier uploads s'il n'existe pas
        if (!is_dir($this->uploadsPath)) {
            mkdir($this->uploadsPath, 0755, true);
        }
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
     * Upload a new media file with new entity-based structure
     *
     * @param array $file File from $_FILES
     * @param array $data Additional data
     * @param int $userId ID of the user uploading the file
     * @return int|null ID of the new media or null on failure
     * @throws \Exception Si une erreur grave se produit
     */
    public function uploadMedia(array $file, array $data, int $userId): ?int
    {
        // Vérification approfondie du fichier
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("MediaService::uploadMedia - Fichier invalide ou manquant");
            return null;
        }

        // Vérification des erreurs de téléchargement
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error']);
            error_log("MediaService::uploadMedia - Erreur de téléchargement: " . $errorMessage);
            throw new \Exception($errorMessage);
        }

        // Déterminer si on utilise la nouvelle structure ou l'ancienne
        $useNewStructure = isset($data['entity_type']) && isset($data['entity_id']);

        if ($useNewStructure) {
            return $this->uploadMediaNewStructure($file, $data, $userId);
        } else {
            return $this->uploadMediaOldStructure($file, $data, $userId);
        }
    }

    /**
     * Upload avec la nouvelle structure par entité
     */
    private function uploadMediaNewStructure(array $file, array $data, int $userId): ?int
    {
        // Générer les chemins avec la nouvelle structure
        $paths = $this->generateEntityBasedPaths($file['name'], $data);

        // Créer le répertoire de destination
        if (!is_dir($paths['directory'])) {
            if (!mkdir($paths['directory'], 0755, true)) {
                throw new \Exception("Impossible de créer le répertoire: " . $paths['directory']);
            }
        }

        // Vérifier les permissions
        if (!is_writable($paths['directory'])) {
            throw new \Exception("Le répertoire n'est pas accessible en écriture: " . $paths['directory']);
        }

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $paths['full_path'])) {
            throw new \Exception("Impossible de déplacer le fichier téléchargé");
        }

        // Traitement de l'image
        $mediaType = $this->determineMediaType($file['type'] ?? '');
        $metadata = $this->processMedia($paths['full_path'], $paths['directory'], $paths['filename'], $mediaType);

        // Enregistrer en base de données
        $mediaData = [
            'media_type' => $mediaType,
            'filename' => $paths['filename'],
            'file_path' => $paths['relative_path'],
            'file_size' => $file['size'] ?? 0,
            'mime_type' => $file['type'] ?? $this->detectMimeType($paths['full_path']),
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

        if (!$mediaId) {
            // Nettoyer le fichier en cas d'échec
            $this->cleanupFiles($paths['full_path'], $metadata);
            throw new \Exception("Échec de l'enregistrement du média en base de données");
        }

        // Créer la relation avec l'entité
        $this->associateMediaWithEntity(
            $mediaId,
            $data['entity_type'],
            (int) $data['entity_id'],
            $data['relationship_type'] ?? 'gallery',
            $data['sort_order'] ?? 0
        );

        return $mediaId;
    }

    /**
     * Upload avec l'ancienne structure par date (pour compatibilité)
     */
    private function uploadMediaOldStructure(array $file, array $data, int $userId): ?int
    {
        // Générer les chemins avec l'ancienne structure
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('media_') . '.' . $extension;
        $year = date('Y');
        $month = date('m');
        $relativePath = "/media/$year/$month";
        $filePath = $this->uploadsPath . $relativePath;

        // Créer le répertoire
        if (!is_dir($filePath) && !mkdir($filePath, 0755, true)) {
            throw new \Exception("Impossible de créer le répertoire: " . $filePath);
        }

        if (!is_writable($filePath)) {
            throw new \Exception("Le répertoire n'est pas accessible en écriture: " . $filePath);
        }

        $fullPath = "$filePath/$filename";

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \Exception("Impossible de déplacer le fichier téléchargé");
        }

        // Traitement du média
        $mediaType = $this->determineMediaType($file['type'] ?? '');
        $metadata = $this->processMedia($fullPath, $filePath, $filename, $mediaType);

        // Enregistrer en base
        $mediaData = [
            'media_type' => $mediaType,
            'filename' => $filename,
            'file_path' => "$relativePath/$filename",
            'file_size' => $file['size'] ?? 0,
            'mime_type' => $file['type'] ?? $this->detectMimeType($fullPath),
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

        if (!$mediaId) {
            $this->cleanupFiles($fullPath, $metadata);
            throw new \Exception("Échec de l'enregistrement du média en base de données");
        }

        // Créer la relation si les données sont fournies
        if (isset($data['entity_type']) && isset($data['entity_id'])) {
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
     * Génère les chemins pour la nouvelle structure par entité
     */
    private function generateEntityBasedPaths(string $originalFilename, array $data): array
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = $this->generateUniqueFilename($originalFilename);

        // Construire le chemin selon l'entité
        $entityPath = $this->getEntityPath($data['entity_type'], $data['entity_id']);
        $relationshipPath = $data['relationship_type'] ?? 'gallery';

        $relativePath = "/{$entityPath}/{$relationshipPath}";
        $fullDirectory = $this->uploadsPath . $relativePath;

        return [
            'filename' => $filename,
            'relative_path' => "{$relativePath}/{$filename}",
            'full_path' => "{$fullDirectory}/{$filename}",
            'directory' => $fullDirectory,
            'entity_path' => $entityPath
        ];
    }

    /**
     * Obtient le chemin de l'entité
     */
    private function getEntityPath(string $entityType, int $entityId): string
    {
        $pluralTypes = [
            'sector' => 'sectors',
            'route' => 'routes',
            'event' => 'events',
            'user' => 'users'
        ];

        $plural = $pluralTypes[$entityType] ?? $entityType . 's';

        if ($entityType === 'user') {
            return "{$plural}/profiles";
        }

        return "{$plural}/{$entityId}";
    }

    /**
     * Génère un nom de fichier unique et sécurisé
     */
    private function generateUniqueFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Nettoyer le nom de base
        $cleanBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $cleanBasename = trim($cleanBasename, '_-');
        $cleanBasename = substr($cleanBasename, 0, 50);

        if (empty($cleanBasename)) {
            $cleanBasename = 'media';
        }

        return $cleanBasename . '_' . uniqid() . '.' . $extension;
    }

    /**
     * Traite un média (images, vidéos, etc.)
     */
    private function processMedia(string $fullPath, string $directory, string $filename, string $mediaType): array
    {
        $metadata = [];

        if ($mediaType === 'image') {
            $metadata = $this->processImage($fullPath, $directory, $filename);
        } elseif ($mediaType === 'video') {
            $metadata = $this->processVideo($fullPath);
        } elseif ($mediaType === 'pdf') {
            $metadata = $this->processPdf($fullPath);
        }

        return $metadata;
    }

    /**
     * Traite une image (redimensionnement, miniatures)
     */
    private function processImage(string $fullPath, string $directory, string $filename): array
    {
        $metadata = [];

        try {
            // Utiliser GD si Intervention Image n'est pas disponible
            if (class_exists('\Intervention\Image\ImageManager')) {
                $metadata = $this->processImageWithIntervention($fullPath, $directory, $filename);
            } else {
                $metadata = $this->processImageWithGD($fullPath, $directory, $filename);
            }
        } catch (\Exception $e) {
            error_log("Erreur traitement image: " . $e->getMessage());
            // Récupérer au moins les dimensions de base
            $imageInfo = getimagesize($fullPath);
            if ($imageInfo) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
            }
        }

        return $metadata;
    }

    /**
     * Traite une image avec Intervention Image
     */
    private function processImageWithIntervention(string $fullPath, string $directory, string $filename): array
    {
        $imageManager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $image = $imageManager->make($fullPath);

        $metadata = [
            'width' => $image->width(),
            'height' => $image->height()
        ];

        // Redimensionner si trop grande
        if ($image->width() > 2000 || $image->height() > 2000) {
            $image->resize(2000, 2000, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $image->save($fullPath, 85);
        }

        // Créer le dossier thumbnails
        $thumbDirectory = dirname($directory) . '/thumbnails';
        if (!is_dir($thumbDirectory)) {
            mkdir($thumbDirectory, 0755, true);
        }

        // Créer différentes tailles de miniatures
        $thumbnails = [];
        $sizes = [
            'thumb' => [300, 300],
            'medium' => [800, 600],
            'small' => [150, 150]
        ];

        foreach ($sizes as $sizeName => $dimensions) {
            $thumbFilename = $sizeName . '_' . $filename;
            $thumbPath = $thumbDirectory . '/' . $thumbFilename;

            $thumbImage = clone $image;
            $thumbImage->resize($dimensions[0], $dimensions[1], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->save($thumbPath, 75);

            $relativePath = str_replace($this->uploadsPath, '', $thumbPath);
            $thumbnails[$sizeName] = $relativePath;
        }

        $metadata['thumbnails'] = $thumbnails;

        return $metadata;
    }

    /**
     * Traite une image avec GD (fallback)
     */
    private function processImageWithGD(string $fullPath, string $directory, string $filename): array
    {
        $imageInfo = getimagesize($fullPath);
        if (!$imageInfo) {
            return [];
        }

        $metadata = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];

        // Créer une miniature simple avec GD
        $thumbDirectory = dirname($directory) . '/thumbnails';
        if (!is_dir($thumbDirectory)) {
            mkdir($thumbDirectory, 0755, true);
        }

        $thumbPath = $thumbDirectory . '/thumb_' . $filename;
        if ($this->createThumbnailWithGD($fullPath, $thumbPath, 300, 300)) {
            $relativePath = str_replace($this->uploadsPath, '', $thumbPath);
            $metadata['thumbnails'] = ['thumb' => $relativePath];
        }

        return $metadata;
    }

    /**
     * Crée une miniature avec GD
     */
    private function createThumbnailWithGD(string $source, string $destination, int $maxWidth, int $maxHeight): bool
    {
        $imageInfo = getimagesize($source);
        if (!$imageInfo) return false;

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];

        // Calculer les nouvelles dimensions
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $newWidth = (int)($sourceWidth * $ratio);
        $newHeight = (int)($sourceHeight * $ratio);

        // Créer les ressources d'image
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            default:
                return false;
        }

        if (!$sourceImage) return false;

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Conserver la transparence pour PNG et GIF
        if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Redimensionner
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        // Sauvegarder
        $success = false;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($thumbnail, $destination, 75);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($thumbnail, $destination, 7);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($thumbnail, $destination);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $success;
    }

    /**
     * Traite une vidéo
     */
    private function processVideo(string $fullPath): array
    {
        $metadata = [];

        // Informations de base sur le fichier
        $metadata['file_size'] = filesize($fullPath);

        // Utiliser FFmpeg si disponible pour extraire des métadonnées détaillées
        if ($this->isFFmpegAvailable()) {
            try {
                $ffmpegMetadata = $this->extractFFmpegMetadata($fullPath);
                $metadata = array_merge($metadata, $ffmpegMetadata);
            } catch (\Exception $e) {
                // Log l'erreur mais continue avec les métadonnées de base
                error_log("FFmpeg metadata extraction failed: " . $e->getMessage());
            }
        }

        return $metadata;
    }

    /**
     * Traite un PDF
     */
    private function processPdf(string $fullPath): array
    {
        $metadata = [];

        $metadata['file_size'] = filesize($fullPath);

        // Extraire les métadonnées PDF
        try {
            $pdfMetadata = $this->extractPdfMetadata($fullPath);
            $metadata = array_merge($metadata, $pdfMetadata);
        } catch (\Exception $e) {
            // Log l'erreur mais continue avec les métadonnées de base
            error_log("PDF metadata extraction failed: " . $e->getMessage());
        }

        return $metadata;
    }

    /**
     * Détermine le type de média
     */
    private function determineMediaType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mimeType, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mimeType, 'application/pdf') === 0) {
            return 'pdf';
        }

        return 'other';
    }

    /**
     * Nettoie les fichiers en cas d'erreur
     */
    private function cleanupFiles(string $mainFile, array $metadata): void
    {
        if (file_exists($mainFile)) {
            unlink($mainFile);
        }

        if (isset($metadata['thumbnails'])) {
            foreach ($metadata['thumbnails'] as $thumbPath) {
                $fullThumbPath = $this->uploadsPath . $thumbPath;
                if (file_exists($fullThumbPath)) {
                    unlink($fullThumbPath);
                }
            }
        }
    }

    /**
     * Obtient le message d'erreur d'upload
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la taille maximale autorisée par PHP",
            UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la taille maximale autorisée par le formulaire",
            UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé",
            UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été téléchargé",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
            UPLOAD_ERR_CANT_WRITE => "Échec d'écriture du fichier sur le disque",
            UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté le téléchargement du fichier"
        ];

        return $errorMessages[$errorCode] ?? "Erreur inconnue lors du téléchargement";
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
        // Vérifier si l'association existe déjà
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

        // Si c'est une relation 'main', mettre à jour les relations main existantes pour cette entité
        if ($relationshipType === 'main') {
            $this->db->update(
                'climbing_media_relationships',
                ['relationship_type' => 'gallery'],
                "entity_type = ? AND entity_id = ? AND relationship_type = 'main'",
                [$entityType, $entityId]
            );
        }

        // Créer la nouvelle association
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
        // Récupérer les données du média
        $media = $this->getMediaById($mediaId);

        if (!$media) {
            return false;
        }

        // Supprimer le fichier principal
        $filePath = $this->uploadsPath . $media['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer les miniatures
        $metadata = json_decode($media['metadata'] ?? '{}', true);
        if (isset($metadata['thumbnails'])) {
            foreach ($metadata['thumbnails'] as $thumbPath) {
                $fullThumbPath = $this->uploadsPath . $thumbPath;
                if (file_exists($fullThumbPath)) {
                    unlink($fullThumbPath);
                }
            }
        }

        // Supprimer les relations
        $this->db->delete('climbing_media_relationships', "media_id = ?", [$mediaId]);

        // Supprimer l'enregistrement du média
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

        // Améliorer les données des médias
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

        // Construire l'URL complète - utiliser une URL de base si définie
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        return $baseUrl . $filePath;
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
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
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
        $countSql = "SELECT COUNT(*) as count FROM climbing_media WHERE $whereClause";
        $countResult = $this->db->fetchOne($countSql, array_slice($params, 0, -2));
        $total = (int)($countResult['count'] ?? 0);

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
     * Vérifie si un fichier est une image
     */
    private function isImage(string $filePath): bool
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = $this->detectMimeType($filePath);
        return in_array($mimeType, $imageTypes);
    }

    /**
     * Obtient les statistiques d'utilisation des médias
     */
    public function getMediaStats(): array
    {
        $stats = [];

        // Nombre total de médias
        $totalResult = $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_media");
        $stats['total_media'] = (int)($totalResult['count'] ?? 0);

        // Répartition par type
        $typeStats = $this->db->fetchAll("
            SELECT media_type, COUNT(*) as count 
            FROM climbing_media 
            GROUP BY media_type 
            ORDER BY count DESC
        ");
        $stats['by_type'] = $typeStats;

        // Taille totale des fichiers
        $sizeResult = $this->db->fetchOne("SELECT SUM(file_size) as total_size FROM climbing_media");
        $stats['total_size'] = (int)($sizeResult['total_size'] ?? 0);

        return $stats;
    }

    /**
     * Vérifie si FFmpeg est disponible sur le système
     */
    private function isFFmpegAvailable(): bool
    {
        // Cache le résultat pour éviter les vérifications répétées
        static $isAvailable = null;
        
        if ($isAvailable === null) {
            $output = [];
            $returnCode = 0;
            exec('ffmpeg -version 2>/dev/null', $output, $returnCode);
            $isAvailable = ($returnCode === 0);
        }
        
        return $isAvailable;
    }

    /**
     * Extrait les métadonnées vidéo avec FFmpeg
     */
    private function extractFFmpegMetadata(string $filePath): array
    {
        $metadata = [];
        
        // Échapper le chemin du fichier pour la sécurité
        $escapedPath = escapeshellarg($filePath);
        
        // Commande FFprobe pour extraire les métadonnées JSON
        $command = "ffprobe -v quiet -print_format json -show_format -show_streams $escapedPath 2>/dev/null";
        
        $output = shell_exec($command);
        
        if (!$output) {
            throw new \Exception("FFprobe failed to extract metadata");
        }
        
        $ffprobeData = json_decode($output, true);
        
        if (!$ffprobeData) {
            throw new \Exception("Invalid JSON response from FFprobe");
        }
        
        // Extraire les informations du format
        if (isset($ffprobeData['format'])) {
            $format = $ffprobeData['format'];
            
            if (isset($format['duration'])) {
                $metadata['duration'] = (float)$format['duration'];
                $metadata['duration_formatted'] = $this->formatDuration($metadata['duration']);
            }
            
            if (isset($format['bit_rate'])) {
                $metadata['bitrate'] = (int)$format['bit_rate'];
            }
            
            if (isset($format['tags'])) {
                $tags = $format['tags'];
                $metadata['title'] = $tags['title'] ?? null;
                $metadata['artist'] = $tags['artist'] ?? null;
                $metadata['album'] = $tags['album'] ?? null;
                $metadata['creation_time'] = $tags['creation_time'] ?? null;
                $metadata['comment'] = $tags['comment'] ?? null;
            }
        }
        
        // Extraire les informations des streams vidéo
        if (isset($ffprobeData['streams'])) {
            foreach ($ffprobeData['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    $metadata['width'] = (int)($stream['width'] ?? 0);
                    $metadata['height'] = (int)($stream['height'] ?? 0);
                    $metadata['resolution'] = $metadata['width'] . 'x' . $metadata['height'];
                    $metadata['codec'] = $stream['codec_name'] ?? null;
                    $metadata['pixel_format'] = $stream['pix_fmt'] ?? null;
                    
                    // Calculer le ratio d'aspect
                    if ($metadata['width'] > 0 && $metadata['height'] > 0) {
                        $gcd = $this->gcd($metadata['width'], $metadata['height']);
                        $aspectW = $metadata['width'] / $gcd;
                        $aspectH = $metadata['height'] / $gcd;
                        $metadata['aspect_ratio'] = $aspectW . ':' . $aspectH;
                    }
                    
                    // Frame rate
                    if (isset($stream['r_frame_rate'])) {
                        $fps = $stream['r_frame_rate'];
                        if (strpos($fps, '/') !== false) {
                            list($num, $den) = explode('/', $fps);
                            if ((int)$den > 0) {
                                $metadata['fps'] = round((int)$num / (int)$den, 2);
                            }
                        }
                    }
                    
                    break; // Prendre seulement le premier stream vidéo
                }
            }
            
            // Extraire les informations des streams audio
            foreach ($ffprobeData['streams'] as $stream) {
                if ($stream['codec_type'] === 'audio') {
                    $metadata['audio_codec'] = $stream['codec_name'] ?? null;
                    $metadata['audio_channels'] = (int)($stream['channels'] ?? 0);
                    $metadata['audio_sample_rate'] = (int)($stream['sample_rate'] ?? 0);
                    break; // Prendre seulement le premier stream audio
                }
            }
        }
        
        return $metadata;
    }

    /**
     * Formate une durée en secondes vers un format lisible
     */
    private function formatDuration(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%02d:%02d', $minutes, $secs);
        }
    }

    /**
     * Calcule le plus grand commun diviseur (pour le ratio d'aspect)
     */
    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        return $a;
    }

    /**
     * Extrait les métadonnées d'un fichier PDF
     */
    private function extractPdfMetadata(string $filePath): array
    {
        $metadata = [];
        
        // Vérifier si pdfinfo est disponible (fait partie de poppler-utils)
        if ($this->isPdfinfoAvailable()) {
            $metadata = $this->extractPdfMetadataWithPdfinfo($filePath);
        } else {
            // Fallback: méthode basique de lecture des métadonnées PDF
            $metadata = $this->extractPdfMetadataBasic($filePath);
        }
        
        return $metadata;
    }

    /**
     * Vérifie si pdfinfo est disponible
     */
    private function isPdfinfoAvailable(): bool
    {
        static $isAvailable = null;
        
        if ($isAvailable === null) {
            $output = [];
            $returnCode = 0;
            exec('pdfinfo -v 2>/dev/null', $output, $returnCode);
            $isAvailable = ($returnCode === 0);
        }
        
        return $isAvailable;
    }

    /**
     * Extrait les métadonnées PDF avec pdfinfo
     */
    private function extractPdfMetadataWithPdfinfo(string $filePath): array
    {
        $metadata = [];
        $escapedPath = escapeshellarg($filePath);
        
        // Exécuter pdfinfo
        $output = shell_exec("pdfinfo $escapedPath 2>/dev/null");
        
        if (!$output) {
            throw new \Exception("pdfinfo failed to extract metadata");
        }
        
        // Parser la sortie de pdfinfo
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                switch ($key) {
                    case 'Pages':
                        $metadata['pages'] = (int)$value;
                        break;
                    case 'Title':
                        $metadata['title'] = $value;
                        break;
                    case 'Author':
                        $metadata['author'] = $value;
                        break;
                    case 'Subject':
                        $metadata['subject'] = $value;
                        break;
                    case 'Creator':
                        $metadata['creator'] = $value;
                        break;
                    case 'Producer':
                        $metadata['producer'] = $value;
                        break;
                    case 'CreationDate':
                        $metadata['creation_date'] = $value;
                        break;
                    case 'ModDate':
                        $metadata['modification_date'] = $value;
                        break;
                    case 'Page size':
                        $metadata['page_size'] = $value;
                        break;
                    case 'Encrypted':
                        $metadata['encrypted'] = ($value === 'yes');
                        break;
                    case 'PDF version':
                        $metadata['pdf_version'] = $value;
                        break;
                }
            }
        }
        
        return $metadata;
    }

    /**
     * Méthode basique pour extraire les métadonnées PDF
     * Lit directement le fichier PDF pour extraire les informations de base
     */
    private function extractPdfMetadataBasic(string $filePath): array
    {
        $metadata = [];
        
        // Lire les premiers 2048 octets du fichier pour rechercher les métadonnées
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new \Exception("Cannot open PDF file");
        }
        
        $content = fread($handle, 2048);
        fclose($handle);
        
        // Rechercher la version PDF
        if (preg_match('/%PDF-(\d+\.\d+)/', $content, $matches)) {
            $metadata['pdf_version'] = $matches[1];
        }
        
        // Pour le nombre de pages, nous devons lire plus du fichier
        // Cette méthode est basique et peut ne pas être 100% fiable
        $metadata['pages'] = $this->countPdfPagesBasic($filePath);
        
        // Essayer d'extraire des métadonnées de base avec une approche regex
        $fullContent = file_get_contents($filePath);
        
        // Rechercher les métadonnées dans le dictionnaire Info
        if (preg_match('/\/Info\s*<<([^>]*)>>/s', $fullContent, $matches)) {
            $infoContent = $matches[1];
            
            // Extraire le titre
            if (preg_match('/\/Title\s*\(([^)]*)\)/', $infoContent, $titleMatch)) {
                $metadata['title'] = $titleMatch[1];
            }
            
            // Extraire l'auteur
            if (preg_match('/\/Author\s*\(([^)]*)\)/', $infoContent, $authorMatch)) {
                $metadata['author'] = $authorMatch[1];
            }
            
            // Extraire le créateur
            if (preg_match('/\/Creator\s*\(([^)]*)\)/', $infoContent, $creatorMatch)) {
                $metadata['creator'] = $creatorMatch[1];
            }
        }
        
        return $metadata;
    }

    /**
     * Compte le nombre de pages de façon basique
     */
    private function countPdfPagesBasic(string $filePath): int
    {
        $content = file_get_contents($filePath);
        
        // Méthode 1: Compter les objets Page
        $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $content);
        
        if ($pageCount > 0) {
            return $pageCount;
        }
        
        // Méthode 2: Rechercher /Count dans le catalogue de pages
        if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
            return (int)$matches[1];
        }
        
        // Méthode 3: Compter les références de pages
        $pageCount = preg_match_all('/\/Page\s/', $content);
        
        return max(1, $pageCount); // Au moins 1 page
    }
}
