// src/Middleware/CsrfMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Session;

class CsrfMiddleware implements MiddlewareInterface
{
private Session $session;

public function __construct(Session $session)
{
$this->session = $session;
}

public function handle(Request $request, callable $next): Response
{
if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
$token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-TOKEN');

if (!$this->session->validateCsrfToken($token)) {
if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
return new Response(
json_encode(['error' => 'CSRF token mismatch']),
419,
['Content-Type' => 'application/json']
);
}

return new Response('CSRF Token Mismatch', 419);
}
}

return $next($request);
}
}