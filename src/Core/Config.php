<?php

namespace TopoclimbCH\Core;

class Config
{
    /**
     * Charge les variables d'environnement et gère la compatibilité 
     * entre l'ancien et le nouveau format
     */
    public static function load(): void
    {
        // Assurer la compatibilité entre les anciennes et nouvelles variables
        if (!isset($_ENV['DB_HOST']) && isset($_ENV['DB_SERVER'])) {
            $_ENV['DB_HOST'] = $_ENV['DB_SERVER'];
        }
        
        if (!isset($_ENV['DB_DATABASE']) && isset($_ENV['DB_NAME'])) {
            $_ENV['DB_DATABASE'] = $_ENV['DB_NAME'];
        }
        
        if (!isset($_ENV['DB_USERNAME']) && isset($_ENV['DB_USER'])) {
            $_ENV['DB_USERNAME'] = $_ENV['DB_USER'];
        }
    }
    
    /**
     * Récupère une variable d'environnement avec une valeur par défaut
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}