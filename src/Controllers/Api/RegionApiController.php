<?php

namespace TopoclimbCH\Controllers\Api;

use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\ApiResponse;
use TopoclimbCH\Services\RegionService;
use TopoclimbCH\Models\Region;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Controller for Region resources
 */
class RegionApiController extends ApiController
{
    private RegionService $regionService;

    public function __construct(
        \TopoclimbCH\Core\View $view,
        \TopoclimbCH\Core\Session $session,
        \TopoclimbCH\Core\Security\CsrfManager $csrfManager,
        \TopoclimbCH\Core\Database $db,
        \TopoclimbCH\Core\Auth $auth,
        RegionService $regionService
    ) {
        parent::__construct($view, $session, $csrfManager, $db, $auth);
        $this->regionService = $regionService;
    }

    /**
     * GET /api/v1/regions
     * List all regions with pagination and search
     */
    public function index(Request $request): Response
    {
        try {
            $pagination = $this->getPaginationParams($request);
            $search = $this->getSearchParams($request);
            
            // Get total count
            $totalQuery = "SELECT COUNT(*) as total FROM climbing_regions WHERE active = 1";
            $totalParams = [];
            
            // Add search filter
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $totalQuery .= " AND (name LIKE ? OR description LIKE ?)";
                $totalParams = ["%$searchQuery%", "%$searchQuery%"];
            }
            
            $totalResult = $this->db->fetchOne($totalQuery, $totalParams);
            $total = (int)$totalResult['total'];
            
            // Get regions
            $query = "SELECT * FROM climbing_regions WHERE active = 1";
            $params = [];
            
            if (!empty($search['q'])) {
                $searchQuery = $this->sanitizeSearchQuery($search['q']);
                $query .= " AND (name LIKE ? OR description LIKE ?)";
                $params = ["%$searchQuery%", "%$searchQuery%"];
            }
            
            // Add sorting
            $allowedSorts = ['name', 'created_at', 'updated_at'];
            $sort = in_array($search['sort'], $allowedSorts) ? $search['sort'] : 'name';
            $order = strtoupper($search['order']) === 'DESC' ? 'DESC' : 'ASC';
            
            $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
            $params[] = $pagination['per_page'];
            $params[] = $pagination['offset'];
            
            $regions = $this->db->fetchAll($query, $params);
            
            // Transform data
            $regionData = array_map(function($region) {
                return [
                    'id' => (int)$region['id'],
                    'name' => $region['name'],
                    'description' => $region['description'],
                    'image' => $region['image'],
                    'weather_info' => $region['weather_info'],
                    'created_at' => $region['created_at'],
                    'updated_at' => $region['updated_at']
                ];
            }, $regions);
            
            $totalPages = $this->getTotalPages($total, $pagination['per_page']);
            
            return ApiResponse::paginated($regionData, $total, $pagination['page'], $pagination['per_page'], $totalPages);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/regions/{id}
     * Get a specific region
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int)$request->attributes->get('id');
            
            $region = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$region) {
                return ApiResponse::notFound('Region');
            }
            
            // Get related data
            $sites = $this->db->fetchAll(
                "SELECT id, name, description FROM climbing_sites WHERE region_id = ? AND active = 1",
                [$id]
            );
            
            $regionData = [
                'id' => (int)$region['id'],
                'name' => $region['name'],
                'description' => $region['description'],
                'image' => $region['image'],
                'weather_info' => $region['weather_info'],
                'created_at' => $region['created_at'],
                'updated_at' => $region['updated_at'],
                'sites' => array_map(function($site) {
                    return [
                        'id' => (int)$site['id'],
                        'name' => $site['name'],
                        'description' => $site['description']
                    ];
                }, $sites)
            ];
            
            return ApiResponse::success($regionData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/v1/regions
     * Create a new region
     */
    public function store(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1']); // Admin, Moderator only
            
            $data = $this->validateJsonInput($request, [
                'name' => 'required|string',
                'description' => 'string',
                'image' => 'string',
                'weather_info' => 'string'
            ]);
            
            // Check if region name already exists
            $existing = $this->db->fetchOne(
                "SELECT id FROM climbing_regions WHERE name = ? AND active = 1",
                [$data['name']]
            );
            
            if ($existing) {
                return ApiResponse::error('Region with this name already exists', 409);
            }
            
            $regionData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'weather_info' => $data['weather_info'] ?? null,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->auth->id(),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            $regionId = $this->db->insert('climbing_regions', $regionData);
            
            if (!$regionId) {
                return ApiResponse::serverError('Failed to create region');
            }
            
            // Fetch the created region
            $createdRegion = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ?",
                [$regionId]
            );
            
            $responseData = [
                'id' => (int)$createdRegion['id'],
                'name' => $createdRegion['name'],
                'description' => $createdRegion['description'],
                'image' => $createdRegion['image'],
                'weather_info' => $createdRegion['weather_info'],
                'created_at' => $createdRegion['created_at'],
                'updated_at' => $createdRegion['updated_at']
            ];
            
            return ApiResponse::success($responseData, [], 201);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /api/v1/regions/{id}
     * Update a region
     */
    public function update(Request $request): Response
    {
        try {
            $this->requirePermission(['0', '1']); // Admin, Moderator only
            
            $id = (int)$request->attributes->get('id');
            
            // Check if region exists
            $region = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$region) {
                return ApiResponse::notFound('Region');
            }
            
            $data = $this->validateJsonInput($request, [
                'name' => 'string',
                'description' => 'string',
                'image' => 'string',
                'weather_info' => 'string'
            ]);
            
            // Check if new name conflicts with existing region
            if (isset($data['name']) && $data['name'] !== $region['name']) {
                $existing = $this->db->fetchOne(
                    "SELECT id FROM climbing_regions WHERE name = ? AND id != ? AND active = 1",
                    [$data['name'], $id]
                );
                
                if ($existing) {
                    return ApiResponse::error('Region with this name already exists', 409);
                }
            }
            
            // Build update data
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ];
            
            foreach (['name', 'description', 'image', 'weather_info'] as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            $success = $this->db->update('climbing_regions', $updateData, ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to update region');
            }
            
            // Fetch updated region
            $updatedRegion = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ?",
                [$id]
            );
            
            $responseData = [
                'id' => (int)$updatedRegion['id'],
                'name' => $updatedRegion['name'],
                'description' => $updatedRegion['description'],
                'image' => $updatedRegion['image'],
                'weather_info' => $updatedRegion['weather_info'],
                'created_at' => $updatedRegion['created_at'],
                'updated_at' => $updatedRegion['updated_at']
            ];
            
            return ApiResponse::success($responseData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/v1/regions/{id}
     * Delete a region (soft delete)
     */
    public function destroy(Request $request): Response
    {
        try {
            $this->requirePermission(['0']); // Admin only
            
            $id = (int)$request->attributes->get('id');
            
            // Check if region exists
            $region = $this->db->fetchOne(
                "SELECT * FROM climbing_regions WHERE id = ? AND active = 1",
                [$id]
            );
            
            if (!$region) {
                return ApiResponse::notFound('Region');
            }
            
            // Check if region has active sites
            $activeSites = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM climbing_sites WHERE region_id = ? AND active = 1",
                [$id]
            );
            
            if ((int)$activeSites['count'] > 0) {
                return ApiResponse::error('Cannot delete region with active sites', 409);
            }
            
            // Soft delete
            $success = $this->db->update('climbing_regions', [
                'active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->auth->id()
            ], ['id' => $id]);
            
            if (!$success) {
                return ApiResponse::serverError('Failed to delete region');
            }
            
            return ApiResponse::success(['message' => 'Region deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/v1/regions/search
     * Search regions (legacy endpoint for backward compatibility)
     */
    public function search(Request $request): Response
    {
        // Redirect to index with search parameters
        $request->query->set('q', $request->query->get('q', ''));
        return $this->index($request);
    }
}