// src/Middleware/ThrottleMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Cache\CacheInterface;

class ThrottleMiddleware implements MiddlewareInterface
{
private CacheInterface $cache;
private int $maxAttempts;
private int $decayMinutes;

public function __construct(CacheInterface $cache, int $maxAttempts = 60, int $decayMinutes = 1)
{
$this->cache = $cache;
$this->maxAttempts = $maxAttempts;
$this->decayMinutes = $decayMinutes;
}

public function handle(Request $request, callable $next): Response
{
$key = $this->resolveRequestSignature($request);

if ($this->tooManyAttempts($key)) {
return $this->buildResponse($key);
}

$this->hit($key);

$response = $next($request);

return $this->addHeaders(
$response,
$this->maxAttempts,
$this->calculateRemainingAttempts($key)
);
}

private function resolveRequestSignature(Request $request): string
{
return sha1(
$request->getMethod() .
'|' . $request->server->get('SERVER_NAME') .
'|' . $request->getClientIp() .
'|' . $request->getPathInfo()
);
}

private function tooManyAttempts(string $key): bool
{
return $this->cache->get($key . ':timer') !== null &&
$this->cache->get($key, 0) >= $this->maxAttempts;
}

private function hit(string $key): void
{
$this->cache->set(
$key . ':timer',
time() + ($this->decayMinutes * 60),
$this->decayMinutes * 60
);

$hits = (int) $this->cache->get($key, 0);
$this->cache->set($key, $hits + 1, $this->decayMinutes * 60);
}

private function calculateRemainingAttempts(string $key): int
{
return $this->maxAttempts - (int) $this->cache->get($key, 0);
}

private function buildResponse(string $key): Response
{
$retryAfter = $this->cache->get($key . ':timer') - time();

return new Response(
json_encode(['error' => 'Too Many Requests']),
429,
[
'Content-Type' => 'application/json',
'Retry-After' => $retryAfter,
'X-RateLimit-Limit' => $this->maxAttempts,
'X-RateLimit-Remaining' => 0
]
);
}

private function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
{
$response->headers->add([
'X-RateLimit-Limit' => $maxAttempts,
'X-RateLimit-Remaining' => max(0, $remainingAttempts)
]);

return $response;
}
}