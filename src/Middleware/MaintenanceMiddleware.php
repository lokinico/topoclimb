// src/Middleware/MaintenanceMiddleware.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Services\AuthService;

class MaintenanceMiddleware implements MiddlewareInterface
{
private AuthService $authService;
private string $maintenanceFile;

public function __construct(AuthService $authService, string $maintenanceFile = '')
{
$this->authService = $authService;
$this->maintenanceFile = $maintenanceFile ?: storage_path('framework/maintenance.php');
}

public function handle(Request $request, callable $next): Response
{
if ($this->isInMaintenanceMode() && !$this->shouldBypassMaintenance($request)) {
$data = $this->getMaintenanceData();

if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
return new Response(
json_encode([
'error' => 'Service Unavailable',
'message' => $data['message'] ?? 'Application is in maintenance mode',
'retry_after' => $data['retry_after'] ?? null
]),
503,
['Content-Type' => 'application/json']
);
}

return $this->renderMaintenancePage($data);
}

return $next($request);
}

private function isInMaintenanceMode(): bool
{
return file_exists($this->maintenanceFile);
}

private function shouldBypassMaintenance(Request $request): bool
{
// Les administrateurs peuvent bypasser la maintenance
if ($this->authService->check() && $this->authService->hasRole('admin')) {
return true;
}

// Certaines routes peuvent être autorisées
$allowedPaths = ['/admin/maintenance', '/health', '/api/health'];

return in_array($request->getPathInfo(), $allowedPaths);
}

private function getMaintenanceData(): array
{
if (!file_exists($this->maintenanceFile)) {
return [];
}

return include $this->maintenanceFile;
}

private function renderMaintenancePage(array $data): Response
{
$html = '
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance - TopoclimbCH</title>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: #f3f4f6;
        }

        .container {
            max-width: 32rem;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 1.875rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        p {
            color: #6b7280;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Maintenance en cours</h1>
        <p>' . ($data['message'] ?? 'Nous effectuons une maintenance sur TopoclimbCH. Nous serons de retour bientôt !') . '</p>
        ' . (isset($data['retry_after']) ? '<p>Temps estimé : ' . date('H:i', $data['retry_after']) . '</p>' : '') . '
    </div>
</body>

</html>';

return new Response($html, 503);
}
}