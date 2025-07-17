<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use ZipArchive;

/**
 * Service de sauvegarde automatique pour TopoclimbCH
 * Gère les backups de la base de données et des fichiers
 */
class BackupService
{
    private Database $db;
    private string $backupPath;
    private string $tempPath;
    private const MAX_BACKUP_FILES = 30; // Garder 30 backups
    private const BACKUP_RETENTION_DAYS = 90; // 90 jours

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->backupPath = __DIR__ . '/../../storage/backups/';
        $this->tempPath = __DIR__ . '/../../storage/temp/';
        
        // Créer les dossiers s'ils n'existent pas
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Crée un backup complet du système
     */
    public function createFullBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "topoclimb_backup_{$timestamp}";
        
        try {
            $result = [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'type' => 'full',
                'status' => 'success',
                'components' => [],
                'size' => 0,
                'duration' => 0
            ];
            
            $startTime = microtime(true);
            
            // Backup base de données
            $dbBackup = $this->backupDatabase($backupName);
            $result['components']['database'] = $dbBackup;
            
            // Backup fichiers uploads
            $filesBackup = $this->backupFiles($backupName);
            $result['components']['files'] = $filesBackup;
            
            // Backup configuration
            $configBackup = $this->backupConfiguration($backupName);
            $result['components']['configuration'] = $configBackup;
            
            // Créer l'archive finale
            $archiveResult = $this->createFinalArchive($backupName);
            $result['components']['archive'] = $archiveResult;
            
            $result['duration'] = microtime(true) - $startTime;
            $result['size'] = $archiveResult['size'] ?? 0;
            
            // Nettoyer les fichiers temporaires
            $this->cleanupTempFiles($backupName);
            
            // Nettoyer les anciens backups
            $this->cleanupOldBackups();
            
            // Enregistrer le backup en base
            $this->recordBackup($result);
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'type' => 'full',
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Crée un backup incrémental
     */
    public function createIncrementalBackup(int $sinceDays = 1): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "topoclimb_incremental_{$timestamp}";
        $since = date('Y-m-d H:i:s', strtotime("-{$sinceDays} days"));
        
        try {
            $result = [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'type' => 'incremental',
                'since' => $since,
                'status' => 'success',
                'components' => [],
                'size' => 0,
                'duration' => 0
            ];
            
            $startTime = microtime(true);
            
            // Backup données modifiées
            $dbBackup = $this->backupDatabaseIncremental($backupName, $since);
            $result['components']['database'] = $dbBackup;
            
            // Backup fichiers modifiés
            $filesBackup = $this->backupFilesIncremental($backupName, $since);
            $result['components']['files'] = $filesBackup;
            
            // Créer l'archive finale
            $archiveResult = $this->createFinalArchive($backupName);
            $result['components']['archive'] = $archiveResult;
            
            $result['duration'] = microtime(true) - $startTime;
            $result['size'] = $archiveResult['size'] ?? 0;
            
            // Nettoyer les fichiers temporaires
            $this->cleanupTempFiles($backupName);
            
            // Enregistrer le backup en base
            $this->recordBackup($result);
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'type' => 'incremental',
                'since' => $since,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Restaure un backup
     */
    public function restoreBackup(string $backupName): array
    {
        $backupFile = $this->backupPath . $backupName . '.zip';
        
        if (!file_exists($backupFile)) {
            return [
                'status' => 'error',
                'error' => 'Backup file not found'
            ];
        }
        
        try {
            $startTime = microtime(true);
            
            // Extraire l'archive
            $extractPath = $this->tempPath . 'restore_' . uniqid();
            $this->extractArchive($backupFile, $extractPath);
            
            $result = [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'status' => 'success',
                'components' => [],
                'duration' => 0
            ];
            
            // Restaurer la base de données
            if (file_exists($extractPath . '/database.sql')) {
                $dbRestore = $this->restoreDatabase($extractPath . '/database.sql');
                $result['components']['database'] = $dbRestore;
            }
            
            // Restaurer les fichiers
            if (is_dir($extractPath . '/files')) {
                $filesRestore = $this->restoreFiles($extractPath . '/files');
                $result['components']['files'] = $filesRestore;
            }
            
            // Restaurer la configuration
            if (file_exists($extractPath . '/config.json')) {
                $configRestore = $this->restoreConfiguration($extractPath . '/config.json');
                $result['components']['configuration'] = $configRestore;
            }
            
            $result['duration'] = microtime(true) - $startTime;
            
            // Nettoyer les fichiers temporaires
            $this->recursiveRemoveDirectory($extractPath);
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'timestamp' => time(),
                'backup_name' => $backupName,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Liste les backups disponibles
     */
    public function listBackups(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM backups 
            ORDER BY timestamp DESC
        ");
        $stmt->execute();
        
        $backups = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Ajouter les informations de fichier
        foreach ($backups as &$backup) {
            $backupFile = $this->backupPath . $backup['backup_name'] . '.zip';
            $backup['file_exists'] = file_exists($backupFile);
            $backup['file_size'] = $backup['file_exists'] ? filesize($backupFile) : 0;
        }
        
        return $backups;
    }

    /**
     * Obtient les statistiques de backup
     */
    public function getBackupStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_backups,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_backups,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_backups,
                AVG(duration) as avg_duration,
                SUM(size) as total_size,
                MAX(timestamp) as last_backup
            FROM backups
        ");
        $stmt->execute();
        
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Ajouter les statistiques de fichiers
        $backupFiles = glob($this->backupPath . '*.zip');
        $totalFileSize = 0;
        
        foreach ($backupFiles as $file) {
            $totalFileSize += filesize($file);
        }
        
        $stats['backup_files_count'] = count($backupFiles);
        $stats['backup_files_size'] = $totalFileSize;
        $stats['backup_directory'] = $this->backupPath;
        
        return $stats;
    }

    /**
     * Sauvegarde la base de données
     */
    private function backupDatabase(string $backupName): array
    {
        $outputFile = $this->tempPath . $backupName . '_database.sql';
        
        try {
            $startTime = microtime(true);
            
            // Obtenir la liste des tables
            $tables = $this->getDatabaseTables();
            
            $output = "-- TopoclimbCH Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            foreach ($tables as $table) {
                $output .= $this->exportTable($table);
            }
            
            $output .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
            
            file_put_contents($outputFile, $output);
            
            return [
                'status' => 'success',
                'file' => $outputFile,
                'size' => filesize($outputFile),
                'tables_count' => count($tables),
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Sauvegarde incrémentale de la base de données
     */
    private function backupDatabaseIncremental(string $backupName, string $since): array
    {
        $outputFile = $this->tempPath . $backupName . '_database_incremental.sql';
        
        try {
            $startTime = microtime(true);
            
            $output = "-- TopoclimbCH Incremental Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Since: {$since}\n\n";
            
            // Tables avec timestamps
            $tablesWithTimestamps = [
                'climbing_regions' => 'updated_at',
                'climbing_sites' => 'updated_at',
                'climbing_sectors' => 'updated_at',
                'climbing_routes' => 'updated_at',
                'climbing_alerts' => 'updated_at',
                'users' => 'date_registered',
                'user_ascents' => 'created_at'
            ];
            
            foreach ($tablesWithTimestamps as $table => $timestampField) {
                $output .= $this->exportTableIncremental($table, $timestampField, $since);
            }
            
            file_put_contents($outputFile, $output);
            
            return [
                'status' => 'success',
                'file' => $outputFile,
                'size' => filesize($outputFile),
                'since' => $since,
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Sauvegarde les fichiers
     */
    private function backupFiles(string $backupName): array
    {
        $sourceDir = __DIR__ . '/../../storage/uploads/';
        $outputDir = $this->tempPath . $backupName . '_files/';
        
        try {
            $startTime = microtime(true);
            
            if (!is_dir($sourceDir)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'No uploads directory found'
                ];
            }
            
            mkdir($outputDir, 0755, true);
            
            $fileCount = $this->copyDirectory($sourceDir, $outputDir);
            $totalSize = $this->getDirectorySize($outputDir);
            
            return [
                'status' => 'success',
                'directory' => $outputDir,
                'files_count' => $fileCount,
                'size' => $totalSize,
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Sauvegarde incrémentale des fichiers
     */
    private function backupFilesIncremental(string $backupName, string $since): array
    {
        $sourceDir = __DIR__ . '/../../storage/uploads/';
        $outputDir = $this->tempPath . $backupName . '_files/';
        $sinceTimestamp = strtotime($since);
        
        try {
            $startTime = microtime(true);
            
            if (!is_dir($sourceDir)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'No uploads directory found'
                ];
            }
            
            mkdir($outputDir, 0755, true);
            
            $fileCount = $this->copyDirectoryIncremental($sourceDir, $outputDir, $sinceTimestamp);
            $totalSize = $this->getDirectorySize($outputDir);
            
            return [
                'status' => 'success',
                'directory' => $outputDir,
                'files_count' => $fileCount,
                'size' => $totalSize,
                'since' => $since,
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Sauvegarde la configuration
     */
    private function backupConfiguration(string $backupName): array
    {
        $outputFile = $this->tempPath . $backupName . '_config.json';
        
        try {
            $startTime = microtime(true);
            
            $config = [
                'timestamp' => time(),
                'php_version' => PHP_VERSION,
                'app_version' => '1.0.0',
                'database_type' => 'sqlite',
                'extensions' => get_loaded_extensions(),
                'settings' => [
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_limit' => ini_get('memory_limit'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ]
            ];
            
            file_put_contents($outputFile, json_encode($config, JSON_PRETTY_PRINT));
            
            return [
                'status' => 'success',
                'file' => $outputFile,
                'size' => filesize($outputFile),
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Crée l'archive finale
     */
    private function createFinalArchive(string $backupName): array
    {
        $archiveFile = $this->backupPath . $backupName . '.zip';
        $tempDir = $this->tempPath;
        
        try {
            $startTime = microtime(true);
            
            $zip = new ZipArchive();
            $result = $zip->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($result !== true) {
                throw new \Exception("Cannot create zip file: {$result}");
            }
            
            // Ajouter tous les fichiers temporaires
            $files = glob($tempDir . $backupName . '_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, basename($file));
                } elseif (is_dir($file)) {
                    $this->addDirectoryToZip($zip, $file, basename($file) . '/');
                }
            }
            
            $zip->close();
            
            return [
                'status' => 'success',
                'file' => $archiveFile,
                'size' => filesize($archiveFile),
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Obtient la liste des tables de la base de données
     */
    private function getDatabaseTables(): array
    {
        $stmt = $this->db->prepare("
            SELECT name FROM sqlite_master 
            WHERE type='table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");
        $stmt->execute();
        
        $tables = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $tables[] = $row['name'];
        }
        
        return $tables;
    }

    /**
     * Exporte une table
     */
    private function exportTable(string $table): string
    {
        $output = "-- Table: {$table}\n";
        
        // Structure de la table
        $stmt = $this->db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        $createSql = $stmt->fetch(\PDO::FETCH_ASSOC)['sql'];
        
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $createSql . ";\n\n";
        
        // Données de la table
        $stmt = $this->db->prepare("SELECT * FROM {$table}");
        $stmt->execute();
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $values = array_map(function($value) {
                return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
            }, $row);
            
            $output .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
        }
        
        $output .= "\n";
        
        return $output;
    }

    /**
     * Exporte une table de façon incrémentale
     */
    private function exportTableIncremental(string $table, string $timestampField, string $since): string
    {
        $output = "-- Incremental Table: {$table} (since {$since})\n";
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE {$timestampField} > ?");
            $stmt->execute([$since]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $values = array_map(function($value) {
                    return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
                }, $row);
                
                $output .= "INSERT OR REPLACE INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
            }
            
            $output .= "\n";
            
        } catch (\Exception $e) {
            $output .= "-- Error exporting {$table}: " . $e->getMessage() . "\n\n";
        }
        
        return $output;
    }

    /**
     * Copie un répertoire
     */
    private function copyDirectory(string $source, string $destination): int
    {
        $fileCount = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $destination . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                mkdir($destPath, 0755, true);
            } else {
                copy($item, $destPath);
                $fileCount++;
            }
        }
        
        return $fileCount;
    }

    /**
     * Copie un répertoire de façon incrémentale
     */
    private function copyDirectoryIncremental(string $source, string $destination, int $sinceTimestamp): int
    {
        $fileCount = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getMTime() > $sinceTimestamp) {
                $destPath = $destination . $iterator->getSubPathName();
                $destDir = dirname($destPath);
                
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                copy($item, $destPath);
                $fileCount++;
            }
        }
        
        return $fileCount;
    }

    /**
     * Obtient la taille d'un répertoire
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }

    /**
     * Ajoute un répertoire à un zip
     */
    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . substr($filePath, strlen($directory) + 1);
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Extrait une archive
     */
    private function extractArchive(string $archiveFile, string $destination): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($archiveFile);
        
        if ($result !== true) {
            throw new \Exception("Cannot open zip file: {$result}");
        }
        
        $zip->extractTo($destination);
        $zip->close();
    }

    /**
     * Restaure la base de données
     */
    private function restoreDatabase(string $sqlFile): array
    {
        try {
            $startTime = microtime(true);
            
            $sql = file_get_contents($sqlFile);
            $statements = explode(';', $sql);
            
            $this->db->beginTransaction();
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }
            
            $this->db->commit();
            
            return [
                'status' => 'success',
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Restaure les fichiers
     */
    private function restoreFiles(string $sourceDir): array
    {
        try {
            $startTime = microtime(true);
            
            $destinationDir = __DIR__ . '/../../storage/uploads/';
            
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            
            $fileCount = $this->copyDirectory($sourceDir, $destinationDir);
            
            return [
                'status' => 'success',
                'files_count' => $fileCount,
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Restaure la configuration
     */
    private function restoreConfiguration(string $configFile): array
    {
        try {
            $startTime = microtime(true);
            
            $config = json_decode(file_get_contents($configFile), true);
            
            // TODO: Restaurer les paramètres de configuration
            
            return [
                'status' => 'success',
                'config' => $config,
                'duration' => microtime(true) - $startTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - ($startTime ?? microtime(true))
            ];
        }
    }

    /**
     * Nettoie les fichiers temporaires
     */
    private function cleanupTempFiles(string $backupName): void
    {
        $files = glob($this->tempPath . $backupName . '_*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->recursiveRemoveDirectory($file);
            }
        }
    }

    /**
     * Nettoie les anciens backups
     */
    private function cleanupOldBackups(): void
    {
        $cutoffTime = time() - (self::BACKUP_RETENTION_DAYS * 24 * 3600);
        
        // Supprimer les anciens backups de la base de données
        $stmt = $this->db->prepare("SELECT backup_name FROM backups WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $backupFile = $this->backupPath . $row['backup_name'] . '.zip';
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
        }
        
        // Supprimer les enregistrements de la base de données
        $stmt = $this->db->prepare("DELETE FROM backups WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);
        
        // Limiter le nombre de backups
        $stmt = $this->db->prepare("
            SELECT backup_name FROM backups 
            ORDER BY timestamp DESC 
            LIMIT -1 OFFSET ?
        ");
        $stmt->execute([self::MAX_BACKUP_FILES]);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $backupFile = $this->backupPath . $row['backup_name'] . '.zip';
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
        }
        
        $stmt = $this->db->prepare("
            DELETE FROM backups 
            WHERE backup_name NOT IN (
                SELECT backup_name FROM backups 
                ORDER BY timestamp DESC 
                LIMIT ?
            )
        ");
        $stmt->execute([self::MAX_BACKUP_FILES]);
    }

    /**
     * Enregistre un backup en base de données
     */
    private function recordBackup(array $backup): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO backups (
                timestamp, backup_name, type, status, size, duration, 
                components, error, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $backup['timestamp'],
            $backup['backup_name'],
            $backup['type'],
            $backup['status'],
            $backup['size'] ?? 0,
            $backup['duration'] ?? 0,
            json_encode($backup['components'] ?? []),
            $backup['error'] ?? null,
            date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Supprime récursivement un répertoire
     */
    private function recursiveRemoveDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($directory);
    }
}