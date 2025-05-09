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
}