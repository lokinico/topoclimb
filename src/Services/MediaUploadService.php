<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploadService
{
    private Database $db;
    private string $uploadDirectory;
    private array $allowedTypes;
    private int $maxFileSize;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->uploadDirectory = '/home/nibaechl/topoclimb/public/uploads/media';
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }
    
    /**
     * Upload et enregistrement d'un média pour une entité
     */
    public function uploadMedia(UploadedFile $file, string $entityType, int $entityId, ?string $title = null, ?int $userId = null): ?int
    {
        try {
            app_log("MediaUploadService::uploadMedia - Début upload");
            
            // Validation du fichier et récupération de la taille
            app_log("MediaUploadService::uploadMedia - Validation fichier");
            $fileSize = $this->validateFile($file);
            
            // Collecter les informations du fichier AVANT de le déplacer
            app_log("MediaUploadService::uploadMedia - Collecte informations fichier");
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $fileSize; // Déjà calculé dans validateFile
            
            // Génération nom de fichier unique
            app_log("MediaUploadService::uploadMedia - Génération nom fichier");
            $fileName = $this->generateFileName($file);
            $filePath = '/uploads/media/' . $fileName;
            $fullPath = $this->uploadDirectory . '/' . $fileName;
            
            app_log("MediaUploadService::uploadMedia - fileName: $fileName, fullPath: $fullPath");
            
            // Déplacement du fichier (extraire nom fichier final et répertoire complet)
            $finalFileName = basename($fileName); // ex: 68a8...png
            $targetDirectory = $this->uploadDirectory . '/' . dirname($fileName); // ex: /...media/2025/08/22
            
            app_log("MediaUploadService::uploadMedia - Déplacement vers: $targetDirectory/$finalFileName");
            $file->move($targetDirectory, $finalFileName);
            
            // Enregistrement en base de données (avec les données collectées avant move)
            app_log("MediaUploadService::uploadMedia - Enregistrement base données");
            $mediaId = $this->saveMediaRecord($entityType, $entityId, $filePath, $originalName, $mimeType, $fileSize, $title, $userId);
            
            return $mediaId;
            
        } catch (\Exception $e) {
            app_log("MediaUploadService: Erreur upload - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validation d'un fichier uploadé
     * @return int La taille du fichier en bytes
     */
    private function validateFile(UploadedFile $file): int
    {
        app_log("MediaUploadService::validateFile - Début validation");
        
        // Vérifier que le fichier a été uploadé correctement
        app_log("MediaUploadService::validateFile - Vérification isValid()");
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou erreur d\'upload');
        }
        
        // Vérifier le type MIME
        app_log("MediaUploadService::validateFile - Vérification MIME type");
        $mimeType = $file->getMimeType();
        app_log("MediaUploadService::validateFile - MIME type détecté: " . $mimeType);
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé. Types acceptés: JPG, PNG, GIF, WebP');
        }
        
        // Vérifier la taille (utiliser $_FILES car getClientSize() peut être problématique)
        app_log("MediaUploadService::validateFile - Vérification taille");
        $fileName = $file->getClientOriginalName();
        $fileSize = null;
        
        // Essayer d'obtenir la taille via $_FILES (plus fiable)
        if (isset($_FILES) && is_array($_FILES)) {
            foreach ($_FILES as $fieldName => $fileInfo) {
                if ($fileInfo['name'] === $fileName) {
                    $fileSize = $fileInfo['size'];
                    break;
                }
            }
        }
        
        // Fallback: essayer getClientSize()
        if ($fileSize === null) {
            try {
                $fileSize = $file->getClientSize();
            } catch (\Exception $e) {
                app_log("MediaUploadService::validateFile - Erreur getClientSize: " . $e->getMessage());
                $fileSize = 0; // Si on ne peut pas obtenir la taille, on laisse passer
            }
        }
        
        app_log("MediaUploadService::validateFile - Taille fichier: " . $fileSize . " bytes");
        if ($fileSize > $this->maxFileSize) {
            throw new \InvalidArgumentException('Fichier trop volumineux. Taille max: 5MB');
        }
        
        // Vérification sécuritaire supplémentaire via getimagesize
        app_log("MediaUploadService::validateFile - Vérification getimagesize");
        $tempPath = $file->getPathname();
        app_log("MediaUploadService::validateFile - Chemin temp: " . $tempPath);
        $imageInfo = @getimagesize($tempPath);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Fichier corrompu ou non valide');
        }
        app_log("MediaUploadService::validateFile - Validation terminée avec succès");
        
        return $fileSize;
    }
    
    /**
     * Génération d'un nom de fichier unique
     */
    private function generateFileName(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?: 'bin';
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $baseName);
        $safeName = substr($safeName, 0, 50); // Limiter la longueur
        
        $datePath = date('Y/m/d');
        $this->ensureUploadDirectoryExists($datePath);
        
        return $datePath . '/' . uniqid() . '_' . $safeName . '.' . $extension;
    }
    
    /**
     * Enregistrement du média en base de données
     */
    private function saveMediaRecord(string $entityType, int $entityId, string $filePath, string $originalName, string $mimeType, int $fileSize, ?string $title, ?int $userId): int
    {
        $data = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $title ?: $originalName,
            'description' => null,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'file_type' => 'image',
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'display_order' => 1,
            'is_primary' => 1,
            'alt_text' => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
            'updated_by' => $userId
        ];
        
        return $this->db->insert('climbing_media', $data);
    }
    
    /**
     * Récupération des médias pour une entité
     */
    public function getMediaForEntity(string $entityType, int $entityId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM climbing_media 
             WHERE entity_type = ? AND entity_id = ? AND active = 1
             ORDER BY display_order ASC, created_at ASC",
            [$entityType, $entityId]
        );
    }
    
    /**
     * Suppression d'un média (soft delete)
     */
    public function deleteMedia(int $mediaId, ?int $userId = null): bool
    {
        return $this->db->update(
            'climbing_media',
            [
                'active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId
            ],
            ['id' => $mediaId]
        );
    }
    
    /**
     * Création des répertoires de date si nécessaire
     */
    private function ensureUploadDirectoryExists(string $datePath): void
    {
        $fullDatePath = $this->uploadDirectory . '/' . $datePath;
        if (!is_dir($fullDatePath)) {
            mkdir($fullDatePath, 0755, true);
        }
    }
}