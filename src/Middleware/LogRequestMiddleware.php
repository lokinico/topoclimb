// src/Middleware/LogRequestMiddleware.php

namespace TopoclimbCH\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware implements MiddlewareInterface
{
private LoggerInterface $logger;

public function __construct(LoggerInterface $logger)
{
$this->logger = $logger;
}

public function handle(Request $request, callable $next): Response
{
$startTime = microtime(true);

$this->logger->info('Request started', [
'method' => $request->getMethod(),
'uri' => $request->getRequestUri(),
'ip' => $request->getClientIp(),
'user_agent' => $request->headers->get('User-Agent')
]);

$response = $next($request);

$duration = microtime(true) - $startTime;

$this->logger->info('Request completed', [
'method' => $request->getMethod(),
'uri' => $request->getRequestUri(),
'status' => $response->getStatusCode(),
'duration' => round($duration * 1000, 2) . 'ms'
]);

return $response;
}
}