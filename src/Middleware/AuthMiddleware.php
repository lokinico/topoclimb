// src/Middleware/AuthMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Services\AuthService;

class AuthMiddleware implements MiddlewareInterface
{
private AuthService $authService;
private Session $session;

public function __construct(AuthService $authService, Session $session)
{
$this->authService = $authService;
$this->session = $session;
}

public function handle(Request $request, callable $next): Response
{
if (!$this->authService->check()) {
// Sauvegarder l'URL demandée pour redirection après login
$this->session->set('intended_url', $request->getRequestUri());

if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
return new Response(
json_encode(['error' => 'Authentication required']),
401,
['Content-Type' => 'application/json']
);
}

return new Response('', 302, ['Location' => '/auth/login']);
}

return $next($request);
}
}