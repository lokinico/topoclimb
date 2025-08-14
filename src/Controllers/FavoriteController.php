<?php

namespace TopoclimbCH\Controllers;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Security\CsrfManager;

class FavoriteController extends BaseController
{
    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        ?Auth $auth = null
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
    }

    /**
     * API: Basculer l'état favori d'une entité
     */
    public function apiToggle(Request $request): Response
    {
        $this->requireAuth();

        try {
            $user = $this->auth->user();
            $userId = $user->id;
            $entityType = $request->request->get('entity_type');
            $entityId = (int)$request->request->get('entity_id');

            // Validation
            $validTypes = ['sector', 'route', 'site', 'region'];
            if (!in_array($entityType, $validTypes)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Type d\'entité invalide'
                ], 400);
            }

            if ($entityId <= 0) {
                return Response::json([
                    'success' => false,
                    'error' => 'ID d\'entité invalide'
                ], 400);
            }

            // Vérifier si l'entité existe
            if (!$this->entityExists($entityType, $entityId)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Entité introuvable'
                ], 404);
            }

            // Vérifier si déjà en favoris
            $existing = $this->db->fetchOne(
                "SELECT id FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id = ?",
                [$userId, $entityType, $entityId]
            );

            if ($existing) {
                // Retirer des favoris
                $this->db->query(
                    "DELETE FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id = ?",
                    [$userId, $entityType, $entityId]
                );

                return Response::json([
                    'success' => true,
                    'action' => 'removed',
                    'is_favorite' => false,
                    'message' => 'Retiré des favoris'
                ]);
            } else {
                // Ajouter aux favoris
                $this->db->query(
                    "INSERT INTO user_favorites (user_id, entity_type, entity_id) VALUES (?, ?, ?)",
                    [$userId, $entityType, $entityId]
                );

                return Response::json([
                    'success' => true,
                    'action' => 'added',
                    'is_favorite' => true,
                    'message' => 'Ajouté aux favoris'
                ]);
            }

        } catch (\Exception $e) {
            error_log("FavoriteController::apiToggle Error: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur serveur'
            ], 500);
        }
    }

    /**
     * API: Obtenir l'état des favoris d'un utilisateur
     */
    public function apiStatus(Request $request): Response
    {
        $this->requireAuth();

        try {
            $user = $this->auth->user();
            $userId = $user->id;
            $entityType = $request->query->get('entity_type');
            $entityIds = $request->query->get('entity_ids'); // Format: "1,2,3"

            // Validation
            $validTypes = ['sector', 'route', 'site', 'region'];
            if (!in_array($entityType, $validTypes)) {
                return Response::json([
                    'success' => false,
                    'error' => 'Type d\'entité invalide'
                ], 400);
            }

            // Parser les IDs
            $ids = [];
            if ($entityIds) {
                $ids = array_filter(array_map('intval', explode(',', $entityIds)));
            }

            if (empty($ids)) {
                return Response::json([
                    'success' => true,
                    'favorites' => []
                ]);
            }

            // Récupérer les favoris
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $favorites = $this->db->fetchAll(
                "SELECT entity_id FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id IN ($placeholders)",
                array_merge([$userId, $entityType], $ids)
            );

            $favoriteIds = array_column($favorites, 'entity_id');

            return Response::json([
                'success' => true,
                'favorites' => array_map('intval', $favoriteIds)
            ]);

        } catch (\Exception $e) {
            error_log("FavoriteController::apiStatus Error: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'Erreur serveur'
            ], 500);
        }
    }

    /**
     * Page des favoris de l'utilisateur
     */
    public function index(Request $request): Response
    {
        $this->requireAuth();

        try {
            $user = $this->auth->user();
            $userId = $user->id;
            $type = $request->query->get('type', 'all');
            
            // Construire la requête selon le type
            $whereClause = "f.user_id = ?";
            $params = [$userId];
            
            if ($type !== 'all') {
                $validTypes = ['sector', 'route', 'site', 'region'];
                if (in_array($type, $validTypes)) {
                    $whereClause .= " AND f.entity_type = ?";
                    $params[] = $type;
                }
            }

            // Récupérer les favoris avec les détails
            $favorites = $this->db->fetchAll("
                SELECT f.*, 
                       CASE 
                           WHEN f.entity_type = 'sector' THEN s.name
                           WHEN f.entity_type = 'route' THEN r.name
                           WHEN f.entity_type = 'site' THEN si.name
                           WHEN f.entity_type = 'region' THEN re.name
                       END as entity_name,
                       CASE 
                           WHEN f.entity_type = 'sector' THEN re2.name
                           WHEN f.entity_type = 'route' THEN re3.name
                           WHEN f.entity_type = 'site' THEN re4.name
                           WHEN f.entity_type = 'region' THEN NULL
                       END as region_name
                FROM user_favorites f
                LEFT JOIN climbing_sectors s ON f.entity_type = 'sector' AND f.entity_id = s.id
                LEFT JOIN climbing_routes r ON f.entity_type = 'route' AND f.entity_id = r.id
                LEFT JOIN climbing_sites si ON f.entity_type = 'site' AND f.entity_id = si.id
                LEFT JOIN climbing_regions re ON f.entity_type = 'region' AND f.entity_id = re.id
                LEFT JOIN climbing_regions re2 ON f.entity_type = 'sector' AND s.region_id = re2.id
                LEFT JOIN climbing_sectors s2 ON f.entity_type = 'route' AND r.sector_id = s2.id
                LEFT JOIN climbing_regions re3 ON s2.region_id = re3.id
                LEFT JOIN climbing_regions re4 ON f.entity_type = 'site' AND si.region_id = re4.id
                WHERE $whereClause
                ORDER BY f.created_at DESC
            ", $params);

            return $this->render('favorites/index', [
                'favorites' => $favorites,
                'currentType' => $type,
                'stats' => $this->getFavoriteStats($userId)
            ]);

        } catch (\Exception $e) {
            error_log("FavoriteController::index Error: " . $e->getMessage());
            $this->flash('error', 'Erreur lors du chargement des favoris');
            return $this->redirect('/');
        }
    }

    /**
     * Vérifier si une entité existe
     */
    private function entityExists(string $entityType, int $entityId): bool
    {
        $tables = [
            'sector' => 'climbing_sectors',
            'route' => 'climbing_routes',
            'site' => 'climbing_sites',
            'region' => 'climbing_regions'
        ];

        if (!isset($tables[$entityType])) {
            return false;
        }

        $result = $this->db->fetchOne(
            "SELECT 1 FROM {$tables[$entityType]} WHERE id = ? AND active = 1",
            [$entityId]
        );

        return $result !== false;
    }

    /**
     * Obtenir les statistiques des favoris
     */
    private function getFavoriteStats(int $userId): array
    {
        try {
            $stats = $this->db->fetchAll("
                SELECT entity_type, COUNT(*) as count 
                FROM user_favorites 
                WHERE user_id = ? 
                GROUP BY entity_type
            ", [$userId]);

            $result = [
                'total' => 0,
                'sectors' => 0,
                'routes' => 0,
                'sites' => 0,
                'regions' => 0
            ];

            foreach ($stats as $stat) {
                $result[$stat['entity_type'] . 's'] = (int)$stat['count'];
                $result['total'] += (int)$stat['count'];
            }

            return $result;

        } catch (\Exception $e) {
            error_log("FavoriteController::getFavoriteStats Error: " . $e->getMessage());
            return ['total' => 0, 'sectors' => 0, 'routes' => 0, 'sites' => 0, 'regions' => 0];
        }
    }
}