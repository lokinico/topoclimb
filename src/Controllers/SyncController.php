<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;
use TopoclimbCH\Services\SyncService;
use TopoclimbCH\Services\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class SyncController extends BaseController
{
    private SyncService $syncService;
    private AuthService $authService;

    public function __construct(
        SyncService $syncService,
        AuthService $authService
    ) {
        $this->syncService = $syncService;
        $this->authService = $authService;
    }

    /**
     * Obtient les données pour le mode hors-ligne
     */
    public function getOfflineData(Request $request): JsonResponse
    {
        $userId = $this->authService->isAuthenticated() ? $this->authService->getCurrentUserId() : null;
        
        try {
            $data = $this->syncService->getOfflineData($userId);
            
            return new JsonResponse($data, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Expires' => '0'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération des données hors-ligne',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchronisation delta (changements depuis un timestamp)
     */
    public function getDeltaSync(Request $request): JsonResponse
    {
        $lastSync = (int) $request->query->get('lastSync', 0);
        $userId = $this->authService->isAuthenticated() ? $this->authService->getCurrentUserId() : null;
        
        if (!$lastSync) {
            return new JsonResponse([
                'error' => 'Paramètre lastSync requis'
            ], 400);
        }
        
        try {
            $data = $this->syncService->getDeltaSync($lastSync, $userId);
            
            return new JsonResponse($data, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Expires' => '0'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la synchronisation delta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchronise les modifications locales vers le serveur
     */
    public function syncLocalChanges(Request $request): JsonResponse
    {
        if (!$this->authService->isAuthenticated()) {
            return new JsonResponse([
                'error' => 'Authentification requise'
            ], 401);
        }
        
        $userId = $this->authService->getCurrentUserId();
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['changes'])) {
            return new JsonResponse([
                'error' => 'Données de changements requises'
            ], 400);
        }
        
        try {
            $results = $this->syncService->syncLocalChanges($data['changes'], $userId);
            
            return new JsonResponse($results, 200, [
                'Content-Type' => 'application/json'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la synchronisation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtient les statistiques de synchronisation
     */
    public function getSyncStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->syncService->getSyncStats();
            
            return new JsonResponse($stats, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'public, max-age=300' // 5 minutes
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération des statistiques',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}