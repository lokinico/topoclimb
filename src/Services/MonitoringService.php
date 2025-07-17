<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

/**
 * Service de monitoring et métriques pour TopoclimbCH
 * Surveille les performances, erreurs et utilisation du système
 */
class MonitoringService
{
    private Database $db;
    private string $logPath;
    private const MAX_LOG_SIZE = 100 * 1024 * 1024; // 100MB
    private const MAX_LOG_FILES = 10;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logPath = __DIR__ . '/../../storage/logs/';
        
        // Créer le dossier de logs s'il n'existe pas
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Enregistre une métrique de performance
     */
    public function recordMetric(string $metric, float $value, array $tags = []): void
    {
        $data = [
            'timestamp' => time(),
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'hostname' => gethostname(),
            'pid' => getmypid()
        ];

        $this->writeLog('metrics', $data);
        $this->storeMetricInDb($data);
    }

    /**
     * Enregistre une erreur
     */
    public function recordError(string $level, string $message, array $context = []): void
    {
        $data = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'hostname' => gethostname(),
            'pid' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        $this->writeLog('errors', $data);
        $this->storeErrorInDb($data);
        
        // Alerter si erreur critique
        if ($level === 'critical' || $level === 'emergency') {
            $this->sendCriticalAlert($data);
        }
    }

    /**
     * Enregistre une action utilisateur
     */
    public function recordUserAction(int $userId, string $action, array $data = []): void
    {
        $logData = [
            'timestamp' => time(),
            'user_id' => $userId,
            'action' => $action,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id()
        ];

        $this->writeLog('user_actions', $logData);
        $this->storeUserActionInDb($logData);
    }

    /**
     * Surveille les performances du système
     */
    public function monitorSystemPerformance(): array
    {
        $metrics = [
            'timestamp' => time(),
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'database_performance' => $this->getDatabasePerformance(),
            'response_times' => $this->getResponseTimes(),
            'active_users' => $this->getActiveUsers(),
            'error_rate' => $this->getErrorRate()
        ];

        $this->recordMetric('system.cpu_usage', $metrics['cpu_usage']);
        $this->recordMetric('system.memory_usage', $metrics['memory_usage']);
        $this->recordMetric('system.disk_usage', $metrics['disk_usage']);
        $this->recordMetric('system.active_users', $metrics['active_users']);
        $this->recordMetric('system.error_rate', $metrics['error_rate']);

        return $metrics;
    }

    /**
     * Obtient les métriques d'utilisation
     */
    public function getUsageMetrics(int $hours = 24): array
    {
        $since = time() - ($hours * 3600);
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as unique_users,
                action,
                COUNT(*) as action_count
            FROM user_actions 
            WHERE timestamp > ?
            GROUP BY action
            ORDER BY action_count DESC
        ");
        $stmt->execute([$since]);
        
        $actions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            SELECT 
                AVG(value) as avg_response_time,
                MAX(value) as max_response_time,
                MIN(value) as min_response_time
            FROM metrics 
            WHERE metric = 'response_time' AND timestamp > ?
        ");
        $stmt->execute([$since]);
        
        $responseStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'period_hours' => $hours,
            'actions' => $actions,
            'response_times' => $responseStats,
            'popular_features' => $this->getPopularFeatures($since),
            'user_activity' => $this->getUserActivity($since)
        ];
    }

    /**
     * Obtient les statistiques d'erreurs
     */
    public function getErrorStats(int $hours = 24): array
    {
        $since = time() - ($hours * 3600);
        
        $stmt = $this->db->prepare("
            SELECT 
                level,
                COUNT(*) as count,
                COUNT(DISTINCT DATE(FROM_UNIXTIME(timestamp))) as days_with_errors
            FROM error_logs 
            WHERE timestamp > ?
            GROUP BY level
            ORDER BY count DESC
        ");
        $stmt->execute([$since]);
        
        $errorsByLevel = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            SELECT 
                message,
                COUNT(*) as count,
                MAX(timestamp) as last_occurrence
            FROM error_logs 
            WHERE timestamp > ?
            GROUP BY message
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$since]);
        
        $topErrors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'period_hours' => $hours,
            'errors_by_level' => $errorsByLevel,
            'top_errors' => $topErrors,
            'error_trend' => $this->getErrorTrend($since)
        ];
    }

    /**
     * Vérifie l'état de santé du système
     */
    public function healthCheck(): array
    {
        $health = [
            'timestamp' => time(),
            'overall_status' => 'healthy',
            'checks' => []
        ];

        // Vérifier la base de données
        $dbHealth = $this->checkDatabaseHealth();
        $health['checks']['database'] = $dbHealth;
        
        // Vérifier l'espace disque
        $diskHealth = $this->checkDiskSpace();
        $health['checks']['disk'] = $diskHealth;
        
        // Vérifier la mémoire
        $memoryHealth = $this->checkMemoryUsage();
        $health['checks']['memory'] = $memoryHealth;
        
        // Vérifier les services externes
        $externalHealth = $this->checkExternalServices();
        $health['checks']['external_services'] = $externalHealth;
        
        // Vérifier les erreurs récentes
        $errorHealth = $this->checkRecentErrors();
        $health['checks']['errors'] = $errorHealth;
        
        // Déterminer l'état global
        $unhealthyChecks = array_filter($health['checks'], function($check) {
            return $check['status'] !== 'healthy';
        });
        
        if (!empty($unhealthyChecks)) {
            $health['overall_status'] = 'unhealthy';
        }
        
        return $health;
    }

    /**
     * Nettoie les anciens logs
     */
    public function cleanupLogs(): void
    {
        $cutoffTime = time() - (30 * 24 * 3600); // 30 jours
        
        // Nettoyer les logs de base de données
        $stmt = $this->db->prepare("DELETE FROM error_logs WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);
        
        $stmt = $this->db->prepare("DELETE FROM user_actions WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);
        
        $stmt = $this->db->prepare("DELETE FROM metrics WHERE timestamp < ?");
        $stmt->execute([$cutoffTime]);
        
        // Nettoyer les fichiers de logs
        $this->cleanupLogFiles();
    }

    /**
     * Écrit dans les logs
     */
    private function writeLog(string $type, array $data): void
    {
        $logFile = $this->logPath . $type . '-' . date('Y-m-d') . '.log';
        $logLine = date('Y-m-d H:i:s') . ' ' . json_encode($data) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Rotation des logs si trop volumineux
        if (file_exists($logFile) && filesize($logFile) > self::MAX_LOG_SIZE) {
            $this->rotateLog($logFile);
        }
    }

    /**
     * Stocke une métrique en base de données
     */
    private function storeMetricInDb(array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO metrics (timestamp, metric, value, tags, hostname, pid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['timestamp'],
                $data['metric'],
                $data['value'],
                json_encode($data['tags']),
                $data['hostname'],
                $data['pid']
            ]);
        } catch (\Exception $e) {
            // Échec silencieux pour éviter les boucles d'erreur
            error_log("Erreur stockage métrique: " . $e->getMessage());
        }
    }

    /**
     * Stocke une erreur en base de données
     */
    private function storeErrorInDb(array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_logs (timestamp, level, message, context, hostname, pid, memory_usage, peak_memory)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['timestamp'],
                $data['level'],
                $data['message'],
                json_encode($data['context']),
                $data['hostname'],
                $data['pid'],
                $data['memory_usage'],
                $data['peak_memory']
            ]);
        } catch (\Exception $e) {
            error_log("Erreur stockage erreur: " . $e->getMessage());
        }
    }

    /**
     * Stocke une action utilisateur en base de données
     */
    private function storeUserActionInDb(array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_actions (timestamp, user_id, action, data, ip, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['timestamp'],
                $data['user_id'],
                $data['action'],
                json_encode($data['data']),
                $data['ip'],
                $data['user_agent'],
                $data['session_id']
            ]);
        } catch (\Exception $e) {
            error_log("Erreur stockage action utilisateur: " . $e->getMessage());
        }
    }

    /**
     * Obtient l'utilisation CPU
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0.0;
        }
        
        return 0.0;
    }

    /**
     * Obtient l'utilisation mémoire
     */
    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        return $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0.0;
    }

    /**
     * Obtient l'utilisation disque
     */
    private function getDiskUsage(): float
    {
        $totalSpace = disk_total_space(__DIR__);
        $freeSpace = disk_free_space(__DIR__);
        
        if ($totalSpace > 0) {
            return (($totalSpace - $freeSpace) / $totalSpace) * 100;
        }
        
        return 0.0;
    }

    /**
     * Obtient les performances de la base de données
     */
    private function getDatabasePerformance(): array
    {
        $start = microtime(true);
        
        try {
            $stmt = $this->db->prepare("SELECT 1");
            $stmt->execute();
            
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'response_time_ms' => $responseTime,
                'status' => 'healthy'
            ];
        } catch (\Exception $e) {
            return [
                'response_time_ms' => 0,
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtient les temps de réponse moyens
     */
    private function getResponseTimes(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                AVG(value) as avg_response_time,
                MAX(value) as max_response_time,
                MIN(value) as min_response_time
            FROM metrics 
            WHERE metric = 'response_time' AND timestamp > ?
        ");
        $stmt->execute([time() - 3600]); // Dernière heure
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'avg_response_time' => 0,
            'max_response_time' => 0,
            'min_response_time' => 0
        ];
    }

    /**
     * Obtient le nombre d'utilisateurs actifs
     */
    private function getActiveUsers(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT user_id) as active_users
            FROM user_actions 
            WHERE timestamp > ?
        ");
        $stmt->execute([time() - 3600]); // Dernière heure
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['active_users'] ?? 0;
    }

    /**
     * Obtient le taux d'erreur
     */
    private function getErrorRate(): float
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as error_count
            FROM error_logs 
            WHERE timestamp > ?
        ");
        $stmt->execute([time() - 3600]); // Dernière heure
        
        $errorCount = $stmt->fetch(\PDO::FETCH_ASSOC)['error_count'] ?? 0;
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_actions
            FROM user_actions 
            WHERE timestamp > ?
        ");
        $stmt->execute([time() - 3600]);
        
        $totalActions = $stmt->fetch(\PDO::FETCH_ASSOC)['total_actions'] ?? 0;
        
        return $totalActions > 0 ? ($errorCount / $totalActions) * 100 : 0.0;
    }

    /**
     * Obtient les fonctionnalités populaires
     */
    private function getPopularFeatures(int $since): array
    {
        $stmt = $this->db->prepare("
            SELECT action, COUNT(*) as usage_count
            FROM user_actions 
            WHERE timestamp > ?
            GROUP BY action
            ORDER BY usage_count DESC
            LIMIT 10
        ");
        $stmt->execute([$since]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient l'activité utilisateur
     */
    private function getUserActivity(int $since): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00') as hour,
                COUNT(*) as actions,
                COUNT(DISTINCT user_id) as unique_users
            FROM user_actions 
            WHERE timestamp > ?
            GROUP BY hour
            ORDER BY hour
        ");
        $stmt->execute([$since]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient la tendance des erreurs
     */
    private function getErrorTrend(int $since): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00') as hour,
                COUNT(*) as error_count,
                level
            FROM error_logs 
            WHERE timestamp > ?
            GROUP BY hour, level
            ORDER BY hour
        ");
        $stmt->execute([$since]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie la santé de la base de données
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            $stmt = $this->db->prepare("SELECT 1");
            $stmt->execute();
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Vérifie l'espace disque
     */
    private function checkDiskSpace(): array
    {
        $freeSpace = disk_free_space(__DIR__);
        $totalSpace = disk_total_space(__DIR__);
        
        if ($totalSpace > 0) {
            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            if ($usagePercent > 90) {
                return [
                    'status' => 'unhealthy',
                    'usage_percent' => $usagePercent,
                    'message' => 'Disk space critically low'
                ];
            } elseif ($usagePercent > 80) {
                return [
                    'status' => 'warning',
                    'usage_percent' => $usagePercent,
                    'message' => 'Disk space low'
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'usage_percent' => $usagePercent,
                    'message' => 'Disk space adequate'
                ];
            }
        }
        
        return [
            'status' => 'unknown',
            'message' => 'Unable to determine disk space'
        ];
    }

    /**
     * Vérifie l'utilisation mémoire
     */
    private function checkMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryLimit > 0) {
            $usagePercent = ($memoryUsage / $memoryLimit) * 100;
            
            if ($usagePercent > 90) {
                return [
                    'status' => 'unhealthy',
                    'usage_percent' => $usagePercent,
                    'message' => 'Memory usage critically high'
                ];
            } elseif ($usagePercent > 80) {
                return [
                    'status' => 'warning',
                    'usage_percent' => $usagePercent,
                    'message' => 'Memory usage high'
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'usage_percent' => $usagePercent,
                    'message' => 'Memory usage normal'
                ];
            }
        }
        
        return [
            'status' => 'unknown',
            'message' => 'Unable to determine memory usage'
        ];
    }

    /**
     * Vérifie les services externes
     */
    private function checkExternalServices(): array
    {
        $services = [
            'weather_api' => 'https://api.openweathermap.org/data/2.5/weather',
            'geocoding_api' => 'https://nominatim.openstreetmap.org/status',
            'swiss_maps' => 'https://api3.geo.admin.ch/rest/services/api/MapServer'
        ];
        
        $results = [];
        
        foreach ($services as $name => $url) {
            $start = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'HEAD'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            $responseTime = (microtime(true) - $start) * 1000;
            
            if ($response !== false) {
                $results[$name] = [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'message' => 'Service accessible'
                ];
            } else {
                $results[$name] = [
                    'status' => 'unhealthy',
                    'response_time_ms' => $responseTime,
                    'message' => 'Service not accessible'
                ];
            }
        }
        
        return $results;
    }

    /**
     * Vérifie les erreurs récentes
     */
    private function checkRecentErrors(): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as critical_errors
            FROM error_logs 
            WHERE level IN ('critical', 'emergency') AND timestamp > ?
        ");
        $stmt->execute([time() - 3600]); // Dernière heure
        
        $criticalErrors = $stmt->fetch(\PDO::FETCH_ASSOC)['critical_errors'] ?? 0;
        
        if ($criticalErrors > 0) {
            return [
                'status' => 'unhealthy',
                'critical_errors' => $criticalErrors,
                'message' => "Critical errors detected: {$criticalErrors}"
            ];
        }
        
        return [
            'status' => 'healthy',
            'critical_errors' => 0,
            'message' => 'No critical errors'
        ];
    }

    /**
     * Envoie une alerte critique
     */
    private function sendCriticalAlert(array $data): void
    {
        // TODO: Implémenter l'envoi d'alertes (email, Slack, etc.)
        error_log("ALERTE CRITIQUE: " . json_encode($data));
    }

    /**
     * Nettoie les fichiers de logs
     */
    private function cleanupLogFiles(): void
    {
        $files = glob($this->logPath . '*.log');
        
        if (count($files) > self::MAX_LOG_FILES) {
            // Trier par date de modification
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Supprimer les plus anciens
            $filesToDelete = array_slice($files, 0, count($files) - self::MAX_LOG_FILES);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Rotation des logs
     */
    private function rotateLog(string $logFile): void
    {
        $rotatedFile = $logFile . '.' . date('His');
        rename($logFile, $rotatedFile);
        
        // Compresser le fichier rotaté
        if (function_exists('gzopen')) {
            $gz = gzopen($rotatedFile . '.gz', 'wb9');
            $fp = fopen($rotatedFile, 'rb');
            
            while (!feof($fp)) {
                gzwrite($gz, fread($fp, 8192));
            }
            
            fclose($fp);
            gzclose($gz);
            unlink($rotatedFile);
        }
    }

    /**
     * Parse la limite de mémoire
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower($limit);
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $value = (int) $limit;
        $unit = substr($limit, -1);
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}