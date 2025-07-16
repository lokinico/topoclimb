<?php

namespace TopoclimbCH\Core\Routing;

use ReflectionClass;
use ReflectionMethod;
use TopoclimbCH\Core\Routing\Route;
use TopoclimbCH\Core\Routing\Middleware;
use TopoclimbCH\Core\Routing\Group;

/**
 * Loads routes from PHP attributes
 */
class AttributeRouteLoader
{
    private array $routes = [];
    
    /**
     * Load routes from a controller class
     */
    public function loadFromController(string $controllerClass): array
    {
        if (!class_exists($controllerClass)) {
            return [];
        }
        
        $reflection = new ReflectionClass($controllerClass);
        $routes = [];
        
        // Get class-level attributes
        $classGroup = $this->getClassGroup($reflection);
        $classMiddlewares = $this->getClassMiddlewares($reflection);
        
        // Process each method
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodRoutes = $this->loadFromMethod($method, $controllerClass, $classGroup, $classMiddlewares);
            $routes = array_merge($routes, $methodRoutes);
        }
        
        return $routes;
    }
    
    /**
     * Load routes from all controllers in a directory
     */
    public function loadFromDirectory(string $directory, string $namespace = ''): array
    {
        $routes = [];
        
        if (!is_dir($directory)) {
            return $routes;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = substr($file->getPath(), strlen($directory));
                $relativeNamespace = str_replace('/', '\\', $relativePath);
                
                $className = $namespace . ($relativeNamespace ? $relativeNamespace . '\\' : '') . $file->getBasename('.php');
                
                if (class_exists($className)) {
                    $controllerRoutes = $this->loadFromController($className);
                    $routes = array_merge($routes, $controllerRoutes);
                }
            }
        }
        
        return $routes;
    }
    
    /**
     * Get class-level group configuration
     */
    private function getClassGroup(ReflectionClass $reflection): ?Group
    {
        $attributes = $reflection->getAttributes(Group::class);
        
        if (empty($attributes)) {
            return null;
        }
        
        return $attributes[0]->newInstance();
    }
    
    /**
     * Get class-level middlewares
     */
    private function getClassMiddlewares(ReflectionClass $reflection): array
    {
        $middlewares = [];
        $attributes = $reflection->getAttributes(Middleware::class);
        
        foreach ($attributes as $attribute) {
            $middleware = $attribute->newInstance();
            $middlewares = array_merge($middlewares, $middleware->middleware);
        }
        
        return $middlewares;
    }
    
    /**
     * Load routes from a method
     */
    private function loadFromMethod(
        ReflectionMethod $method, 
        string $controllerClass, 
        ?Group $classGroup, 
        array $classMiddlewares
    ): array {
        $routes = [];
        $routeAttributes = $method->getAttributes(Route::class);
        
        if (empty($routeAttributes)) {
            return $routes;
        }
        
        // Get method-level middlewares
        $methodMiddlewares = [];
        $middlewareAttributes = $method->getAttributes(Middleware::class);
        
        foreach ($middlewareAttributes as $attribute) {
            $middleware = $attribute->newInstance();
            $methodMiddlewares = array_merge($methodMiddlewares, $middleware->middleware);
        }
        
        // Process each route attribute
        foreach ($routeAttributes as $routeAttribute) {
            $route = $routeAttribute->newInstance();
            
            // Build full path
            $fullPath = $this->buildFullPath($classGroup?->prefix ?? '', $route->path);
            
            // Merge middlewares (class first, then method, then route)
            $allMiddlewares = array_merge(
                $classGroup?->middlewares ?? [],
                $classMiddlewares,
                $methodMiddlewares,
                $route->middlewares
            );
            
            // Convert to old format for compatibility
            foreach ($route->methods as $httpMethod) {
                $routes[] = [
                    'method' => $httpMethod,
                    'path' => $fullPath,
                    'controller' => $controllerClass,
                    'action' => $method->getName(),
                    'middlewares' => $this->resolveMiddlewareClasses($allMiddlewares),
                    'name' => $route->name ?: $this->generateRouteName($controllerClass, $method->getName()),
                    'requirements' => $route->requirements,
                    'defaults' => $route->defaults,
                    'source' => 'attribute' // Mark as attribute-based route
                ];
            }
        }
        
        return $routes;
    }
    
    /**
     * Build full path from prefix and route path
     */
    private function buildFullPath(string $prefix, string $path): string
    {
        $prefix = rtrim($prefix, '/');
        $path = '/' . ltrim($path, '/');
        
        return $prefix . $path;
    }
    
    /**
     * Generate route name from controller and method
     */
    private function generateRouteName(string $controllerClass, string $methodName): string
    {
        $className = basename(str_replace('\\', '/', $controllerClass));
        $className = str_replace('Controller', '', $className);
        
        return strtolower($className) . '.' . strtolower($methodName);
    }
    
    /**
     * Resolve middleware class names
     */
    private function resolveMiddlewareClasses(array $middlewares): array
    {
        $resolved = [];
        
        foreach ($middlewares as $middleware) {
            if (is_string($middleware)) {
                // If it's a short name, resolve to full class name
                if (!str_contains($middleware, '\\')) {
                    $resolved[] = "TopoclimbCH\\Middleware\\{$middleware}";
                } else {
                    $resolved[] = $middleware;
                }
            } else {
                $resolved[] = $middleware;
            }
        }
        
        return array_unique($resolved);
    }
}