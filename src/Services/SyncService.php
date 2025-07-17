<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;
use TopoclimbCH\Models\Site;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Route;
use TopoclimbCH\Models\Alert;

/**
 * Service de synchronisation pour le mode hors-ligne
 * Gère la synchronisation des données entre le client et le serveur
 */
class SyncService
{
    private Database $db;
    private const SYNC_VERSION = '1.0.0';
    private const MAX_SYNC_BATCH_SIZE = 100;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtient les données essentielles pour le mode hors-ligne
     */
    public function getOfflineData(int $userId = null): array
    {
        $timestamp = time();
        
        return [
            'version' => self::SYNC_VERSION,
            'timestamp' => $timestamp,
            'user_id' => $userId,
            'data' => [
                'regions' => $this->getRegionsData(),
                'sites' => $this->getSitesData(),
                'sectors' => $this->getSectorsData(),
                'routes' => $this->getRoutesData(),
                'alerts' => $this->getAlertsData(),
                'user_data' => $userId ? $this->getUserData($userId) : null
            ],
            'metadata' => [
                'total_regions' => count($this->getRegionsData()),
                'total_sites' => count($this->getSitesData()),
                'total_sectors' => count($this->getSectorsData()),
                'total_routes' => count($this->getRoutesData()),
                'total_alerts' => count($this->getAlertsData())
            ]
        ];
    }

    /**
     * Synchronise les données modifiées depuis un timestamp
     */
    public function getDeltaSync(int $lastSync, int $userId = null): array
    {
        $lastSyncDate = date('Y-m-d H:i:s', $lastSync);
        
        return [
            'version' => self::SYNC_VERSION,
            'timestamp' => time(),
            'last_sync' => $lastSync,
            'user_id' => $userId,
            'changes' => [
                'regions' => $this->getRegionsChanges($lastSyncDate),
                'sites' => $this->getSitesChanges($lastSyncDate),
                'sectors' => $this->getSectorsChanges($lastSyncDate),
                'routes' => $this->getRoutesChanges($lastSyncDate),
                'alerts' => $this->getAlertsChanges($lastSyncDate),
                'user_data' => $userId ? $this->getUserDataChanges($userId, $lastSyncDate) : null
            ]
        ];
    }

    /**
     * Synchronise les modifications locales vers le serveur
     */
    public function syncLocalChanges(array $changes, int $userId): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'conflicts' => []
        ];

        foreach ($changes as $change) {
            try {
                $result = $this->processLocalChange($change, $userId);
                
                if ($result['success']) {
                    $results['success'][] = $result;
                } else {
                    $results['errors'][] = $result;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'id' => $change['id'] ?? 'unknown',
                    'type' => $change['type'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Obtient les données des régions
     */
    private function getRegionsData(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, country_id, latitude, longitude, 
                   image_url, created_at, updated_at
            FROM climbing_regions 
            WHERE active = 1
            ORDER BY name
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les données des sites
     */
    private function getSitesData(): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id, s.name, s.description, s.region_id, s.latitude, s.longitude,
                   s.elevation, s.access_info, s.created_at, s.updated_at,
                   r.name as region_name
            FROM climbing_sites s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            WHERE s.active = 1
            ORDER BY s.name
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les données des secteurs
     */
    private function getSectorsData(): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id, s.name, s.description, s.region_id, s.site_id, 
                   s.latitude, s.longitude, s.elevation, s.orientation,
                   s.created_at, s.updated_at,
                   r.name as region_name, st.name as site_name
            FROM climbing_sectors s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE s.active = 1
            ORDER BY s.name
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les données des voies (limitées pour le cache)
     */
    private function getRoutesData(): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name, r.sector_id, r.grade, r.type, r.length,
                   r.description, r.created_at, r.updated_at,
                   s.name as sector_name, st.name as site_name, reg.name as region_name
            FROM climbing_routes r
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            LEFT JOIN climbing_regions reg ON s.region_id = reg.id
            WHERE r.active = 1
            ORDER BY r.name
            LIMIT 1000
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les données des alertes actives
     */
    private function getAlertsData(): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.title, a.description, a.severity, a.alert_type_id,
                   a.region_id, a.site_id, a.sector_id, a.start_date, a.end_date,
                   a.created_at, a.updated_at,
                   t.name as alert_type_name, t.icon as alert_type_icon,
                   r.name as region_name, s.name as site_name, sec.name as sector_name
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types t ON a.alert_type_id = t.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            WHERE a.active = 1 AND (a.end_date IS NULL OR a.end_date >= DATE('now'))
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les données utilisateur
     */
    private function getUserData(int $userId): array
    {
        $userData = [
            'profile' => $this->getUserProfile($userId),
            'ascents' => $this->getUserAscents($userId),
            'favorites' => $this->getUserFavorites($userId),
            'settings' => $this->getUserSettings($userId)
        ];

        return $userData;
    }

    /**
     * Obtient le profil utilisateur
     */
    private function getUserProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, nom, prenom, ville, mail, autorisation, date_registered
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtient les ascensions utilisateur
     */
    private function getUserAscents(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.route_id, a.date, a.style, a.attempts, a.notes,
                   r.name as route_name, s.name as sector_name, st.name as site_name
            FROM user_ascents a
            LEFT JOIN climbing_routes r ON a.route_id = r.id
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE a.user_id = ?
            ORDER BY a.date DESC
            LIMIT 500
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les favoris utilisateur
     */
    private function getUserFavorites(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT f.route_id, f.created_at,
                   r.name as route_name, s.name as sector_name, st.name as site_name
            FROM user_favorites f
            LEFT JOIN climbing_routes r ON f.route_id = r.id
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les paramètres utilisateur
     */
    private function getUserSettings(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value
            FROM user_settings 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $settings = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }

    /**
     * Obtient les changements de régions depuis une date
     */
    private function getRegionsChanges(string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, country_id, latitude, longitude, 
                   image_url, created_at, updated_at, 'modified' as change_type
            FROM climbing_regions 
            WHERE updated_at > ? OR created_at > ?
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements de sites depuis une date
     */
    private function getSitesChanges(string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id, s.name, s.description, s.region_id, s.latitude, s.longitude,
                   s.elevation, s.access_info, s.created_at, s.updated_at,
                   r.name as region_name, 'modified' as change_type
            FROM climbing_sites s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            WHERE s.updated_at > ? OR s.created_at > ?
            ORDER BY s.updated_at DESC
        ");
        $stmt->execute([$lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements de secteurs depuis une date
     */
    private function getSectorsChanges(string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id, s.name, s.description, s.region_id, s.site_id, 
                   s.latitude, s.longitude, s.elevation, s.orientation,
                   s.created_at, s.updated_at,
                   r.name as region_name, st.name as site_name, 'modified' as change_type
            FROM climbing_sectors s
            LEFT JOIN climbing_regions r ON s.region_id = r.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE s.updated_at > ? OR s.created_at > ?
            ORDER BY s.updated_at DESC
        ");
        $stmt->execute([$lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements de voies depuis une date
     */
    private function getRoutesChanges(string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name, r.sector_id, r.grade, r.type, r.length,
                   r.description, r.created_at, r.updated_at,
                   s.name as sector_name, st.name as site_name, reg.name as region_name,
                   'modified' as change_type
            FROM climbing_routes r
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            LEFT JOIN climbing_regions reg ON s.region_id = reg.id
            WHERE r.updated_at > ? OR r.created_at > ?
            ORDER BY r.updated_at DESC
            LIMIT 500
        ");
        $stmt->execute([$lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements d'alertes depuis une date
     */
    private function getAlertsChanges(string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.title, a.description, a.severity, a.alert_type_id,
                   a.region_id, a.site_id, a.sector_id, a.start_date, a.end_date,
                   a.created_at, a.updated_at,
                   t.name as alert_type_name, t.icon as alert_type_icon,
                   r.name as region_name, s.name as site_name, sec.name as sector_name,
                   'modified' as change_type
            FROM climbing_alerts a
            LEFT JOIN climbing_alert_types t ON a.alert_type_id = t.id
            LEFT JOIN climbing_regions r ON a.region_id = r.id
            LEFT JOIN climbing_sites s ON a.site_id = s.id
            LEFT JOIN climbing_sectors sec ON a.sector_id = sec.id
            WHERE a.updated_at > ? OR a.created_at > ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements des données utilisateur
     */
    private function getUserDataChanges(int $userId, string $lastSync): array
    {
        return [
            'ascents' => $this->getUserAscentsChanges($userId, $lastSync),
            'favorites' => $this->getUserFavoritesChanges($userId, $lastSync),
            'settings' => $this->getUserSettingsChanges($userId, $lastSync)
        ];
    }

    /**
     * Obtient les changements d'ascensions utilisateur
     */
    private function getUserAscentsChanges(int $userId, string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.route_id, a.date, a.style, a.attempts, a.notes,
                   a.created_at, a.updated_at,
                   r.name as route_name, s.name as sector_name, st.name as site_name,
                   'modified' as change_type
            FROM user_ascents a
            LEFT JOIN climbing_routes r ON a.route_id = r.id
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE a.user_id = ? AND (a.updated_at > ? OR a.created_at > ?)
            ORDER BY a.date DESC
        ");
        $stmt->execute([$userId, $lastSync, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements de favoris utilisateur
     */
    private function getUserFavoritesChanges(int $userId, string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT f.route_id, f.created_at,
                   r.name as route_name, s.name as sector_name, st.name as site_name,
                   'modified' as change_type
            FROM user_favorites f
            LEFT JOIN climbing_routes r ON f.route_id = r.id
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            LEFT JOIN climbing_sites st ON s.site_id = st.id
            WHERE f.user_id = ? AND f.created_at > ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtient les changements de paramètres utilisateur
     */
    private function getUserSettingsChanges(int $userId, string $lastSync): array
    {
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value, updated_at, 'modified' as change_type
            FROM user_settings 
            WHERE user_id = ? AND updated_at > ?
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$userId, $lastSync]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Traite un changement local
     */
    private function processLocalChange(array $change, int $userId): array
    {
        $type = $change['type'] ?? 'unknown';
        $action = $change['action'] ?? 'create'; // create, update, delete
        
        switch ($type) {
            case 'ascent':
                return $this->processAscentChange($change, $userId);
            case 'favorite':
                return $this->processFavoriteChange($change, $userId);
            case 'comment':
                return $this->processCommentChange($change, $userId);
            case 'setting':
                return $this->processSettingChange($change, $userId);
            default:
                throw new \Exception("Type de changement non supporté: {$type}");
        }
    }

    /**
     * Traite un changement d'ascension
     */
    private function processAscentChange(array $change, int $userId): array
    {
        $data = $change['data'] ?? [];
        $action = $change['action'] ?? 'create';
        
        if ($action === 'create') {
            $stmt = $this->db->prepare("
                INSERT INTO user_ascents (user_id, route_id, date, style, attempts, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            
            $result = $stmt->execute([
                $userId,
                $data['route_id'],
                $data['date'],
                $data['style'] ?? 'unknown',
                $data['attempts'] ?? 1,
                $data['notes'] ?? ''
            ]);
            
            return [
                'success' => $result,
                'id' => $this->db->lastInsertId(),
                'type' => 'ascent',
                'action' => 'create'
            ];
        }
        
        // TODO: Implémenter update et delete
        return ['success' => false, 'error' => 'Action non implémentée'];
    }

    /**
     * Traite un changement de favori
     */
    private function processFavoriteChange(array $change, int $userId): array
    {
        $data = $change['data'] ?? [];
        $action = $change['action'] ?? 'create';
        
        if ($action === 'create') {
            $stmt = $this->db->prepare("
                INSERT OR IGNORE INTO user_favorites (user_id, route_id, created_at)
                VALUES (?, ?, datetime('now'))
            ");
            
            $result = $stmt->execute([$userId, $data['route_id']]);
            
            return [
                'success' => $result,
                'type' => 'favorite',
                'action' => 'create'
            ];
        } elseif ($action === 'delete') {
            $stmt = $this->db->prepare("
                DELETE FROM user_favorites 
                WHERE user_id = ? AND route_id = ?
            ");
            
            $result = $stmt->execute([$userId, $data['route_id']]);
            
            return [
                'success' => $result,
                'type' => 'favorite',
                'action' => 'delete'
            ];
        }
        
        return ['success' => false, 'error' => 'Action non implémentée'];
    }

    /**
     * Traite un changement de commentaire
     */
    private function processCommentChange(array $change, int $userId): array
    {
        // TODO: Implémenter les commentaires
        return ['success' => false, 'error' => 'Commentaires non implémentés'];
    }

    /**
     * Traite un changement de paramètre
     */
    private function processSettingChange(array $change, int $userId): array
    {
        $data = $change['data'] ?? [];
        
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO user_settings (user_id, setting_key, setting_value, updated_at)
            VALUES (?, ?, ?, datetime('now'))
        ");
        
        $result = $stmt->execute([
            $userId,
            $data['key'],
            $data['value']
        ]);
        
        return [
            'success' => $result,
            'type' => 'setting',
            'action' => 'upsert'
        ];
    }

    /**
     * Nettoie les données de cache anciennes
     */
    public function cleanupOldData(int $maxAgeSeconds = 2592000): bool // 30 jours
    {
        $cutoffDate = date('Y-m-d H:i:s', time() - $maxAgeSeconds);
        
        try {
            // Nettoyer les données de cache expirées
            $stmt = $this->db->prepare("
                DELETE FROM sync_cache 
                WHERE created_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erreur nettoyage cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtient les statistiques de synchronisation
     */
    public function getSyncStats(): array
    {
        $stats = [];
        
        // Nombre total d'éléments
        $tables = ['climbing_regions', 'climbing_sites', 'climbing_sectors', 'climbing_routes', 'climbing_alerts'];
        
        foreach ($tables as $table) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$table}");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stats[$table] = $result['count'];
        }
        
        return $stats;
    }
}