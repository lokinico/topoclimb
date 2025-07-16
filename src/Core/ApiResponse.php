<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Core\Response;

/**
 * Standardized API Response handler for consistent JSON responses
 */
class ApiResponse
{
    /**
     * Create a standardized success response
     */
    public static function success($data, array $meta = [], int $statusCode = 200): Response
    {
        $response = [
            'status' => 'success',
            'data' => $data
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return Response::json($response, $statusCode);
    }

    /**
     * Create a standardized error response
     */
    public static function error(string $message, int $statusCode = 400, string $code = null): Response
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($code) {
            $response['code'] = $code;
        }

        return Response::json($response, $statusCode);
    }

    /**
     * Create a paginated response
     */
    public static function paginated(array $items, int $total, int $page, int $perPage, int $totalPages): Response
    {
        $data = [
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => count($items),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages
                ]
            ]
        ];

        return Response::json($data, 200);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(array $errors): Response
    {
        return self::error('Validation failed', 422, 'VALIDATION_ERROR')
            ->withData(['errors' => $errors]);
    }

    /**
     * Create a not found response
     */
    public static function notFound(string $resource = 'Resource'): Response
    {
        return self::error("$resource not found", 404, 'NOT_FOUND');
    }

    /**
     * Create an unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): Response
    {
        return self::error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Create a forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): Response
    {
        return self::error($message, 403, 'FORBIDDEN');
    }

    /**
     * Create a server error response
     */
    public static function serverError(string $message = 'Internal server error'): Response
    {
        return self::error($message, 500, 'SERVER_ERROR');
    }
}