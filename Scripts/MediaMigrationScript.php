<?php

namespace TopoclimbCH\Scripts;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Application;
use TopoclimbCH\Services\MediaService;
use Exception;

/**
 * Script de migration des médias
 * 
 * Ce script migre les fichiers médias du répertoire image vers la nouvelle structure
 * et met à jour les références dans la base de données.
 * Compatible avec le MediaService existant.
 */
class MediaMigrationScript
{
    /** @var Database Instance de la base de données */
    private Database $db;

    /** @var MediaService Service de gestion des médias */
    private MediaService $mediaService;

    /** @var string Répertoire source des médias */
    private string $sourceDir;

    /** @var string Répertoire cible pour les médias organisés */
    private string $targetBaseDir;

    /** @var array Statistiques de migration */
    private array $stats = [
        'processed' => 0,
        'moved' => 0,
        'missing' => 0,
        'errors' => 0,
    ];

    /**
     * Constructeur
     */
    public function __construct()
    {
        // Initialiser la connexion à la base de données
        $this->db = Application::getInstance()->getDatabase();
        $this->mediaService = new MediaService($this->db);

        // Définir les chemins source et cible (à ajuster selon votre environnement)
        $this->sourceDir = dirname(__DIR__, 2) . '/public/image/';
        $this->targetBaseDir = dirname(__DIR__, 2) . '/public/uploads/media/';

        // Créer la structure de répertoires si nécessaire
        $this->ensureDirectoriesExist();
    }

    /**
     * S'assure que les répertoires nécessaires existent
     */
    private function ensureDirectoriesExist(): void
    {
        // Créer le répertoire de base
        if (!is_dir($this->targetBaseDir)) {
            mkdir($this->targetBaseDir, 0755, true);
        }

        // Créer les répertoires pour l'année et le mois en cours
        $year = date('Y');
        $month = date('m');
        $yearMonthDir = $this->targetBaseDir . $year . '/' . $month;

        if (!is_dir($yearMonthDir)) {
            mkdir($yearMonthDir, 0755, true);
        }
    }

    /**
     * Exécute la migration des médias
     */
    public function run(): void
    {
        echo "Démarrage de la migration des médias depuis '/image/' vers '/uploads/media/'...\n";

        // Récupérer tous les médias de la base de données qui n'ont pas encore été migrés
        $query = "SELECT * FROM climbing_media WHERE file_path LIKE '%image/%' OR file_path IS NULL OR file_path = ''";
        $medias = $this->db->query($query)->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($medias)) {
            echo "Aucun média à migrer trouvé dans la base de données.\n";
            return;
        }

        echo "Trouvé " . count($medias) . " médias à migrer.\n";

        // Traiter chaque média
        foreach ($medias as $media) {
            $this->migrateMedia($media);
        }

        // Mettre à jour les références dans les tables existantes
        $this->updateReferencesInDb();

        // Afficher les statistiques
        echo "\n=== Migration terminée ===\n";
        echo "Traités: {$this->stats['processed']}\n";
        echo "Déplacés: {$this->stats['moved']}\n";
        echo "Manquants: {$this->stats['missing']}\n";
        echo "Erreurs: {$this->stats['errors']}\n";
    }

    /**
     * Migre un média spécifique
     * 
     * @param array $media Données du média à migrer
     * @return bool Succès ou échec
     */
    public function migrateMedia(array $media): bool
    {
        $mediaId = $media['id'];

        echo "Traitement du média #{$mediaId}: {$media['filename']}... ";

        try {
            // Déterminer le chemin source
            $sourcePath = '';
            if (!empty($media['file_path']) && strpos($media['file_path'], 'image/') !== false) {
                // Le chemin est déjà dans la base de données
                $sourcePath = dirname(__DIR__, 2) . '/public/' . $media['file_path'];
            } elseif (!empty($media['filename'])) {
                // Essayer de trouver le fichier dans le répertoire image
                $sourcePath = $this->sourceDir . $media['filename'];
            }

            // Vérifier si le fichier source existe
            if (!file_exists($sourcePath)) {
                echo "ERREUR: Fichier source introuvable ({$sourcePath}).\n";
                $this->stats['missing']++;
                return false;
            }

            // Générer le nouveau chemin selon la structure de MediaService
            $year = date('Y');
            $month = date('m');
            $newFilename = 'migrated_' . $mediaId . '_' . $media['filename'];
            $relativePath = "/media/$year/$month";
            $targetDir = $this->targetBaseDir . $year . '/' . $month;

            // Créer le répertoire cible si nécessaire
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $targetPath = $targetDir . '/' . $newFilename;

            // Copier le fichier
            if (copy($sourcePath, $targetPath)) {
                // Préparer les métadonnées
                $metadata = [];

                // Si c'est une image, créer une miniature
                if ($media['media_type'] === 'image' && extension_loaded('gd')) {
                    $metadata = $this->createThumbnail($targetPath, $targetDir, $newFilename, $relativePath);
                }

                // Mettre à jour la base de données
                $this->updateMediaRecord($mediaId, $newFilename, "$relativePath/$newFilename", $metadata);

                $this->stats['moved']++;
                echo "OK -> $relativePath/$newFilename\n";
                return true;
            } else {
                throw new Exception("Impossible de copier le fichier de {$sourcePath} vers {$targetPath}");
            }
        } catch (Exception $e) {
            echo "ERREUR: " . $e->getMessage() . "\n";
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Crée une miniature pour une image
     *
     * @param string $imagePath Chemin complet de l'image
     * @param string $targetDir Répertoire cible
     * @param string $filename Nom du fichier
     * @param string $relativePath Chemin relatif
     * @return array Métadonnées de l'image
     */
    private function createThumbnail(string $imagePath, string $targetDir, string $filename, string $relativePath): array
    {
        $metadata = [];

        try {
            // Obtenir les dimensions de l'image
            list($width, $height) = getimagesize($imagePath);
            $metadata['width'] = $width;
            $metadata['height'] = $height;

            // Créer la miniature
            $thumbFilename = 'thumb_' . $filename;
            $thumbPath = $targetDir . '/' . $thumbFilename;

            // Utiliser GD pour créer la miniature
            $imageType = exif_imagetype($imagePath);
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($imagePath);
                    break;
                default:
                    // Type d'image non supporté
                    return $metadata;
            }

            // Dimensions de la miniature
            $thumbWidth = 300;
            $thumbHeight = round($height * ($thumbWidth / $width));

            // Créer l'image de destination
            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Préserver la transparence pour PNG et GIF
            if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }

            // Redimensionner
            imagecopyresampled(
                $thumb,
                $source,
                0,
                0,
                0,
                0,
                $thumbWidth,
                $thumbHeight,
                $width,
                $height
            );

            // Enregistrer la miniature
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumb, $thumbPath, 75);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumb, $thumbPath, 7);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($thumb, $thumbPath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($thumb, $thumbPath, 75);
                    break;
            }

            // Libérer la mémoire
            imagedestroy($source);
            imagedestroy($thumb);

            // Ajouter le chemin de la miniature aux métadonnées
            $metadata['thumbnails'] = [
                'thumb' => "$relativePath/$thumbFilename"
            ];
        } catch (Exception $e) {
            echo "Avertissement lors de la création de la miniature: " . $e->getMessage() . "\n";
        }

        return $metadata;
    }

    /**
     * Met à jour l'enregistrement du média dans la base de données
     *
     * @param int $mediaId ID du média
     * @param string $filename Nouveau nom de fichier
     * @param string $filePath Nouveau chemin relatif
     * @param array $metadata Métadonnées
     * @return bool Succès ou échec
     */
    private function updateMediaRecord(int $mediaId, string $filename, string $filePath, array $metadata = []): bool
    {
        // Mettre à jour les champs file_path et filename
        $data = [
            'filename' => $filename,
            'file_path' => $filePath,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Si des métadonnées existent, les ajouter
        if (!empty($metadata)) {
            // Récupérer les métadonnées existantes
            $existing = $this->db->fetchOne(
                "SELECT metadata FROM climbing_media WHERE id = ?",
                [$mediaId]
            );

            $existingMeta = [];
            if ($existing && !empty($existing['metadata'])) {
                $existingMeta = json_decode($existing['metadata'], true) ?: [];
            }

            // Fusionner avec les nouvelles métadonnées
            $newMeta = array_merge($existingMeta, $metadata);
            $data['metadata'] = json_encode($newMeta);
        }

        return $this->db->update('climbing_media', $data, "id = ?", [$mediaId]) > 0;
    }

    /**
     * Met à jour les références dans les tables existantes
     */
    private function updateReferencesInDb(): void
    {
        echo "\nMise à jour des références dans les tables existantes...\n";

        // Tables et colonnes à vérifier
        $tablesToCheck = [
            'secteur' => ['image', 'image2', 'image3'],
            'topo' => ['image'],
            // Ajoutez d'autres tables/colonnes si nécessaire
        ];

        $totalUpdates = 0;

        foreach ($tablesToCheck as $table => $columns) {
            foreach ($columns as $column) {
                echo "Vérification de $table.$column...\n";

                // Récupérer tous les enregistrements avec une valeur dans cette colonne
                $records = $this->db->query("SELECT id, $column FROM $table WHERE $column IS NOT NULL AND $column != ''")->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($records as $record) {
                    $oldValue = $record[$column];

                    // Vérifier si c'est un nom de fichier dans /image/
                    if (strpos($oldValue, 'image/') === 0 || strpos($oldValue, '/image/') === 0) {
                        // Trouver le nom de fichier
                        $filename = basename($oldValue);

                        // Rechercher le nouveau chemin dans la base de données
                        $media = $this->db->fetchOne(
                            "SELECT file_path FROM climbing_media WHERE original_filename = ? OR filename LIKE ?",
                            [$filename, "%$filename%"]
                        );

                        if ($media && !empty($media['file_path'])) {
                            // Mettre à jour la référence
                            $this->db->update(
                                $table,
                                [$column => $media['file_path']],
                                "id = ?",
                                [$record['id']]
                            );

                            $totalUpdates++;
                            echo "  - Mis à jour: $oldValue -> {$media['file_path']}\n";
                        }
                    }
                }
            }
        }

        echo "Total des références mises à jour: $totalUpdates\n";
    }
}

// Exécution du script si appelé directement
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $script = new MediaMigrationScript();
    $script->run();
}
