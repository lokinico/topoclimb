<?php
// src/Core/Container.php

namespace TopoclimbCH\Core;

use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Container
{
    private static ?Container $instance = null;
    private SymfonyContainerBuilder $container;

    /**
     * Constructeur privé pour le Singleton
     */
    private function __construct(SymfonyContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Récupère l'instance unique
     */
    public static function getInstance(SymfonyContainerBuilder $container = null): self
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
            // Tenter de faire une recherche non-stricte (important pour les contrôleurs)
            // par exemple, si on demande "HomeController" au lieu de "TopoclimbCH\Controllers\HomeController"
            if (strpos($id, '\\') === false) {
                $fullId = "TopoclimbCH\\Controllers\\{$id}";
                if ($this->container->has($fullId)) {
                    return $this->container->get($fullId);
                }
            }
            
            throw new \InvalidArgumentException("Service $id not found", 0, $e);
        }
    }

    /**
     * Vérifie si un service existe
     */
    public function has(string $id): bool
    {
        // Vérifier aussi avec le namespace complet
        if (strpos($id, '\\') === false) {
            $fullId = "TopoclimbCH\\Controllers\\{$id}";
            if ($this->container->has($fullId)) {
                return true;
            }
        }
        
        return $this->container->has($id);
    }
}