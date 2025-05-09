<?php
// src/Core/Container.php

namespace TopoclimbCH\Core;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Container
{
    private static ?Container $instance = null;
    private ContainerInterface $container;

    /**
     * Constructeur privé pour le Singleton
     */
    private function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Récupère l'instance unique
     */
    public static function getInstance(ContainerInterface $container = null): self
    {
        if (self::$instance === null) {
            if ($container === null) {
                throw new \InvalidArgumentException('Container must be provided first time');
            }
            self::$instance = new self($container);
        }
        return self::$instance;
    }

    /**
     * Récupère un service depuis le conteneur
     */
    public function get(string $id)
    {
        try {
            return $this->container->get($id);
        } catch (ServiceNotFoundException $e) {
            throw new \InvalidArgumentException("Service $id not found", 0, $e);
        }
    }

    /**
     * Vérifie si un service existe
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Méthodes d'usine pour construire un service avec des dépendances manuelles
     */
    public function make(string $class, array $parameters = [])
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }
        
        // Utilise Reflection pour construire le service
        $reflector = new \ReflectionClass($class);
        
        if (!$reflector->isInstantiable()) {
            throw new \InvalidArgumentException("Class $class is not instantiable");
        }
        
        $constructor = $reflector->getConstructor();
        
        if (null === $constructor) {
            return new $class();
        }
        
        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            
            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
                continue;
            }
            
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }
            
            if ($type && !$type->isBuiltin() && $this->has($type->getName())) {
                $dependencies[] = $this->get($type->getName());
                continue;
            }
            
            throw new \InvalidArgumentException("Cannot resolve parameter $name of $class");
        }
        
        return $reflector->newInstanceArgs($dependencies);
    }
}