<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

/**
 * Service pour gérer Google Analytics et les événements de tracking
 */
class AnalyticsService
{
    private Database $db;
    private array $config;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->config = $this->loadAnalyticsConfig();
    }

    /**
     * Charge la configuration analytics depuis la base de données
     */
    private function loadAnalyticsConfig(): array
    {
        $config = [
            'google_analytics_id' => $_ENV['GOOGLE_ANALYTICS_ID'] ?? null,
            'enabled' => false
        ];

        // Tenter de charger depuis les paramètres de l'application si disponible
        try {
            $settings = $this->db->fetchOne("SELECT google_analytics_id FROM app_settings WHERE id = 1");
            if ($settings && !empty($settings['google_analytics_id'])) {
                $config['google_analytics_id'] = $settings['google_analytics_id'];
                $config['enabled'] = true;
            }
        } catch (\Exception $e) {
            // Si la table n'existe pas, utiliser les variables d'environnement
            if (!empty($config['google_analytics_id'])) {
                $config['enabled'] = true;
            }
        }

        return $config;
    }

    /**
     * Vérifie si Google Analytics est activé
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] && !empty($this->config['google_analytics_id']);
    }

    /**
     * Récupère l'ID de tracking Google Analytics
     */
    public function getTrackingId(): ?string
    {
        return $this->config['google_analytics_id'] ?? null;
    }

    /**
     * Génère le code JavaScript pour un événement personnalisé
     */
    public function generateEventScript(string $eventName, array $parameters = []): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $paramsJson = json_encode($parameters);
        
        return "
        <script>
        if (typeof gtag !== 'undefined') {
            gtag('event', '{$eventName}', {$paramsJson});
        }
        </script>
        ";
    }

    /**
     * Génère les événements de tracking pour l'escalade
     */
    public function trackClimbingEvent(string $action, array $data = []): string
    {
        $eventData = [
            'event_category' => 'climbing',
            'event_label' => $data['label'] ?? null,
            'value' => $data['value'] ?? null
        ];

        // Ajouter des données spécifiques à l'escalade
        if (isset($data['region'])) {
            $eventData['custom_dimension_2'] = $data['region'];
        }
        if (isset($data['difficulty'])) {
            $eventData['difficulty'] = $data['difficulty'];
        }
        if (isset($data['route_style'])) {
            $eventData['route_style'] = $data['route_style'];
        }

        return $this->generateEventScript($action, array_filter($eventData));
    }

    /**
     * Track une vue de région
     */
    public function trackRegionView(int $regionId, string $regionName): string
    {
        return $this->trackClimbingEvent('region_view', [
            'label' => $regionName,
            'value' => $regionId,
            'region' => $regionName
        ]);
    }

    /**
     * Track une vue de secteur
     */
    public function trackSectorView(int $sectorId, string $sectorName, ?string $regionName = null): string
    {
        return $this->trackClimbingEvent('sector_view', [
            'label' => $sectorName,
            'value' => $sectorId,
            'region' => $regionName
        ]);
    }

    /**
     * Track une vue de voie
     */
    public function trackRouteView(int $routeId, string $routeName, ?string $difficulty = null, ?string $style = null): string
    {
        return $this->trackClimbingEvent('route_view', [
            'label' => $routeName,
            'value' => $routeId,
            'difficulty' => $difficulty,
            'route_style' => $style
        ]);
    }

    /**
     * Track une recherche
     */
    public function trackSearch(string $query, string $type = 'general', int $resultCount = 0): string
    {
        return $this->trackClimbingEvent('search', [
            'label' => $query,
            'value' => $resultCount,
            'search_type' => $type
        ]);
    }

    /**
     * Track un téléchargement de fichier
     */
    public function trackDownload(string $fileName, string $fileType): string
    {
        return $this->generateEventScript('file_download', [
            'event_category' => 'engagement',
            'event_label' => $fileName,
            'file_type' => $fileType
        ]);
    }

    /**
     * Track une interaction utilisateur
     */
    public function trackUserInteraction(string $action, ?string $element = null): string
    {
        return $this->generateEventScript('user_interaction', [
            'event_category' => 'engagement',
            'event_label' => $element,
            'interaction_type' => $action
        ]);
    }

    /**
     * Track les statistiques d'utilisation pour les rapports
     */
    public function logAnalyticsEvent(string $eventType, array $data = []): bool
    {
        try {
            $eventData = [
                'event_type' => $eventType,
                'event_data' => json_encode($data),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Vérifier si la table analytics_events existe
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'analytics_events'");
            
            if ($tableExists) {
                return (bool)$this->db->insert('analytics_events', $eventData);
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Analytics logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère les métriques d'utilisation
     */
    public function getUsageMetrics(int $days = 30): array
    {
        try {
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $metrics = [
                'page_views' => 0,
                'unique_visitors' => 0,
                'top_regions' => [],
                'top_routes' => [],
                'search_queries' => []
            ];

            // Vérifier si la table analytics_events existe
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'analytics_events'");
            
            if (!$tableExists) {
                return $metrics;
            }

            // Compter les vues de pages
            $pageViews = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM analytics_events 
                 WHERE event_type IN ('region_view', 'sector_view', 'route_view') 
                 AND created_at >= ?",
                [$since]
            );
            $metrics['page_views'] = (int)($pageViews['count'] ?? 0);

            // Compter les visiteurs uniques (basé sur IP)
            $uniqueVisitors = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT ip_address) as count FROM analytics_events 
                 WHERE created_at >= ?",
                [$since]
            );
            $metrics['unique_visitors'] = (int)($uniqueVisitors['count'] ?? 0);

            // Top régions
            $topRegions = $this->db->fetchAll(
                "SELECT JSON_EXTRACT(event_data, '$.region') as region, COUNT(*) as views
                 FROM analytics_events 
                 WHERE event_type = 'region_view' AND created_at >= ?
                 GROUP BY region 
                 ORDER BY views DESC 
                 LIMIT 10",
                [$since]
            );
            $metrics['top_regions'] = $topRegions;

            // Requêtes de recherche populaires
            $searchQueries = $this->db->fetchAll(
                "SELECT JSON_EXTRACT(event_data, '$.label') as query, COUNT(*) as count
                 FROM analytics_events 
                 WHERE event_type = 'search' AND created_at >= ?
                 GROUP BY query 
                 ORDER BY count DESC 
                 LIMIT 10",
                [$since]
            );
            $metrics['search_queries'] = $searchQueries;

            return $metrics;
        } catch (\Exception $e) {
            error_log("Analytics metrics failed: " . $e->getMessage());
            return [
                'page_views' => 0,
                'unique_visitors' => 0,
                'top_regions' => [],
                'top_routes' => [],
                'search_queries' => []
            ];
        }
    }

    /**
     * Génère le script de consentement aux cookies pour RGPD
     */
    public function generateCookieConsentScript(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return "
        <script>
        // Gestion du consentement aux cookies pour RGPD
        function grantAnalyticsConsent() {
            gtag('consent', 'update', {
                'analytics_storage': 'granted'
            });
            localStorage.setItem('analytics_consent', 'granted');
        }
        
        function denyAnalyticsConsent() {
            gtag('consent', 'update', {
                'analytics_storage': 'denied'
            });
            localStorage.setItem('analytics_consent', 'denied');
        }
        
        // Vérifier le consentement existant
        const savedConsent = localStorage.getItem('analytics_consent');
        if (savedConsent === 'granted') {
            grantAnalyticsConsent();
        } else if (savedConsent === 'denied') {
            denyAnalyticsConsent();
        } else {
            // Paramètres par défaut (consentement requis)
            gtag('consent', 'default', {
                'analytics_storage': 'denied'
            });
        }
        </script>
        ";
    }
}