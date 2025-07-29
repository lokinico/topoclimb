<?php
// src/Services/UserService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;
use TopoclimbCH\Models\UserAscent;

class UserService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère un utilisateur par son ID
     */
    public function getUser(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Récupère un utilisateur par son nom d'utilisateur
     */
    public function getUserByUsername(string $username): ?User
    {
        return User::where(['username' => $username])[0] ?? null;
    }

    /**
     * Récupère un utilisateur par son email
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where(['email' => $email])[0] ?? null;
    }

    /**
     * Met à jour le profil d'un utilisateur
     */
    public function updateProfile(User $user, array $data): bool
    {
        $user->fill($data);
        return $user->save();
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public function changePassword(User $user, string $newPassword): bool
    {
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return $user->save();
    }

    /**
     * Récupère les ascensions d'un utilisateur
     */
    public function getUserAscents(int $userId, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $conditions = ['user_id' => $userId];

        // Ajouter les filtres supplémentaires s'ils existent
        if (!empty($filters)) {
            $conditions = array_merge($conditions, $filters);
        }

        // Construire la requête SQL avec pagination
        $query = "SELECT ua.* FROM user_ascents ua WHERE ua.user_id = ?";
        $params = [$userId];

        // Ajouter d'autres conditions si nécessaire
        if (!empty($filters['route_id'])) {
            $query .= " AND ua.route_id = ?";
            $params[] = $filters['route_id'];
        }

        if (!empty($filters['ascent_type'])) {
            $query .= " AND ua.ascent_type = ?";
            $params[] = $filters['ascent_type'];
        }

        if (!empty($filters['favorite'])) {
            $query .= " AND ua.favorite = 1";
        }

        // Ajouter l'ordre et la pagination
        $query .= " ORDER BY ua.ascent_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($query, $params);
    }

    /**
     * Compte le nombre total d'ascensions d'un utilisateur
     */
    public function countUserAscents(int $userId, array $filters = []): int
    {
        $query = "SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ?";
        $params = [$userId];

        // Ajouter d'autres conditions si nécessaire
        if (!empty($filters['route_id'])) {
            $query .= " AND route_id = ?";
            $params[] = $filters['route_id'];
        }

        if (!empty($filters['ascent_type'])) {
            $query .= " AND ascent_type = ?";
            $params[] = $filters['ascent_type'];
        }

        if (!empty($filters['favorite'])) {
            $query .= " AND favorite = 1";
        }

        $result = $this->db->query($query, $params);
        return $result[0]['count'] ?? 0;
    }

    /**
     * Obtient des statistiques pour un utilisateur
     */
    public function getUserStats(int $userId): array
    {
        $stats = [
            'total_ascents' => 0,
            'favorite_routes' => 0,
            'hardest_route' => null,
            'most_common_style' => null
        ];

        // Nombre total d'ascensions
        $stats['total_ascents'] = $this->countUserAscents($userId);

        // Nombre de voies favorites
        $favoriteQuery = "SELECT COUNT(*) as count FROM user_ascents WHERE user_id = ? AND favorite = 1";
        $favoriteResult = $this->db->query($favoriteQuery, [$userId]);
        $stats['favorite_routes'] = $favoriteResult[0]['count'] ?? 0;

        // Voie la plus difficile
        $hardestQuery = "SELECT * FROM user_ascents WHERE user_id = ? ORDER BY difficulty DESC LIMIT 1";
        $hardestResult = $this->db->query($hardestQuery, [$userId]);
        $stats['hardest_route'] = $hardestResult[0] ?? null;

        // Style le plus pratiqué
        $styleQuery = "
            SELECT style, COUNT(*) as count 
            FROM user_ascents 
            WHERE user_id = ? AND style IS NOT NULL 
            GROUP BY style 
            ORDER BY count DESC 
            LIMIT 1
        ";
        $styleResult = $this->db->query($styleQuery, [$userId]);
        $stats['most_common_style'] = $styleResult[0]['style'] ?? null;

        return $stats;
    }
}
