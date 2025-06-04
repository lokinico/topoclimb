// src/Middleware/CorsMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware implements MiddlewareInterface
{
private array $config;

public function __construct(array $config = [])
{
$this->config = array_merge([
'allowed_origins' => ['*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
'exposed_headers' => [],
'max_age' => 86400,
'supports_credentials' => false
], $config);
}

public function handle(Request $request, callable $next): Response
{
if ($request->getMethod() === 'OPTIONS') {
return $this->createPreflightResponse();
}

$response = $next($request);

return $this->addCorsHeaders($response, $request);
}

private function createPreflightResponse(): Response
{
$response = new Response();

$response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin());
$response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
$response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
$response->headers->set('Access-Control-Max-Age', $this->config['max_age']);

if ($this->config['supports_credentials']) {
$response->headers->set('Access-Control-Allow-Credentials', 'true');
}

return $response;
}

private function addCorsHeaders(Response $response, Request $request): Response
{
$response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin($request));

if (!empty($this->config['exposed_headers'])) {
$response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
}

if ($this->config['supports_credentials']) {
$response->headers->set('Access-Control-Allow-Credentials', 'true');
}

return $response;
}

private function getAllowedOrigin(Request $request = null): string
{
if (in_array('*', $this->config['allowed_origins'])) {
return '*';
}

if ($request && in_array($request->headers->get('Origin'), $this->config['allowed_origins'])) {
return $request->headers->get('Origin');
}

return reset($this->config['allowed_origins']) ?: '*';
}
}