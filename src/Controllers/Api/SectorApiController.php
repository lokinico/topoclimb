<?php

namespace TopoclimbCH\Controllers\Api;

use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\ApiResponse;
use TopoclimbCH\Core\Routing\Route;
use TopoclimbCH\Core\Routing\Group;
use TopoclimbCH\Core\Routing\Middleware;
use TopoclimbCH\Services\SectorService;
use TopoclimbCH\Models\Sector;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Controller for Sector resources
 */
#[Group(prefix: '/api/v1/sectors')]
#[Middleware(['AuthMiddleware', 'PermissionMiddleware'])]
class SectorApiController extends ApiController
{
    private SectorService $sectorService;

    public function __construct(
        \TopoclimbCH\Core\View $view,
        \TopoclimbCH\Core\Session $session,
        \TopoclimbCH\Core\Security\CsrfManager $csrfManager,
        \TopoclimbCH\Core\Database $db,
        \TopoclimbCH\Core\Auth $auth,
        SectorService $sectorService
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->sectorService = $sectorService;
    }

    /**
     * GET /api/v1/sectors
     * List all sectors with pagination and search
     */
    #[Route('/', methods: 'GET', name: 'api.sectors.index')]
    public function index(Request $request): Response
    {
        try {
            $pagination = $this->getPaginationParams($request);
            $search = $this->getSearchParams($request);
            
            // Get total count
            $totalQuery = "SELECT COUNT(*) as total FROM climbing_sectors WHERE active = 1";
            $totalParams = [];
            
            // Add search filter
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $totalQuery .= " AND (name LIKE ? OR description LIKE ? OR code LIKE ?)";
                $totalParams = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
            }
            
            // Add region filter
            if ($request->query->has('region_id')) {
                $regionId = (int)$request->query->get('region_id');
                if ($regionId > 0) {
                    $totalQuery .= " AND region_id = ?";
                    $totalParams[] = $regionId;
                }
            }
            
            $totalResult = $this->db->fetchOne($totalQuery, $totalParams);
            $total = (int)$totalResult['total'];
            
            // Get sectors
            $query = "SELECT s.*, r.name as region_name FROM climbing_sectors s 
                     LEFT JOIN climbing_regions r ON s.region_id = r.id 
                     WHERE s.active = 1";
            $params = [];
            
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $query .= " AND (s.name LIKE ? OR s.description LIKE ? OR s.code LIKE ?)";
                $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
            }
            
            if ($request->query->has('region_id')) {
                $regionId = (int)$request->query->get('region_id');
                if ($regionId > 0) {
                    $query .= " AND s.region_id = ?";
                    $params[] = $regionId;
                }
            }
            
            // Add sorting
            $allowedSorts = ['name', 'altitude', 'access_time', 'created_at', 'updated_at'];
            $sort = in_array($search['sort'], $allowedSorts) ? $search['sort'] : 'name';
            $order = strtoupper($search['order']) === 'DESC' ? 'DESC' : 'ASC';
            
            $query .= " ORDER BY s.$sort $order LIMIT ? OFFSET ?";
            $params[] = $pagination['per_page'];
            $params[] = $pagination['offset'];
            
            $sectors = $this->db->fetchAll($query, $params);
            
            // Transform data
            $sectorData = array_map(function($sector) {
                return [
                    'id' => (int)$sector['id'],
                    'name' => $sector['name'],
                    'code' => $sector['code'],
                    'description' => $sector['description'],
                    'region_id' => (int)$sector['region_id'],
                    'region_name' => $sector['region_name'],
                    'altitude' => $sector['altitude'] ? (int)$sector['altitude'] : null,
                    'access_time' => $sector['access_time'] ? (int)$sector['access_time'] : null,
                    'coordinates_lat' => $sector['coordinates_lat'] ? (float)$sector['coordinates_lat'] : null,
                    'coordinates_lng' => $sector['coordinates_lng'] ? (float)$sector['coordinates_lng'] : null,
                    'approach' => $sector['approach'],
                    'parking_info' => $sector['parking_info'],
                    'access_info' => $sector['access_info'],
                    'created_at' => $sector['created_at'],
                    'updated_at' => $sector['updated_at']
                ];
            }, $sectors);
            
            $totalPages = $this->getTotalPages($total, $pagination['per_page']);
            
            return ApiResponse::paginated($sectorData, $total, $pagination['page'], $pagination['per_page'], $totalPages);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/sectors/{id}
     * Get a specific sector with routes
     */
    #[Route('/{id}', methods: 'GET', name: 'api.sectors.show')]
    public function show(Request $request): Response
    {
        try {
            $id = (int)$request->attributes->get('id');
            
            $sector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.id = ? AND s.active = 1",
                [$id]
            );
            
            if (!$sector) {
                return ApiResponse::notFound('Sector');
            }
            
            // Get routes for this sector
            $routes = $this->db->fetchAll(
                "SELECT r.*, ds.name as difficulty_system_name 
                 FROM climbing_routes r 
                 LEFT JOIN climbing_difficulty_systems ds ON r.difficulty_system_id = ds.id 
                 WHERE r.sector_id = ? AND r.active = 1 
                 ORDER BY r.number ASC",
                [$id]
            );
            
            $sectorData = [
                'id' => (int)$sector['id'],
                'name' => $sector['name'],
                'code' => $sector['code'],
                'description' => $sector['description'],
                'region_id' => (int)$sector['region_id'],
                'region_name' => $sector['region_name'],
                'altitude' => $sector['altitude'] ? (int)$sector['altitude'] : null,
                'access_time' => $sector['access_time'] ? (int)$sector['access_time'] : null,
                'coordinates_lat' => $sector['coordinates_lat'] ? (float)$sector['coordinates_lat'] : null,
                'coordinates_lng' => $sector['coordinates_lng'] ? (float)$sector['coordinates_lng'] : null,
                'approach' => $sector['approach'],
                'parking_info' => $sector['parking_info'],
                'access_info' => $sector['access_info'],
                'created_at' => $sector['created_at'],
                'updated_at' => $sector['updated_at'],
                'routes' => array_map(function($route) {
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
                        'comment' => $route['comment']
                    ];
                }, $routes)
            ];
            
            return ApiResponse::success($sectorData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/v1/sectors
     * Create a new sector
     */
    #[Route('/', methods: 'POST', name: 'api.sectors.store')]
    public function store(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1', '4']); // Admin, Moderator, Editor
            
            $data = $this->validateJsonInput($request, [
                'name' => 'required|string',
                'code' => 'required|string',
                'region_id' => 'required|numeric',
                'book_id' => 'required|numeric',
                'description' => 'string',
                'access_info' => 'string',
                'approach' => 'string',
                'parking_info' => 'string',
                'altitude' => 'numeric',
                'access_time' => 'numeric',
                'coordinates_lat' => 'numeric',
                'coordinates_lng' => 'numeric'
            ]);
            
            // Check if region exists
            $region = $this->db->fetchOne(
                "SELECT id FROM climbing_regions WHERE id = ? AND active = 1",
                [$data['region_id']]
            );
            
            if (!$region) {
                return ApiResponse::error('Region not found', 404);
            }
            
            // Check if code already exists for this region
            $existing = $this->db->fetchOne(
                "SELECT id FROM climbing_sectors WHERE code = ? AND region_id = ? AND active = 1",
                [$data['code'], $data['region_id']]
            );
            
            if ($existing) {
                return ApiResponse::error('Sector with this code already exists in this region', 409);
            }
            
            $sectorData = [
                'name' => $data['name'],
                'code' => $data['code'],
                'region_id' => (int)$data['region_id'],
                'book_id' => (int)$data['book_id'],
                'description' => $data['description'] ?? null,
                'access_info' => $data['access_info'] ?? null,
                'approach' => $data['approach'] ?? null,
                'parking_info' => $data['parking_info'] ?? null,
                'altitude' => isset($data['altitude']) ? (int)$data['altitude'] : null,
                'access_time' => isset($data['access_time']) ? (int)$data['access_time'] : null,
                'coordinates_lat' => isset($data['coordinates_lat']) ? (float)$data['coordinates_lat'] : null,
                'coordinates_lng' => isset($data['coordinates_lng']) ? (float)$data['coordinates_lng'] : null,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->auth->id(),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            $sectorId = $this->db->insert('climbing_sectors', $sectorData);
            
            if (!$sectorId) {
                return ApiResponse::serverError('Failed to create sector');
            }
            
            // Fetch the created sector
            $createdSector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.id = ?",
                [$sectorId]
            );
            
            $responseData = [
                'id' => (int)$createdSector['id'],
                'name' => $createdSector['name'],
                'code' => $createdSector['code'],
                'description' => $createdSector['description'],
                'region_id' => (int)$createdSector['region_id'],
                'region_name' => $createdSector['region_name'],
                'altitude' => $createdSector['altitude'] ? (int)$createdSector['altitude'] : null,
                'access_time' => $createdSector['access_time'] ? (int)$createdSector['access_time'] : null,
                'coordinates_lat' => $createdSector['coordinates_lat'] ? (float)$createdSector['coordinates_lat'] : null,
                'coordinates_lng' => $createdSector['coordinates_lng'] ? (float)$createdSector['coordinates_lng'] : null,
                'approach' => $createdSector['approach'],
                'parking_info' => $createdSector['parking_info'],
                'access_info' => $createdSector['access_info'],
                'created_at' => $createdSector['created_at'],
                'updated_at' => $createdSector['updated_at']
            ];
            
            return ApiResponse::success($responseData, [], 201);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /api/v1/sectors/{id}
     * Update a sector
     */
    #[Route('/{id}', methods: 'PUT', name: 'api.sectors.update')]
    public function update(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1', '4']); // Admin, Moderator, Editor
            
            $id = (int)$request->attributes->get('id');
            
            // Check if sector exists
            $sector = $this->db->fetchOne(
                "SELECT * FROM climbing_sectors WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$sector) {
                return ApiResponse::notFound('Sector');
            }
            
            $data = $this->validateJsonInput($request, [
                'name' => 'string',
                'code' => 'string',
                'region_id' => 'numeric',
                'book_id' => 'numeric',
                'description' => 'string',
                'access_info' => 'string',
                'approach' => 'string',
                'parking_info' => 'string',
                'altitude' => 'numeric',
                'access_time' => 'numeric',
                'coordinates_lat' => 'numeric',
                'coordinates_lng' => 'numeric'
            ]);
            
            // Check if new code conflicts with existing sector in same region
            if (isset($data['code']) && isset($data['region_id'])) {
                $regionId = $data['region_id'];
                $code = $data['code'];
                
                if ($code !== $sector['code'] || $regionId != $sector['region_id']) {
                    $existing = $this->db->fetchOne(
                        "SELECT id FROM climbing_sectors WHERE code = ? AND region_id = ? AND id != ? AND active = 1",
                        [$code, $regionId, $id]
                    );
                    
                    if ($existing) {
                        return ApiResponse::error('Sector with this code already exists in this region', 409);
                    }
                }
            }
            
            // Build update data
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            $fields = ['name', 'code', 'region_id', 'book_id', 'description', 'access_info', 'approach', 'parking_info', 'altitude', 'access_time', 'coordinates_lat', 'coordinates_lng'];
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['region_id', 'book_id', 'altitude', 'access_time'])) {
                        $updateData[$field] = (int)$data[$field];
                    } elseif (in_array($field, ['coordinates_lat', 'coordinates_lng'])) {
                        $updateData[$field] = (float)$data[$field];
                    } else {
                        $updateData[$field] = $data[$field];
                    }
                }
            }
            
            $success = $this->db->update('climbing_sectors', $updateData, ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to update sector');
            }
            
            // Fetch updated sector
            $updatedSector = $this->db->fetchOne(
                "SELECT s.*, r.name as region_name FROM climbing_sectors s 
                 LEFT JOIN climbing_regions r ON s.region_id = r.id 
                 WHERE s.id = ?",
                [$id]
            );
            
            $responseData = [
                'id' => (int)$updatedSector['id'],
                'name' => $updatedSector['name'],
                'code' => $updatedSector['code'],
                'description' => $updatedSector['description'],
                'region_id' => (int)$updatedSector['region_id'],
                'region_name' => $updatedSector['region_name'],
                'altitude' => $updatedSector['altitude'] ? (int)$updatedSector['altitude'] : null,
                'access_time' => $updatedSector['access_time'] ? (int)$updatedSector['access_time'] : null,
                'coordinates_lat' => $updatedSector['coordinates_lat'] ? (float)$updatedSector['coordinates_lat'] : null,
                'coordinates_lng' => $updatedSector['coordinates_lng'] ? (float)$updatedSector['coordinates_lng'] : null,
                'approach' => $updatedSector['approach'],
                'parking_info' => $updatedSector['parking_info'],
                'access_info' => $updatedSector['access_info'],
                'created_at' => $updatedSector['created_at'],
                'updated_at' => $updatedSector['updated_at']
            ];
            
            return ApiResponse::success($responseData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/v1/sectors/{id}
     * Delete a sector (soft delete)
     */
    #[Route('/{id}', methods: 'DELETE', name: 'api.sectors.destroy')]
    public function destroy(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1']); // Admin, Moderator only
            
            $id = (int)$request->attributes->get('id');
            
            // Check if sector exists
            $sector = $this->db->fetchOne(
                "SELECT * FROM climbing_sectors WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$sector) {
                return ApiResponse::notFound('Sector');
            }
            
            // Check if sector has active routes
            $activeRoutes = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_routes WHERE sector_id = ? AND active = 1",
                [$id]
            );
            
            if ((int)$activeRoutes['count'] > 0) {
                return ApiResponse::error('Cannot delete sector with active routes', 409);
            }
            
            // Soft delete
            $success = $this->db->update('climbing_sectors', [
                'active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ], ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to delete sector');
            }
            
            return ApiResponse::success(['message' => 'Sector deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/sectors/search
     * Search sectors (legacy endpoint for backward compatibility)
     */
    #[Route('/search', methods: 'GET', name: 'api.sectors.search')]
    public function search(Request $request): Response
    {
        // Redirect to index with search parameters
        $request->query->set('q', $request->query->get('q', ''));
        return $this->index($request);
    }
}