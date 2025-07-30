<?php
/**
 * Contrôleur Analytics pour la collecte des données d'usage du système de vues
 */

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Auth;

class AnalyticsController extends BaseController
{
    protected ?Database $db;
    
    public function __construct(
        View $view, 
        Session $session, 
        CsrfManager $csrfManager,
        ?Database $db = null,
        ?Auth $auth = null
    )
    {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->db = $db ?: new Database();
    }
    
    /**
     * Recevoir les événements analytics des vues
     */
    public function receiveViewEvents(Request $request): Response
    {
        try {
            // Vérifier la méthode
            if (!$request->isMethod('POST')) {
                return new Response(['error' => 'Method not allowed'], 405);
            }
            
            // Parser les données JSON
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['events'])) {
                return new Response(['error' => 'Invalid data format'], 400);
            }
            
            $events = $data['events'];
            $meta = $data['meta'] ?? [];
            
            // Valider et stocker chaque événement
            $storedCount = 0;
            $errors = [];
            
            foreach ($events as $event) {
                try {
                    $this->storeAnalyticsEvent($event, $meta);
                    $storedCount++;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                    error_log("Analytics error: " . $e->getMessage());
                }
            }
            
            // Log pour monitoring
            error_log("Analytics: Stored {$storedCount} events, " . count($errors) . " errors");
            
            return new Response([
                'success' => true,
                'stored' => $storedCount,
                'errors' => count($errors),
                'message' => "Successfully stored {$storedCount} events"
            ]);
            
        } catch (\Exception $e) {
            error_log("Analytics critical error: " . $e->getMessage());
            
            return new Response([
                'error' => 'Internal server error',
                'message' => 'Failed to process analytics data'
            ], 500);
        }
    }
    
    /**
     * Stocker un événement analytics
     */
    private function storeAnalyticsEvent(array $event, array $meta): void
    {
        // Validation des données obligatoires
        if (!isset($event['type'], $event['timestamp'], $event['sessionId'])) {
            throw new \InvalidArgumentException('Missing required event fields');
        }
        
        // Préparer les données pour insertion
        $eventData = [
            'session_id' => $event['sessionId'],
            'event_type' => $event['type'],
            'timestamp' => date('Y-m-d H:i:s', $event['timestamp'] / 1000), // Convertir milliseconds
            'page' => $event['data']['page'] ?? null,
            'event_data' => json_encode($event['data'] ?? []),
            'user_agent' => $meta['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIP(),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        // Ajouter l'ID utilisateur si disponible
        if ($this->auth && $this->auth->check()) {
            $eventData['user_id'] = $this->auth->id();
        }
        
        // Insert en base de données
        $sql = "INSERT INTO view_analytics (
            session_id, event_type, timestamp, page, event_data, 
            user_agent, ip_address, user_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $eventData['session_id'],
            $eventData['event_type'],
            $eventData['timestamp'],
            $eventData['page'],
            $eventData['event_data'],
            $eventData['user_agent'],
            $eventData['ip_address'],
            $eventData['user_id'] ?? null,
            $eventData['created_at']
        ]);
    }
    
    /**
     * Obtenir l'IP client (avec support proxy)
     */
    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Si plusieurs IPs (proxy), prendre la première
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Valider que c'est une IP valide
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Dashboard analytics simple (pour admin)
     */
    public function dashboard(Request $request): Response
    {
        // Vérifier les permissions admin
        if (!$this->auth || !$this->auth->check() || $this->auth->role() !== 0) {
            return new Response(['error' => 'Unauthorized'], 403);
        }
        
        try {
            // Statistiques de base
            $stats = $this->getAnalyticsStats();
            
            // Données pour graphiques
            $viewChanges = $this->getViewChangeStats();
            $pageViews = $this->getPageViewStats();
            $performance = $this->getPerformanceStats();
            
            return $this->view->render('analytics/dashboard.twig', [
                'stats' => $stats,
                'viewChanges' => $viewChanges,
                'pageViews' => $pageViews,
                'performance' => $performance,
                'title' => 'Analytics Dashboard'
            ]);
            
        } catch (\Exception $e) {
            error_log("Analytics dashboard error: " . $e->getMessage());
            
            return $this->view->render('errors/500.twig', [
                'message' => 'Unable to load analytics dashboard'
            ]);
        }
    }
    
    /**
     * Statistiques générales
     */
    private function getAnalyticsStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT session_id) as unique_sessions,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT page) as unique_pages,
                MIN(created_at) as first_event,
                MAX(created_at) as last_event
            FROM view_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        return $this->db->query($sql)->fetch() ?: [];
    }
    
    /**
     * Statistiques changements de vue
     */
    private function getViewChangeStats(): array
    {
        $sql = "
            SELECT 
                JSON_EXTRACT(event_data, '$.toView') as view_type,
                COUNT(*) as count,
                page
            FROM view_analytics 
            WHERE event_type = 'view_change'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND JSON_EXTRACT(event_data, '$.toView') IS NOT NULL
            GROUP BY view_type, page
            ORDER BY count DESC
            LIMIT 20
        ";
        
        return $this->db->query($sql)->fetchAll() ?: [];
    }
    
    /**
     * Statistiques vues de page
     */
    private function getPageViewStats(): array
    {
        $sql = "
            SELECT 
                page,
                COUNT(*) as views,
                COUNT(DISTINCT session_id) as unique_sessions
            FROM view_analytics 
            WHERE event_type = 'page_view'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY page
            ORDER BY views DESC
        ";
        
        return $this->db->query($sql)->fetchAll() ?: [];
    }
    
    /**
     * Statistiques de performance
     */
    private function getPerformanceStats(): array
    {
        $sql = "
            SELECT 
                page,
                AVG(JSON_EXTRACT(event_data, '$.loadTime')) as avg_load_time,
                COUNT(*) as samples
            FROM view_analytics 
            WHERE event_type = 'page_performance'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND JSON_EXTRACT(event_data, '$.loadTime') IS NOT NULL
            GROUP BY page
            ORDER BY avg_load_time DESC
        ";
        
        return $this->db->query($sql)->fetchAll() ?: [];
    }
    
    /**
     * Nettoyer les anciennes données (à appeler périodiquement)
     */
    public function cleanup(): Response
    {
        try {
            // Supprimer les données de plus de 90 jours
            $sql = "DELETE FROM view_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $result = $this->db->query($sql);
            
            return new Response([
                'success' => true,
                'message' => 'Analytics cleanup completed'
            ]);
            
        } catch (\Exception $e) {
            return new Response([
                'error' => 'Cleanup failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}