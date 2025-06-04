// src/Middleware/ApiAuthMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Services\ApiTokenService;

class ApiAuthMiddleware implements MiddlewareInterface
{
private ApiTokenService $tokenService;

public function __construct(ApiTokenService $tokenService)
{
$this->tokenService = $tokenService;
}

public function handle(Request $request, callable $next): Response
{
$token = $this->extractToken($request);

if (!$token || !$this->tokenService->validateToken($token)) {
return new Response(
json_encode(['error' => 'Invalid or missing API token']),
401,
['Content-Type' => 'application/json']
);
}

// Ajouter les informations utilisateur à la requête
$user = $this->tokenService->getUserFromToken($token);
$request->attributes->set('api_user', $user);
$request->attributes->set('api_token', $token);

return $next($request);
}

private function extractToken(Request $request): ?string
{
// Bearer token
$authHeader = $request->headers->get('Authorization');
if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
return substr($authHeader, 7);
}

// API key dans les paramètres
return $request->query->get('api_key') ?? $request->request->get('api_key');
}
}