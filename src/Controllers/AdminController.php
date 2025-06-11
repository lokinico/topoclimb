<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Response;
use Symfony\Component\HttpFoundation\Request; // ← CORRECTION : utiliser Symfony Request
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

class AdminController extends BaseController
{


    public function __construct(View $view, Session $session, CsrfManager $csrfManager, Database $db, Auth $auth)
    {
        parent::__construct($view, $session, $csrfManager);
        $this->db = $db;
        $this->auth = $auth;
    }

    /**
     * Dashboard principal d'administration
     */
    public function index(): Response
    {
        $stats = $this->getGeneralStats();
        $recentActivity = $this->getRecentActivity();
        $pendingItems = $this->getPendingItems();

        return $this->render('admin/index.twig', [
            'title' => 'Dashboard Administration',
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'pending_items' => $pendingItems
        ]);
    }

    /**
     * Gestion des utilisateurs
     */
    public function users(Request $request): Response
    {
        $page = (int)$request->query->get('page', 1);
        $search = $request->query->get('search', '');
        $role = $request->query->get('role', '');
        $perPage = 20;

        // Construction de la requête
        $where = [];
        $params = [];

        if ($search) {
            $where[] = "(nom LIKE ? OR prenom LIKE ? OR mail LIKE ? OR username LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if ($role !== '') {
            $where[] = "autorisation = ?";
            $params[] = $role;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Compter le total
        $totalQuery = "SELECT COUNT(*) as total FROM users $whereClause";
        $total = $this->db->fetchOne($totalQuery, $params)['total'];

        // Récupérer les utilisateurs
        $offset = ($page - 1) * $perPage;
        $usersQuery = "SELECT * FROM users $whereClause ORDER BY date_registered DESC LIMIT $perPage OFFSET $offset";
        $users = $this->db->fetchAll($usersQuery, $params);

        $totalPages = ceil($total / $perPage);

        return $this->render('admin/users.twig', [
            'title' => 'Gestion des utilisateurs',
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'role' => $role,
            'roles' => $this->getUserRoles()
        ]);
    }

    /**
     * Détail/Édition d'un utilisateur
     */
    public function userEdit(Request $request): Response
    {
        $id = (int)$request->attributes->get('id');

        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

        if (!$user) {
            $this->session->flash('error', 'Utilisateur non trouvé.');
            return Response::redirect('/admin/users');
        }

        if ($request->isMethod('POST')) {
            return $this->userUpdate($request, $id);
        }

        // Ascensions de l'utilisateur
        $ascents = $this->db->fetchAll(
            "SELECT * FROM user_ascents WHERE user_id = ? ORDER BY ascent_date DESC LIMIT 10",
            [$id]
        );

        return $this->render('admin/user-edit.twig', [
            'title' => 'Éditer utilisateur - ' . $user['prenom'] . ' ' . $user['nom'],
            'user' => $user,
            'ascents' => $ascents,
            'roles' => $this->getUserRoles()
        ]);
    }

    /**
     * Mise à jour d'un utilisateur
     */
    private function userUpdate(Request $request, int $id): Response
    {
        $data = [
            'nom' => $request->request->get('nom'),
            'prenom' => $request->request->get('prenom'),
            'ville' => $request->request->get('ville'),
            'mail' => $request->request->get('mail'),
            'username' => $request->request->get('username'),
            'autorisation' => $request->request->get('autorisation')
        ];

        // Validation
        if (empty($data['nom']) || empty($data['prenom']) || empty($data['mail'])) {
            $this->session->flash('error', 'Tous les champs obligatoires doivent être remplis.');
            return Response::redirect("/admin/users/$id/edit");
        }

        // Vérifier l'unicité email/username
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE (mail = ? OR username = ?) AND id != ?",
            [$data['mail'], $data['username'], $id]
        );

        if ($existing) {
            $this->session->flash('error', 'Email ou nom d\'utilisateur déjà utilisé.');
            return Response::redirect("/admin/users/$id/edit");
        }

        // Nouveau mot de passe si fourni
        $newPassword = $request->request->get('password');
        if ($newPassword) {
            $data['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        try {
            $this->db->update('users', $data, 'id = ?', [$id]);
            $this->session->flash('success', 'Utilisateur mis à jour avec succès.');
        } catch (\Exception $e) {
            $this->session->flash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }

        return Response::redirect("/admin/users/$id/edit");
    }

    /**
     * Bannir/Débannir un utilisateur
     */
    public function userToggleBan(Request $request): Response
    {
        $id = (int)$request->attributes->get('id');

        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }

        $newStatus = $user['autorisation'] == '5' ? '3' : '5'; // Toggle entre banni(5) et utilisateur(3)

        try {
            $this->db->update('users', ['autorisation' => $newStatus], 'id = ?', [$id]);

            $action = $newStatus == '5' ? 'banni' : 'débanni';

            return Response::json([
                'success' => true,
                'message' => "Utilisateur $action avec succès",
                'new_status' => $newStatus
            ]);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    /**
     * Gestion du contenu (régions, secteurs, voies)
     */
    public function content(Request $request): Response
    {
        $type = $request->query->get('type', 'all');
        $search = $request->query->get('search', '');

        $content = [];

        if ($type === 'all' || $type === 'regions') {
            $content['regions'] = $this->getContentStats('climbing_regions', $search);
        }

        if ($type === 'all' || $type === 'sectors') {
            $content['sectors'] = $this->getContentStats('climbing_sectors', $search);
        }

        if ($type === 'all' || $type === 'routes') {
            $content['routes'] = $this->getContentStats('climbing_routes', $search);
        }

        return $this->render('admin/content.twig', [
            'title' => 'Gestion du contenu',
            'content' => $content,
            'type' => $type,
            'search' => $search
        ]);
    }

    /**
     * Gestion des médias
     */
    public function media(Request $request): Response
    {
        $page = (int)$request->query->get('page', 1);
        $type = $request->query->get('type', '');
        $perPage = 24;

        $where = [];
        $params = [];

        if ($type) {
            $where[] = "media_type = ?";
            $params[] = $type;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total et médias
        $total = $this->db->fetchOne("SELECT COUNT(*) as total FROM climbing_media $whereClause", $params)['total'];
        $offset = ($page - 1) * $perPage;

        $media = $this->db->fetchAll(
            "SELECT m.*, u.username as uploader_name 
             FROM climbing_media m 
             LEFT JOIN users u ON m.created_by = u.id 
             $whereClause 
             ORDER BY m.created_at DESC 
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $totalPages = ceil($total / $perPage);

        return $this->render('admin/media.twig', [
            'title' => 'Gestion des médias',
            'media' => $media,
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'type' => $type
        ]);
    }

    /**
     * Supprimer un média
     */
    public function mediaDelete(Request $request): Response
    {
        $id = (int)$request->attributes->get('id');

        $media = $this->db->fetchOne("SELECT * FROM climbing_media WHERE id = ?", [$id]);

        if (!$media) {
            return Response::json(['success' => false, 'message' => 'Média non trouvé']);
        }

        try {
            // Supprimer le fichier physique
            $filePath = 'public/' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Supprimer de la base
            $this->db->delete('climbing_media_relationships', 'media_id = ?', [$id]);
            $this->db->delete('climbing_media', 'id = ?', [$id]);

            return Response::json(['success' => true, 'message' => 'Média supprimé avec succès']);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    /**
     * Configuration du site
     */
    public function settings(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateSettings($request);
        }

        $settings = $this->getCurrentSettings();

        return $this->render('admin/settings.twig', [
            'title' => 'Configuration',
            'settings' => $settings
        ]);
    }

    /**
     * Logs système
     */
    public function logs(Request $request): Response
    {
        $page = (int)$request->query->get('page', 1);
        $level = $request->query->get('level', '');
        $perPage = 50;

        // Lire les logs (adapter selon votre système de logs)
        $logs = $this->getSystemLogs($page, $perPage, $level);

        return $this->render('admin/logs.twig', [
            'title' => 'Logs système',
            'logs' => $logs['data'],
            'total' => $logs['total'],
            'page' => $page,
            'total_pages' => $logs['pages'],
            'level' => $level
        ]);
    }

    /**
     * Rapports et analytics
     */
    public function reports(Request $request): Response
    {
        $period = $request->query->get('period', '30'); // 7, 30, 90 jours

        $reports = [
            'users' => $this->getUsersReport($period),
            'content' => $this->getContentReport($period),
            'activity' => $this->getActivityReport($period)
        ];

        return $this->render('admin/reports.twig', [
            'title' => 'Rapports & Analytics',
            'reports' => $reports,
            'period' => $period
        ]);
    }

    // ===== MÉTHODES PRIVÉES HELPER =====

    private function getGeneralStats(): array
    {
        return [
            'total_users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
            'total_regions' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_regions")['count'],
            'total_sectors' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_sectors")['count'],
            'total_routes' => $this->db->fetchOne("SELECT COUNT(*) as count FROM climbing_routes")['count'],
            'total_ascents' => $this->db->fetchOne("SELECT COUNT(*) as count FROM user_ascents")['count'],
            'pending_users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE autorisation = '4'")['count'],
            'banned_users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE autorisation = '5'")['count'],
            'today_registrations' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(date_registered) = CURDATE()")['count']
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'new_users' => $this->db->fetchAll(
                "SELECT nom, prenom, date_registered FROM users ORDER BY date_registered DESC LIMIT 5"
            ),
            'new_ascents' => $this->db->fetchAll(
                "SELECT ua.*, u.username, ua.route_name 
                 FROM user_ascents ua 
                 JOIN users u ON ua.user_id = u.id 
                 ORDER BY ua.created_at DESC LIMIT 5"
            )
        ];
    }

    private function getPendingItems(): array
    {
        return [
            'pending_users' => $this->db->fetchAll(
                "SELECT * FROM users WHERE autorisation = '4' ORDER BY date_registered DESC LIMIT 10"
            )
        ];
    }

    private function getUserRoles(): array
    {
        return [
            '0' => 'Super Admin',
            '1' => 'Modérateur/Éditeur',
            '2' => 'Utilisateur Accepté',
            '3' => 'Utilisateur Standard',
            '4' => 'Nouveau Membre',
            '5' => 'Banni'
        ];
    }

    private function getContentStats(string $table, string $search = ''): array
    {
        $where = $search ? "WHERE name LIKE ?" : "";
        $params = $search ? ["%$search%"] : [];

        return [
            'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM $table $where", $params)['count'],
            'recent' => $this->db->fetchAll("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 10", $params)
        ];
    }

    private function getCurrentSettings(): array
    {
        // Récupérer depuis .env ou une table settings
        return [
            'app_name' => $_ENV['APP_NAME'] ?? 'TopoclimbCH',
            'app_url' => $_ENV['APP_URL'] ?? '',
            'maintenance_mode' => false,
            'registration_enabled' => true,
            'max_upload_size' => '5MB'
        ];
    }

    private function updateSettings(Request $request): Response
    {
        // Logique de mise à jour des paramètres
        $this->session->flash('success', 'Configuration mise à jour avec succès.');
        return Response::redirect('/admin/settings');
    }

    private function getSystemLogs(int $page, int $perPage, string $level): array
    {
        // Adapter selon votre système de logs
        // Exemple basique
        return [
            'data' => [],
            'total' => 0,
            'pages' => 0
        ];
    }

    private function getUsersReport(string $period): array
    {
        $days = (int)$period;
        return [
            'new_registrations' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE date_registered >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )['count'],
            'role_distribution' => $this->db->fetchAll(
                "SELECT autorisation, COUNT(*) as count FROM users GROUP BY autorisation"
            )
        ];
    }

    private function getContentReport(string $period): array
    {
        $days = (int)$period;
        return [
            'new_regions' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_regions WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )['count'] ?? 0,
            'new_sectors' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_sectors WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )['count'] ?? 0,
            'new_routes' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )['count'] ?? 0
        ];
    }

    private function getActivityReport(string $period): array
    {
        $days = (int)$period;
        return [
            'new_ascents' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM user_ascents WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )['count']
        ];
    }
}
