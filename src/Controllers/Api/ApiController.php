<?php

namespace TopoclimbCH\Controllers\Api;

use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\ApiResponse;
use TopoclimbCH\Core\View;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Security\CsrfManager;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Exceptions\ValidationException;
use TopoclimbCH\Exceptions\AuthorizationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base controller for all API endpoints
 */
abstract class ApiController
{
    protected View $view;
    protected Session $session;
    protected CsrfManager $csrfManager;
    protected Database $db;
    protected Auth $auth;

    public function __construct(
        View $view,
        Session $session,
        CsrfManager $csrfManager,
        Database $db,
        Auth $auth
    ) {
        $this->view = $view;
        $this->session = $session;
        $this->csrfManager = $csrfManager;
        $this->db = $db;
        $this->auth = $auth;
    }

    /**
     * Validate required authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->auth->check()) {
            throw new AuthorizationException('Authentication required');
        }
    }

    /**
     * Validate user permissions
     */
    protected function requirePermission(array $allowedRoles): void
    {
        $this->requireAuth();
        
        $userRole = (string)$this->auth->role();
        if (!in_array($userRole, $allowedRoles)) {
            throw new AuthorizationException('Insufficient permissions');
        }
    }

    /**
     * Validate JSON request data
     */
    protected function validateJsonInput(Request $request, array $rules): array
    {
        $contentType = $request->headers->get('Content-Type');
        
        if (!str_contains($contentType, 'application/json')) {
            throw new ValidationException('Content-Type must be application/json');
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException('Invalid JSON data');
        }

        return $this->validateData($data, $rules);
    }

    /**
     * Validate data against rules
     */
    protected function validateData(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (str_contains($rule, 'required') && (is_null($value) || $value === '')) {
                $errors[$field] = "Field $field is required";
                continue;
            }

            if (!is_null($value)) {
                if (str_contains($rule, 'string') && !is_string($value)) {
                    $errors[$field] = "Field $field must be a string";
                    continue;
                }

                if (str_contains($rule, 'integer') && !is_int($value)) {
                    $errors[$field] = "Field $field must be an integer";
                    continue;
                }

                if (str_contains($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "Field $field must be a valid email";
                    continue;
                }

                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(json_encode($errors));
        }

        return $validated;
    }

    /**
     * Handle exceptions and return appropriate API response
     */
    protected function handleException(\Exception $e): Response
    {
        if ($e instanceof ValidationException) {
            $errors = json_decode($e->getMessage(), true);
            return ApiResponse::validationError($errors ?: [$e->getMessage()]);
        }

        if ($e instanceof AuthorizationException) {
            return ApiResponse::unauthorized($e->getMessage());
        }

        // Log the error
        error_log("API Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        return ApiResponse::serverError('An unexpected error occurred');
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = min(100, max(1, (int)$request->query->get('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset
        ];
    }

    /**
     * Calculate total pages for pagination
     */
    protected function getTotalPages(int $total, int $perPage): int
    {
        return (int)ceil($total / $perPage);
    }

    /**
     * Get search parameters from request
     */
    protected function getSearchParams(Request $request): array
    {
        return [
            'q' => $request->query->get('q', ''),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'asc')
        ];
    }

    /**
     * Sanitize search query
     */
    protected function sanitizeSearchQuery(string $query): string
    {
        return trim(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'));
    }
}