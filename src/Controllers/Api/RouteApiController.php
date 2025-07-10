<?php

namespace TopoclimbCH\Controllers\Api;

use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\ApiResponse;
use TopoclimbCH\Core\Routing\Route;
use TopoclimbCH\Core\Routing\Group;
use TopoclimbCH\Core\Routing\Middleware;
use TopoclimbCH\Models\Route as ClimbingRoute;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Controller for Route resources
 */
#[Group(prefix: '/api/v1/routes')]
#[Middleware(['AuthMiddleware', 'PermissionMiddleware'])]
class RouteApiController extends ApiController
{
    /**
     * GET /api/v1/routes
     * List all routes with pagination and search
     */
    #[Route('/', methods: 'GET', name: 'api.routes.index')]
    public function index(Request $request): Response
    {
        try {
            $pagination = $this->getPaginationParams($request);
            $search = $this->getSearchParams($request);
            
            // Build base query
            $totalQuery = "SELECT COUNT(*) as total FROM climbing_routes r 
                          LEFT JOIN climbing_sectors s ON r.sector_id = s.id 
                          WHERE r.active = 1";
            $totalParams = [];
            
            // Add search filter
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $totalQuery .= " AND (r.name LIKE ? OR r.comment LIKE ?)";
                $totalParams = ["%$searchQuery%", "%$searchQuery%"];
            }
            
            // Add sector filter
            if ($request->query->has('sector_id')) {
                $sectorId = (int)$request->query->get('sector_id');
                if ($sectorId > 0) {
                    $totalQuery .= " AND r.sector_id = ?";
                    $totalParams[] = $sectorId;
                }
            }
            
            // Add difficulty filter
            if ($request->query->has('difficulty')) {
                $difficulty = $request->query->get('difficulty');
                if (!empty($difficulty)) {
                    $totalQuery .= " AND r.difficulty = ?";
                    $totalParams[] = $difficulty;
                }
            }
            
            // Add style filter
            if ($request->query->has('style')) {
                $style = $request->query->get('style');
                if (!empty($style)) {
                    $totalQuery .= " AND r.style = ?";
                    $totalParams[] = $style;
                }
            }
            
            // Add minimum beauty filter
            if ($request->query->has('min_beauty')) {
                $minBeauty = (int)$request->query->get('min_beauty');
                if ($minBeauty > 0 && $minBeauty <= 5) {
                    $totalQuery .= " AND r.beauty >= ?";
                    $totalParams[] = $minBeauty;
                }
            }
            
            $totalResult = $this->db->fetchOne($totalQuery, $totalParams);
            $total = (int)$totalResult['total'];
            
            // Get routes
            $query = "SELECT r.*, s.name as sector_name, s.code as sector_code, 
                            reg.name as region_name, ds.name as difficulty_system_name
                     FROM climbing_routes r 
                     LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                     LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                     LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
                     WHERE r.active = 1";
            $params = [];
            
            // Apply same filters to main query
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $query .= " AND (r.name LIKE ? OR r.comment LIKE ?)";
                $params = ["%$searchQuery%", "%$searchQuery%"];
            }
            
            if ($request->query->has('sector_id')) {
                $sectorId = (int)$request->query->get('sector_id');
                if ($sectorId > 0) {
                    $query .= " AND r.sector_id = ?";
                    $params[] = $sectorId;
                }
            }
            
            if ($request->query->has('difficulty')) {
                $difficulty = $request->query->get('difficulty');
                if (!empty($difficulty)) {
                    $query .= " AND r.difficulty = ?";
                    $params[] = $difficulty;
                }
            }
            
            if ($request->query->has('style')) {
                $style = $request->query->get('style');
                if (!empty($style)) {
                    $query .= " AND r.style = ?";
                    $params[] = $style;
                }
            }
            
            if ($request->query->has('min_beauty')) {
                $minBeauty = (int)$request->query->get('min_beauty');
                if ($minBeauty > 0 && $minBeauty <= 5) {
                    $query .= " AND r.beauty >= ?";
                    $params[] = $minBeauty;
                }
            }
            
            // Add sorting
            $allowedSorts = ['name', 'number', 'difficulty', 'beauty', 'length', 'created_at', 'updated_at'];
            $sort = in_array($search['sort'], $allowedSorts) ? $search['sort'] : 'number';
            $order = strtoupper($search['order']) === 'DESC' ? 'DESC' : 'ASC';
            
            $query .= " ORDER BY r.$sort $order LIMIT ? OFFSET ?";
            $params[] = $pagination['per_page'];
            $params[] = $pagination['offset'];
            
            $routes = $this->db->fetchAll($query, $params);
            
            // Transform data
            $routeData = array_map(function($route) {
                return [
                    'id' => (int)$route['id'],
                    'number' => (int)$route['number'],
                    'name' => $route['name'],
                    'difficulty' => $route['difficulty'],
                    'difficulty_system_name' => $route['difficulty_system_name'],
                    'beauty' => $route['beauty'] ? (int)$route['beauty'] : null,
                    'style' => $route['style'],
                    'length' => $route['length'] ? (int)$route['length'] : null,
                    'equipment' => $route['equipment'],
                    'rappel' => $route['rappel'],
                    'comment' => $route['comment'],
                    'sector_id' => (int)$route['sector_id'],
                    'sector_name' => $route['sector_name'],
                    'sector_code' => $route['sector_code'],
                    'region_name' => $route['region_name'],
                    'created_at' => $route['created_at'],
                    'updated_at' => $route['updated_at']
                ];
            }, $routes);
            
            $totalPages = $this->getTotalPages($total, $pagination['per_page']);
            
            return ApiResponse::paginated($routeData, $total, $pagination['page'], $pagination['per_page'], $totalPages);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/routes/{id}
     * Get a specific route
     */
    #[Route('/{id}', methods: 'GET', name: 'api.routes.show')]
    public function show(Request $request): Response
    {
        try {
            $id = (int)$request->attributes->get('id');
            
            $route = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name, s.code as sector_code,
                        reg.name as region_name, ds.name as difficulty_system_name
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
                 WHERE r.id = ? AND r.active = 1",
                [$id]
            );
            
            if (!$route) {
                return ApiResponse::notFound('Route');
            }
            
            $routeData = [
                'id' => (int)$route['id'],
                'number' => (int)$route['number'],
                'name' => $route['name'],
                'difficulty' => $route['difficulty'],
                'difficulty_system_id' => (int)$route['difficulty_system_id'],
                'difficulty_system_name' => $route['difficulty_system_name'],
                'beauty' => $route['beauty'] ? (int)$route['beauty'] : null,
                'style' => $route['style'],
                'length' => $route['length'] ? (int)$route['length'] : null,
                'equipment' => $route['equipment'],
                'rappel' => $route['rappel'],
                'comment' => $route['comment'],
                'legacy_topo_item' => $route['legacy_topo_item'],
                'sector_id' => (int)$route['sector_id'],
                'sector_name' => $route['sector_name'],
                'sector_code' => $route['sector_code'],
                'region_name' => $route['region_name'],
                'created_at' => $route['created_at'],
                'updated_at' => $route['updated_at']
            ];
            
            return ApiResponse::success($routeData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/v1/routes
     * Create a new route
     */
    #[Route('/', methods: 'POST', name: 'api.routes.store')]
    public function store(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1', '4']); // Admin, Moderator, Editor
            
            $data = $this->validateJsonInput($request, [
                'name' => 'required|string',
                'sector_id' => 'required|numeric',
                'difficulty' => 'required|string',
                'difficulty_system_id' => 'required|numeric',
                'number' => 'numeric',
                'beauty' => 'numeric',
                'style' => 'string',
                'length' => 'numeric',
                'equipment' => 'string',
                'rappel' => 'string',
                'comment' => 'string'
            ]);
            
            // Check if sector exists
            $sector = $this->db->fetchOne(
                "SELECT id FROM climbing_sectors WHERE id = ? AND active = 1",
                [$data['sector_id']]
            );
            
            if (!$sector) {
                return ApiResponse::error('Sector not found', 404);
            }
            
            // Check if difficulty system exists
            $difficultySystem = $this->db->fetchOne(
                "SELECT id FROM climbing_difficulty_systems WHERE id = ?",
                [$data['difficulty_system_id']]
            );
            
            if (!$difficultySystem) {
                return ApiResponse::error('Difficulty system not found', 404);
            }
            
            // Auto-assign number if not provided
            if (!isset($data['number'])) {
                $maxNumber = $this->db->fetchOne(
                    "SELECT MAX(number) as max_num FROM climbing_routes WHERE sector_id = ?",
                    [$data['sector_id']]
                );
                $data['number'] = ($maxNumber['max_num'] ?? 0) + 1;
            }
            
            // Validate beauty rating
            if (isset($data['beauty']) && ($data['beauty'] < 0 || $data['beauty'] > 5)) {
                return ApiResponse::error('Beauty rating must be between 0 and 5', 400);
            }
            
            // Validate style
            $allowedStyles = ['sport', 'trad', 'mix', 'boulder', 'aid', 'ice', 'other'];
            if (isset($data['style']) && !in_array($data['style'], $allowedStyles)) {
                return ApiResponse::error('Invalid style. Allowed values: ' . implode(', ', $allowedStyles), 400);
            }
            
            // Validate equipment
            $allowedEquipment = ['poor', 'adequate', 'good', 'excellent'];
            if (isset($data['equipment']) && !in_array($data['equipment'], $allowedEquipment)) {
                return ApiResponse::error('Invalid equipment rating. Allowed values: ' . implode(', ', $allowedEquipment), 400);
            }
            
            $routeData = [
                'name' => $data['name'],
                'sector_id' => (int)$data['sector_id'],
                'number' => (int)$data['number'],
                'difficulty' => $data['difficulty'],
                'difficulty_system_id' => (int)$data['difficulty_system_id'],
                'beauty' => isset($data['beauty']) ? (int)$data['beauty'] : null,
                'style' => $data['style'] ?? null,
                'length' => isset($data['length']) ? (int)$data['length'] : null,
                'equipment' => $data['equipment'] ?? null,
                'rappel' => $data['rappel'] ?? null,
                'comment' => $data['comment'] ?? null,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->auth->id(),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            $routeId = $this->db->insert('climbing_routes', $routeData);
            
            if (!$routeId) {
                return ApiResponse::serverError('Failed to create route');
            }
            
            // Fetch the created route
            $createdRoute = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name, s.code as sector_code,
                        reg.name as region_name, ds.name as difficulty_system_name
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
                 WHERE r.id = ?",
                [$routeId]
            );
            
            $responseData = [
                'id' => (int)$createdRoute['id'],
                'number' => (int)$createdRoute['number'],
                'name' => $createdRoute['name'],
                'difficulty' => $createdRoute['difficulty'],
                'difficulty_system_id' => (int)$createdRoute['difficulty_system_id'],
                'difficulty_system_name' => $createdRoute['difficulty_system_name'],
                'beauty' => $createdRoute['beauty'] ? (int)$createdRoute['beauty'] : null,
                'style' => $createdRoute['style'],
                'length' => $createdRoute['length'] ? (int)$createdRoute['length'] : null,
                'equipment' => $createdRoute['equipment'],
                'rappel' => $createdRoute['rappel'],
                'comment' => $createdRoute['comment'],
                'sector_id' => (int)$createdRoute['sector_id'],
                'sector_name' => $createdRoute['sector_name'],
                'sector_code' => $createdRoute['sector_code'],
                'region_name' => $createdRoute['region_name'],
                'created_at' => $createdRoute['created_at'],
                'updated_at' => $createdRoute['updated_at']
            ];
            
            return ApiResponse::success($responseData, [], 201);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /api/v1/routes/{id}
     * Update a route
     */
    #[Route('/{id}', methods: 'PUT', name: 'api.routes.update')]
    public function update(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1', '4']); // Admin, Moderator, Editor
            
            $id = (int)$request->attributes->get('id');
            
            // Check if route exists
            $route = $this->db->fetchOne(
                "SELECT * FROM climbing_routes WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$route) {
                return ApiResponse::notFound('Route');
            }
            
            $data = $this->validateJsonInput($request, [
                'name' => 'string',
                'sector_id' => 'numeric',
                'difficulty' => 'string',
                'difficulty_system_id' => 'numeric',
                'number' => 'numeric',
                'beauty' => 'numeric',
                'style' => 'string',
                'length' => 'numeric',
                'equipment' => 'string',
                'rappel' => 'string',
                'comment' => 'string'
            ]);
            
            // Validate beauty rating
            if (isset($data['beauty']) && ($data['beauty'] < 0 || $data['beauty'] > 5)) {
                return ApiResponse::error('Beauty rating must be between 0 and 5', 400);
            }
            
            // Validate style
            $allowedStyles = ['sport', 'trad', 'mix', 'boulder', 'aid', 'ice', 'other'];
            if (isset($data['style']) && !in_array($data['style'], $allowedStyles)) {
                return ApiResponse::error('Invalid style. Allowed values: ' . implode(', ', $allowedStyles), 400);
            }
            
            // Validate equipment
            $allowedEquipment = ['poor', 'adequate', 'good', 'excellent'];
            if (isset($data['equipment']) && !in_array($data['equipment'], $allowedEquipment)) {
                return ApiResponse::error('Invalid equipment rating. Allowed values: ' . implode(', ', $allowedEquipment), 400);
            }
            
            // Build update data
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            $fields = ['name', 'sector_id', 'number', 'difficulty', 'difficulty_system_id', 'beauty', 'style', 'length', 'equipment', 'rappel', 'comment'];
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['sector_id', 'number', 'difficulty_system_id', 'beauty', 'length'])) {
                        $updateData[$field] = (int)$data[$field];
                    } else {
                        $updateData[$field] = $data[$field];
                    }
                }
            }
            
            $success = $this->db->update('climbing_routes', $updateData, ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to update route');
            }
            
            // Fetch updated route
            $updatedRoute = $this->db->fetchOne(
                "SELECT r.*, s.name as sector_name, s.code as sector_code,
                        reg.name as region_name, ds.name as difficulty_system_name
                 FROM climbing_routes r 
                 LEFT JOIN climbing_sectors s ON r.sector_id = s.id
                 LEFT JOIN climbing_regions reg ON s.region_id = reg.id
                 LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id
                 WHERE r.id = ?",
                [$id]
            );
            
            $responseData = [
                'id' => (int)$updatedRoute['id'],
                'number' => (int)$updatedRoute['number'],
                'name' => $updatedRoute['name'],
                'difficulty' => $updatedRoute['difficulty'],
                'difficulty_system_id' => (int)$updatedRoute['difficulty_system_id'],
                'difficulty_system_name' => $updatedRoute['difficulty_system_name'],
                'beauty' => $updatedRoute['beauty'] ? (int)$updatedRoute['beauty'] : null,
                'style' => $updatedRoute['style'],
                'length' => $updatedRoute['length'] ? (int)$updatedRoute['length'] : null,
                'equipment' => $updatedRoute['equipment'],
                'rappel' => $updatedRoute['rappel'],
                'comment' => $updatedRoute['comment'],
                'sector_id' => (int)$updatedRoute['sector_id'],
                'sector_name' => $updatedRoute['sector_name'],
                'sector_code' => $updatedRoute['sector_code'],
                'region_name' => $updatedRoute['region_name'],
                'created_at' => $updatedRoute['created_at'],
                'updated_at' => $updatedRoute['updated_at']
            ];
            
            return ApiResponse::success($responseData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/v1/routes/{id}
     * Delete a route (soft delete)
     */
    #[Route('/{id}', methods: 'DELETE', name: 'api.routes.destroy')]
    public function destroy(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1', '4']); // Admin, Moderator, Editor
            
            $id = (int)$request->attributes->get('id');
            
            // Check if route exists
            $route = $this->db->fetchOne(
                "SELECT * FROM climbing_routes WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$route) {
                return ApiResponse::notFound('Route');
            }
            
            // Soft delete
            $success = $this->db->update('climbing_routes', [
                'active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ], ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to delete route');
            }
            
            return ApiResponse::success(['message' => 'Route deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/routes/search
     * Search routes (legacy endpoint for backward compatibility)
     */
    #[Route('/search', methods: 'GET', name: 'api.routes.search')]
    public function search(Request $request): Response
    {
        // Redirect to index with search parameters
        $request->query->set('q', $request->query->get('q', ''));
        return $this->index($request);
    }
}