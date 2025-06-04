// src/Middleware/MiddlewareRegistry.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\DependencyInjection\ContainerInterface;

class MiddlewareRegistry
{
private ContainerInterface $container;
private array $aliases = [];

public function __construct(ContainerInterface $container)
{
$this->container = $container;
$this->registerDefaultAliases();
}

private function registerDefaultAliases(): void
{
$this->aliases = [
'auth' => AuthMiddleware::class,
'admin' => AdminMiddleware::class,
'csrf' => CsrfMiddleware::class,
'cors' => CorsMiddleware::class,
'api.auth' => ApiAuthMiddleware::class,
'api.throttle' => ThrottleMiddleware::class,
'maintenance' => MaintenanceMiddleware::class,
'log.requests' => LogRequestMiddleware::class,
'super.admin' => SuperAdminMiddleware::class,
];
}

public function register(string $alias, string $className): void
{
$this->aliases[$alias] = $className;
}

public function resolve(string $alias): MiddlewareInterface
{
$className = $this->aliases[$alias] ?? $alias;

if (!$this->container->has($className)) {
throw new \InvalidArgumentException("Middleware '{$alias}' not found");
}

return $this->container->get($className);
}

public function getAliases(): array
{
return $this->aliases;
}
}