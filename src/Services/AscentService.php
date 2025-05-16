<?php
// src/Services/AscentService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\UserAscent;

class AscentService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les statistiques d'ascension d'un utilisateur
     */
    public function getUserStats(int $userId): array
    {
        $stats = [
            'total' => 0,
            'flash' => 0,
            'onsight' => 0,
            'redpoint' => 0,
            'favorite' => 0,
            'hardest' => null
        ];

        // Total
        $totalQuery = "SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ?";
        $result = $this->db->query($totalQuery, [$userId]);
        $stats['total'] = $result[0]['count'] ?? 0;

        // Par type d'ascension
        $typesQuery = "SELECT ascent_type, COUNT(*) as count FROM user_ascents WHERE user_id = ? GROUP BY ascent_type";
        $typesResult = $this->db->query($typesQuery, [$userId]);

        foreach ($typesResult as $row) {
            $type = strtolower($row['ascent_type']);
            if (isset($stats[$type])) {
                $stats[$type] = $row['count'];
            }
        }

        // Favorites
        $favoriteQuery = "SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ? AND favorite = 1";
        $favoriteResult = $this->db->query($favoriteQuery, [$userId]);
        $stats['favorite'] = $favoriteResult[0]['count'] ?? 0;

        // Voie la plus difficile
        $hardestQuery = "
            SELECT ua.*, r.name as route_name
            FROM user_ascents ua
            LEFT JOIN climbing_routes r ON ua.route_id = r.id
            WHERE ua.user_id = ?
            ORDER BY ua.difficulty DESC
            LIMIT 1
        ";
        $hardestResult = $this->db->query($hardestQuery, [$userId]);
        $stats['hardest'] = $hardestResult[0] ?? null;

        return $stats;
    }

    /**
     * Récupère les ascensions récentes d'un utilisateur
     */
    public function getUserRecentAscents(int $userId, int $limit = 5): array
    {
        $query = "
            SELECT ua.*, r.name as route_name, r.difficulty as route_difficulty, s.name as sector_name
            FROM user_ascents ua
            LEFT JOIN climbing_routes r ON ua.route_id = r.id
            LEFT JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE ua.user_id = ?
            ORDER BY ua.ascent_date DESC
            LIMIT ?
        ";

        return $this->db->query($query, [$userId, $limit]);
    }
}
