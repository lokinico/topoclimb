<?php
// src/Core/Filtering/Filter.php

namespace TopoclimbCH\Core\Filtering;

abstract class Filter
{
    /**
     * Paramètres de filtrage
     */
    protected array $params = [];
    
    /**
     * Définition des filtres disponibles
     */
    protected array $filters = [];
    
    /**
     * Constructeur
     *
     * @param array $params Paramètres de filtrage
     */
    public function __construct(array $params = [])
    {
        $this->params = $this->sanitizeParams($params);
    }
    
    /**
     * Nettoyer les paramètres
     */
    protected function sanitizeParams(array $params): array
    {
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            // Ignorer les paramètres vides
            if (empty($value) && $value !== '0') {
                continue;
            }
            
            // Vérifier que le filtre est défini
            if (isset($this->filters[$key])) {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Obtenir les paramètres de filtrage
     */
    public function getParams(): array
    {
        return $this->params;
    }
    
    /**
     * Vérifier si un filtre est actif
     */
    public function hasFilter(string $name): bool
    {
        return isset($this->params[$name]);
    }
    
    /**
     * Obtenir la valeur d'un filtre
     */
    public function getFilter(string $name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }
    
    /**
     * Appliquer les filtres à une requête SQL
     *
     * @param array $where Tableau de conditions WHERE
     * @return array Conditions WHERE avec filtres appliqués
     */
    public function apply(array $where = []): array
    {
        $conditions = $where;
        
        foreach ($this->params as $name => $value) {
            if (isset($this->filters[$name])) {
                $filter = $this->filters[$name];
                
                if (is_callable($filter)) {
                    // Appliquer une fonction de filtrage personnalisée
                    $conditions = $filter($conditions, $value);
                } else {
                    // Filtre simple (correspondance exacte)
                    $conditions[$filter] = $value;
                }
            }
        }
        
        return $conditions;
    }
    
    /**
     * Convertir les paramètres de filtrage en chaîne de requête
     */
    public function toQueryString(): string
    {
        return http_build_query($this->params);
    }
    
    /**
     * Obtenir l'URL de filtrage
     */
    public function getFilterUrl(string $baseUrl, array $overrides = []): string
    {
        $params = array_merge($this->params, $overrides);
        
        // Retirer les paramètres vides
        $params = array_filter($params, function ($value) {
            return $value !== null && $value !== '';
        });
        
        return $baseUrl . '?' . http_build_query($params);
    }
}