// src/Middleware/AdminMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Services\AuthService;

class AdminMiddleware implements MiddlewareInterface
{
private AuthService $authService;

public function __construct(AuthService $authService)
{
$this->authService = $authService;
}

public function handle(Request $request, callable $next): Response
{
if (!$this->authService->check() || !$this->authService->hasRole('admin')) {
if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
return new Response(
json_encode(['error' => 'Admin access required']),
403,
['Content-Type' => 'application/json']
);
}

return new Response('', 403, ['Location' => '/']);
}

return $next($request);
}
}