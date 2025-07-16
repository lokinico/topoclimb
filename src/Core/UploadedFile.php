<?php

namespace TopoclimbCH\Core;

class UploadedFile
{
    private string $name;
    private string $type;
    private string $tmpName;
    private int $error;
    private int $size;
    
    public function __construct(array $file)
    {
        $this->name = $file['name'] ?? '';
        $this->type = $file['type'] ?? '';
        $this->tmpName = $file['tmp_name'] ?? '';
        $this->error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $this->size = $file['size'] ?? 0;
    }
    
    /**
     * Crée une instance à partir d'un tableau de fichier
     */
    public static function createFromArray(array $file): self
    {
        return new self($file);
    }
    
    /**
     * Vérifie si le fichier a été uploadé avec succès
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
    
    /**
     * Récupère le code d'erreur
     */
    public function getError(): int
    {
        return $this->error;
    }
    
    /**
     * Récupère le message d'erreur
     */
    public function getErrorMessage(): string
    {
        switch ($this->error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Le fichier dépasse la taille maximale définie dans php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Le fichier dépasse la taille maximale définie dans le formulaire HTML';
            case UPLOAD_ERR_PARTIAL:
                return 'Le fichier n\'a été que partiellement uploadé';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier n\'a été uploadé';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Le dossier temporaire est manquant';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Échec de l\'écriture du fichier sur le disque';
            case UPLOAD_ERR_EXTENSION:
                return 'Une extension PHP a arrêté l\'upload du fichier';
            default:
                return 'Erreur inconnue lors de l\'upload';
        }
    }
    
    /**
     * Récupère le nom du fichier original
     */
    public function getClientFilename(): string
    {
        return $this->name;
    }
    
    /**
     * Récupère l'extension du fichier
     */
    public function getExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }
    
    /**
     * Récupère le type MIME du fichier
     */
    public function getClientMediaType(): string
    {
        return $this->type;
    }
    
    /**
     * Récupère la taille du fichier
     */
    public function getSize(): int
    {
        return $this->size;
    }
    
    /**
     * Déplace le fichier uploadé
     */
    public function moveTo(string $targetPath): bool
    {
        return move_uploaded_file($this->tmpName, $targetPath);
    }
    
    /**
     * Récupère le chemin temporaire du fichier
     */
    public function getStream()
    {
        return fopen($this->tmpName, 'r');
    }
}