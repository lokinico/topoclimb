<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;
use TopoclimbCH\Services\MonitoringService;
use TopoclimbCH\Services\BackupService;
use TopoclimbCH\Services\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class MonitoringController extends BaseController
{
    private MonitoringService $monitoringService;
    private BackupService $backupService;
    private AuthService $authService;

    public function __construct(
        MonitoringService $monitoringService,
        BackupService $backupService,
        AuthService $authService
    ) {
        $this->monitoringService = $monitoringService;
        $this->backupService = $backupService;
        $this->authService = $authService;
    }

    /**
     * Dashboard de monitoring (admin seulement)
     */
    public function dashboard(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $healthCheck = $this->monitoringService->healthCheck();
        $systemPerformance = $this->monitoringService->monitorSystemPerformance();
        $usageMetrics = $this->monitoringService->getUsageMetrics(24);
        $errorStats = $this->monitoringService->getErrorStats(24);
        $backupStats = $this->backupService->getBackupStats();

        return $this->render('monitoring/dashboard.twig', [
            'page_title' => 'Monitoring et Surveillance',
            'health_check' => $healthCheck,
            'system_performance' => $systemPerformance,
            'usage_metrics' => $usageMetrics,
            'error_stats' => $errorStats,
            'backup_stats' => $backupStats
        ]);
    }

    /**
     * API: Health check
     */
    public function apiHealthCheck(Request $request): JsonResponse
    {
        $health = $this->monitoringService->healthCheck();
        
        $statusCode = $health['overall_status'] === 'healthy' ? 200 : 503;
        
        return new JsonResponse($health, $statusCode);
    }

    /**
     * API: Métriques système
     */
    public function apiSystemMetrics(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $metrics = $this->monitoringService->monitorSystemPerformance();
        
        return new JsonResponse($metrics);
    }

    /**
     * API: Métriques d'utilisation
     */
    public function apiUsageMetrics(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $hours = (int) $request->query->get('hours', 24);
        $metrics = $this->monitoringService->getUsageMetrics($hours);
        
        return new JsonResponse($metrics);
    }

    /**
     * API: Statistiques d'erreurs
     */
    public function apiErrorStats(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $hours = (int) $request->query->get('hours', 24);
        $stats = $this->monitoringService->getErrorStats($hours);
        
        return new JsonResponse($stats);
    }

    /**
     * API: Enregistrer une métrique
     */
    public function apiRecordMetric(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['metric']) || !isset($data['value'])) {
            return new JsonResponse(['error' => 'Metric et value requis'], 400);
        }

        $this->monitoringService->recordMetric(
            $data['metric'],
            (float) $data['value'],
            $data['tags'] ?? []
        );
        
        return new JsonResponse(['success' => true]);
    }

    /**
     * API: Enregistrer une erreur
     */
    public function apiRecordError(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['level']) || !isset($data['message'])) {
            return new JsonResponse(['error' => 'Level et message requis'], 400);
        }

        $this->monitoringService->recordError(
            $data['level'],
            $data['message'],
            $data['context'] ?? []
        );
        
        return new JsonResponse(['success' => true]);
    }

    /**
     * API: Enregistrer une action utilisateur
     */
    public function apiRecordUserAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['action'])) {
            return new JsonResponse(['error' => 'Action requise'], 400);
        }

        $userId = $this->authService->getCurrentUserId();
        
        if (!$userId) {
            return new JsonResponse(['error' => 'Utilisateur non connecté'], 401);
        }

        $this->monitoringService->recordUserAction(
            $userId,
            $data['action'],
            $data['data'] ?? []
        );
        
        return new JsonResponse(['success' => true]);
    }

    /**
     * Page de gestion des backups
     */
    public function backups(Request $request): Response
    {
        if (!$this->authService->hasRole(['admin'])) {
            return $this->unauthorized('Accès non autorisé');
        }

        $backups = $this->backupService->listBackups();
        $stats = $this->backupService->getBackupStats();

        return $this->render('monitoring/backups.twig', [
            'page_title' => 'Gestion des Backups',
            'backups' => $backups,
            'stats' => $stats
        ]);
    }

    /**
     * API: Créer un backup complet
     */
    public function apiCreateFullBackup(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $result = $this->backupService->createFullBackup();
        
        return new JsonResponse($result);
    }

    /**
     * API: Créer un backup incrémental
     */
    public function apiCreateIncrementalBackup(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $days = (int) $request->query->get('days', 1);
        $result = $this->backupService->createIncrementalBackup($days);
        
        return new JsonResponse($result);
    }

    /**
     * API: Restaurer un backup
     */
    public function apiRestoreBackup(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $backupName = $request->attributes->get('backup_name');
        
        if (!$backupName) {
            return new JsonResponse(['error' => 'Nom de backup requis'], 400);
        }

        $result = $this->backupService->restoreBackup($backupName);
        
        return new JsonResponse($result);
    }

    /**
     * API: Lister les backups
     */
    public function apiListBackups(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $backups = $this->backupService->listBackups();
        
        return new JsonResponse($backups);
    }

    /**
     * API: Statistiques des backups
     */
    public function apiBackupStats(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $stats = $this->backupService->getBackupStats();
        
        return new JsonResponse($stats);
    }

    /**
     * API: Nettoyer les anciens logs
     */
    public function apiCleanupLogs(Request $request): JsonResponse
    {
        if (!$this->authService->hasRole(['admin'])) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $this->monitoringService->cleanupLogs();
        
        return new JsonResponse(['success' => true, 'message' => 'Logs nettoyés']);
    }

    /**
     * Endpoint pour les webhooks de monitoring externe
     */
    public function webhook(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['type'])) {
            return new JsonResponse(['error' => 'Type de webhook requis'], 400);
        }

        // Traiter selon le type de webhook
        switch ($data['type']) {
            case 'metric':
                if (isset($data['metric']) && isset($data['value'])) {
                    $this->monitoringService->recordMetric(
                        $data['metric'],
                        (float) $data['value'],
                        $data['tags'] ?? []
                    );
                }
                break;
                
            case 'error':
                if (isset($data['level']) && isset($data['message'])) {
                    $this->monitoringService->recordError(
                        $data['level'],
                        $data['message'],
                        $data['context'] ?? []
                    );
                }
                break;
                
            case 'health_check':
                $health = $this->monitoringService->healthCheck();
                return new JsonResponse($health);
                
            default:
                return new JsonResponse(['error' => 'Type de webhook non supporté'], 400);
        }
        
        return new JsonResponse(['success' => true]);
    }

    /**
     * Middleware pour enregistrer automatiquement les métriques de performance
     */
    public function recordResponseTime(Request $request, Response $response): void
    {
        $startTime = $request->server->get('REQUEST_TIME_FLOAT');
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // en millisecondes
        
        $this->monitoringService->recordMetric('response_time', $responseTime, [
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'status' => $response->getStatusCode()
        ]);
        
        // Enregistrer l'action utilisateur si connecté
        if ($this->authService->isAuthenticated()) {
            $this->monitoringService->recordUserAction(
                $this->authService->getCurrentUserId(),
                'page_view',
                [
                    'path' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                    'response_time' => $responseTime,
                    'status' => $response->getStatusCode()
                ]
            );
        }
    }
}