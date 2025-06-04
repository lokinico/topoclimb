// scripts/migrate_routes.php
<?php

class RouteMigrator
{
    public function migrateToNewFormat(array $oldRoutes): string
    {
        $newRoutes = "<?php\nuse TopoclimbCH\\Core\\Router;\n\n";
        $newRoutes .= "return function (Router \$router) {\n";

        foreach ($oldRoutes as $route) {
            $method = strtolower($route['method']);
            $path = $route['path'];
            $controller = $route['controller'];
            $action = $route['action'];
            $middlewares = $route['middlewares'] ?? [];

            $newRoutes .= "    \$router->$method('$path', [\n";
            $newRoutes .= "        'controller' => '$controller',\n";
            $newRoutes .= "        'action' => '$action'\n";
            $newRoutes .= "    ]";

            if (!empty($middlewares)) {
                $middlewareStr = "'" . implode("', '", $middlewares) . "'";
                $newRoutes .= ", [\n        'middlewares' => [$middlewareStr]\n    ]";
            }

            $newRoutes .= ");\n\n";
        }

        $newRoutes .= "};";

        return $newRoutes;
    }
}
