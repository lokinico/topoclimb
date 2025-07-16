<?php
// src/Models/Media.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Media extends Model
{
    /**
     * @var string
     */
    protected static string $table = 'climbing_media';

    /**
     * @var array
     */
    protected array $fillable = [
        'media_type',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'title',
        'description',
        'is_public',
        'is_featured',
        'view_count',
        'storage_type',
        'original_filename',
        'metadata',
        'created_by'
    ];

    /**
     * @var array
     */
    protected array $casts = [
        'file_size' => 'int',
        'is_public' => 'bool',
        'is_featured' => 'bool',
        'view_count' => 'int',
        'metadata' => 'json'
    ];

    /**
     * Relations avec les entités
     */
    public function relationships()
    {
        return $this->hasMany(MediaRelationship::class, 'media_id');
    }

    /**
     * Récupérer les médias pour une entité spécifique
     *
     * @param string $entityType
     * @param int $entityId
     * @param string|null $relationshipType
     * @return array
     */
    public static function getForEntity(string $entityType, int $entityId, ?string $relationshipType = null): array
    {
        $db = self::getDatabase();

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

        return $db->fetchAll($sql, $params);
    }

    /**
     * Supprimer un média avec ses fichiers et relations
     *
     * @param int $mediaId
     * @return bool
     */
    public static function deleteWithFiles(int $mediaId): bool
    {
        $db = self::getDatabase();

        try {
            // Récupérer les informations du média
            $media = $db->fetchOne("SELECT * FROM climbing_media WHERE id = ?", [$mediaId]);

            if (!$media) {
                return false;
            }

            $db->beginTransaction();

            // Supprimer les fichiers physiques
            $uploadsPath = BASE_PATH . '/public/uploads';

            // Fichier principal
            $mainFile = $uploadsPath . $media['file_path'];
            if (file_exists($mainFile)) {
                unlink($mainFile);
            }

            // Miniatures si elles existent
            if (!empty($media['metadata'])) {
                $metadata = json_decode($media['metadata'], true);
                if (isset($metadata['thumbnails'])) {
                    foreach ($metadata['thumbnails'] as $thumbPath) {
                        $fullThumbPath = $uploadsPath . $thumbPath;
                        if (file_exists($fullThumbPath)) {
                            unlink($fullThumbPath);
                        }
                    }
                }
            }

            // Supprimer les relations en base
            $db->delete('climbing_media_relationships', 'media_id = ?', [$mediaId]);
            $db->delete('climbing_media_annotations', 'media_id = ?', [$mediaId]);
            $db->delete('climbing_media_tags', 'media_id = ?', [$mediaId]);

            // Supprimer le média lui-même
            $result = $db->delete('climbing_media', 'id = ?', [$mediaId]);

            $db->commit();

            return $result > 0;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erreur lors de la suppression du média $mediaId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir l'URL complète du média
     *
     * @return string
     */
    public function getUrlAttribute(): string
    {
        if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
            return $this->file_path;
        }

        $filePath = $this->file_path;
        if ($filePath && $filePath[0] !== '/') {
            $filePath = '/' . $filePath;
        }

        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        return $baseUrl . $filePath;
    }

    /**
     * Obtenir la taille formatée du fichier
     *
     * @return string
     */
    public function getFormattedSizeAttribute(): string
    {
        $size = $this->file_size ?? 0;

        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 1) . ' KB';
        } elseif ($size < 1073741824) {
            return round($size / 1048576, 1) . ' MB';
        } else {
            return round($size / 1073741824, 1) . ' GB';
        }
    }

    /**
     * Vérifier si le média est une image
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return $this->media_type === 'image' ||
            (isset($this->mime_type) && str_starts_with($this->mime_type, 'image/'));
    }

    /**
     * Vérifier si le média est une vidéo
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return $this->media_type === 'video' ||
            (isset($this->mime_type) && str_starts_with($this->mime_type, 'video/'));
    }

    /**
     * Vérifier si le média est un PDF
     *
     * @return bool
     */
    public function isPdf(): bool
    {
        return $this->media_type === 'pdf' ||
            (isset($this->mime_type) && $this->mime_type === 'application/pdf');
    }

    /**
     * Obtenir les miniatures décodées
     *
     * @return array
     */
    public function getThumbnailsAttribute(): array
    {
        if (empty($this->metadata)) {
            return [];
        }

        $metadata = is_string($this->metadata) ? json_decode($this->metadata, true) : $this->metadata;
        return $metadata['thumbnails'] ?? [];
    }

    /**
     * Validation rules
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'media_type' => 'required|in:image,video,pdf,topo,other',
            'filename' => 'required|max:255',
            'file_path' => 'required|max:255',
            'mime_type' => 'max:100',
            'title' => 'max:100',
            'created_by' => 'required|numeric'
        ];
    }
}
