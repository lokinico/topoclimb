<?php
// src/Core/Routing/RouteGroup.php

namespace TopoclimbCH\Core\Routing;

use TopoclimbCH\Core\Router;

class RouteGroup
{
    /**
     * @var Router
     */
    private Router $router;

    /**
     * Attributs du groupe
     *
     * @var array
     */
    private array $attributes;

    /**
     * Préfixe du groupe
     *
     * @var string
     */
    private string $prefix = '';

    /**
     * Middlewares du groupe
     *
     * @var array
     */
    private array $middlewares = [];

    /**
     * Namespace du groupe
     *
     * @var string
     */
    private string $namespace = '';

    /**
     * Nom du groupe (préfixe pour les routes nommées)
     *
     * @var string
     */
    private string $name = '';

    /**
     * Domaine du groupe
     *
     * @var string
     */
    private string $domain = '';

    /**
     * Contraintes par défaut du groupe
     *
     * @var array
     */
    private array $constraints = [];

    /**
     * RouteGroup constructor
     *
     * @param Router $router
     * @param array $attributes
     */
    public function __construct(Router $router, array $attributes = [])
    {
        $this->router = $router;
        $this->attributes = $attributes;

        $this->prefix = $attributes['prefix'] ?? '';
        $this->middlewares = $attributes['middlewares'] ?? [];
        $this->namespace = $attributes['namespace'] ?? '';
        $this->name = $attributes['name'] ?? '';
        $this->domain = $attributes['domain'] ?? '';
        $this->constraints = $attributes['constraints'] ?? [];
    }

    /**
     * Ajouter une route GET au groupe
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function get(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    /**
     * Ajouter une route POST au groupe
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function post(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    /**
     * Ajouter une route PUT au groupe
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function put(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    /**
     * Ajouter une route DELETE au groupe
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function delete(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    /**
     * Ajouter une route PATCH au groupe
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function patch(string $path, mixed $handler, array $options = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    /**
     * Ajouter une route pour toutes les méthodes
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function any(string $path, mixed $handler, array $options = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $options);
        }

        return $this;
    }

    /**
     * Ajouter une route avec méthodes spécifiques
     *
     * @param array $methods
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    public function match(array $methods, string $path, mixed $handler, array $options = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $options);
        }

        return $this;
    }

    /**
     * Créer un sous-groupe
     *
     * @param array $attributes
     * @param callable $callback
     * @return RouteGroup
     */
    public function group(array $attributes, callable $callback): RouteGroup
    {
        // Fusionner les attributs du groupe parent
        $mergedAttributes = $this->mergeAttributes($attributes);

        $group = new RouteGroup($this->router, $mergedAttributes);
        $callback($group);

        return $group;
    }

    /**
     * Créer des routes de ressource standard (CRUD)
     *
     * @param string $name Nom de la ressource (ex: 'users')
     * @param string $controller Classe du contrôleur
     * @param array $options Options spécifiques
     * @return self
     */
    public function resource(string $name, string $controller, array $options = []): self
    {
        $only = $options['only'] ?? ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $parameters = $options['parameters'] ?? [];
        $names = $options['names'] ?? [];

        // Filtrer les actions
        $actions = array_diff($only, $except);

        // Paramètre principal (ex: {user})
        $parameter = $parameters[$name] ?? '{id}';
        $resourceNameSingular = $options['singular'] ?? rtrim($name, 's');

        foreach ($actions as $action) {
            switch ($action) {
                case 'index':
                    $this->get("/{$name}", [
                        'controller' => $controller,
                        'action' => 'index'
                    ], [
                        'name' => $names['index'] ?? "{$name}.index"
                    ]);
                    break;

                case 'create':
                    $this->get("/{$name}/create", [
                        'controller' => $controller,
                        'action' => 'create'
                    ], [
                        'name' => $names['create'] ?? "{$name}.create"
                    ]);
                    break;

                case 'store':
                    $this->post("/{$name}", [
                        'controller' => $controller,
                        'action' => 'store'
                    ], [
                        'name' => $names['store'] ?? "{$name}.store"
                    ]);
                    break;

                case 'show':
                    $this->get("/{$name}/{$parameter}", [
                        'controller' => $controller,
                        'action' => 'show'
                    ], [
                        'name' => $names['show'] ?? "{$name}.show"
                    ]);
                    break;

                case 'edit':
                    $this->get("/{$name}/{$parameter}/edit", [
                        'controller' => $controller,
                        'action' => 'edit'
                    ], [
                        'name' => $names['edit'] ?? "{$name}.edit"
                    ]);
                    break;

                case 'update':
                    $this->match(['PUT', 'PATCH'], "/{$name}/{$parameter}", [
                        'controller' => $controller,
                        'action' => 'update'
                    ], [
                        'name' => $names['update'] ?? "{$name}.update"
                    ]);
                    break;

                case 'destroy':
                    $this->delete("/{$name}/{$parameter}", [
                        'controller' => $controller,
                        'action' => 'destroy'
                    ], [
                        'name' => $names['destroy'] ?? "{$name}.destroy"
                    ]);
                    break;
            }
        }

        return $this;
    }

    /**
     * Créer des routes API de ressource (sans create/edit)
     *
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return self
     */
    public function apiResource(string $name, string $controller, array $options = []): self
    {
        $options['except'] = array_merge($options['except'] ?? [], ['create', 'edit']);
        return $this->resource($name, $controller, $options);
    }

    /**
     * Ajouter des routes pour les ascensions (spécifique à l'escalade)
     *
     * @param string $controller
     * @return self
     */
    public function ascentRoutes(string $controller): self
    {
        $this->group(['prefix' => 'routes/{route}'], function (RouteGroup $group) use ($controller) {
            $group->get('/log-ascent', [
                'controller' => $controller,
                'action' => 'logAscentForm'
            ], ['name' => 'routes.log-ascent']);

            $group->post('/log-ascent', [
                'controller' => $controller,
                'action' => 'storeAscent'
            ], ['name' => 'routes.store-ascent']);

            $group->get('/ascents', [
                'controller' => $controller,
                'action' => 'ascents'
            ], ['name' => 'routes.ascents']);
        });

        return $this;
    }

    /**
     * Ajouter des routes pour les commentaires
     *
     * @param string $controller
     * @param string $resource
     * @return self
     */
    public function commentRoutes(string $controller, string $resource = 'routes'): self
    {
        $this->group(['prefix' => "{$resource}/{id}"], function (RouteGroup $group) use ($controller) {
            $group->get('/comments', [
                'controller' => $controller,
                'action' => 'comments'
            ], ['name' => 'comments.index']);

            $group->post('/comments', [
                'controller' => $controller,
                'action' => 'storeComment'
            ], ['name' => 'comments.store']);
        });

        return $this;
    }

    /**
     * Ajouter des routes pour l'export (CSV, PDF, etc.)
     *
     * @param string $controller
     * @param string $resource
     * @return self
     */
    public function exportRoutes(string $controller, string $resource): self
    {
        $this->group(['prefix' => "{$resource}/export"], function (RouteGroup $group) use ($controller) {
            $group->get('/csv', [
                'controller' => $controller,
                'action' => 'exportCsv'
            ], ['name' => "{$resource}.export.csv"]);

            $group->get('/pdf', [
                'controller' => $controller,
                'action' => 'exportPdf'
            ], ['name' => "{$resource}.export.pdf"]);

            $group->get('/excel', [
                'controller' => $controller,
                'action' => 'exportExcel'
            ], ['name' => "{$resource}.export.excel"]);
        });

        return $this;
    }

    /**
     * Ajouter des routes de recherche avancée
     *
     * @param string $controller
     * @param string $resource
     * @return self
     */
    public function searchRoutes(string $controller, string $resource): self
    {
        $this->group(['prefix' => "{$resource}/search"], function (RouteGroup $group) use ($controller) {
            $group->get('/', [
                'controller' => $controller,
                'action' => 'search'
            ], ['name' => "{$resource}.search"]);

            $group->get('/suggestions', [
                'controller' => $controller,
                'action' => 'suggestions'
            ], ['name' => "{$resource}.search.suggestions"]);

            $group->post('/advanced', [
                'controller' => $controller,
                'action' => 'advancedSearch'
            ], ['name' => "{$resource}.search.advanced"]);
        });

        return $this;
    }

    /**
     * Ajouter des routes pour les webhooks
     *
     * @param string $controller
     * @return self
     */
    public function webhookRoutes(string $controller): self
    {
        $this->group(['prefix' => 'webhooks'], function (RouteGroup $group) use ($controller) {
            $group->post('/github', [
                'controller' => $controller,
                'action' => 'github'
            ], ['name' => 'webhooks.github']);

            $group->post('/stripe', [
                'controller' => $controller,
                'action' => 'stripe'
            ], ['name' => 'webhooks.stripe']);

            $group->post('/generic/{provider}', [
                'controller' => $controller,
                'action' => 'generic'
            ], ['name' => 'webhooks.generic']);
        });

        return $this;
    }

    /**
     * Ajouter une route au groupe avec tous les attributs du groupe
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return self
     */
    private function addRoute(string $method, string $path, mixed $handler, array $options = []): self
    {
        // Appliquer le préfixe
        $fullPath = $this->prefix . $path;

        // Fusionner les middlewares
        $middlewares = array_merge($this->middlewares, $options['middlewares'] ?? []);

        // Fusionner les contraintes
        $constraints = array_merge($this->constraints, $options['constraints'] ?? []);

        // Appliquer le namespace au contrôleur
        if (is_array($handler) && isset($handler['controller']) && $this->namespace) {
            $handler['controller'] = $this->namespace . '\\' . $handler['controller'];
        }

        // Préfixer le nom de la route
        $routeName = $options['name'] ?? null;
        if ($routeName && $this->name) {
            $routeName = $this->name . '.' . $routeName;
        }

        // Préparer les options finales
        $finalOptions = array_merge($options, [
            'middlewares' => $middlewares,
            'constraints' => $constraints,
            'name' => $routeName,
            'domain' => $options['domain'] ?? $this->domain ?: null
        ]);

        // Ajouter la route au routeur principal
        $this->router->addRoute($method, $fullPath, $handler, $finalOptions);

        return $this;
    }

    /**
     * Fusionner les attributs avec ceux du groupe parent
     *
     * @param array $attributes
     * @return array
     */
    private function mergeAttributes(array $attributes): array
    {
        return [
            'prefix' => $this->prefix . ($attributes['prefix'] ?? ''),
            'middlewares' => array_merge($this->middlewares, $attributes['middlewares'] ?? []),
            'namespace' => $this->namespace . ($attributes['namespace'] ? '\\' . $attributes['namespace'] : ''),
            'name' => $this->name . ($attributes['name'] ? '.' . $attributes['name'] : ''),
            'domain' => $attributes['domain'] ?? $this->domain,
            'constraints' => array_merge($this->constraints, $attributes['constraints'] ?? [])
        ];
    }

    /**
     * Obtenir les attributs du groupe
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Obtenir le préfixe du groupe
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Obtenir les middlewares du groupe
     *
     * @return array
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Obtenir le namespace du groupe
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
