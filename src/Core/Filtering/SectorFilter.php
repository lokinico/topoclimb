<?php
// src/Core/Filtering/SectorFilter.php

namespace TopoclimbCH\Core\Filtering;

class SectorFilter extends Filter
{
    /**
     * Définition des filtres disponibles pour les secteurs
     */
    protected array $filters = [
        'region_id' => 'region_id',
        'exposure_id' => 'exposure_id',
        'altitude_min' => 'altitude_min',
        'altitude_max' => 'altitude_max',
        'month_id' => 'month_id',
        'quality' => 'quality',
        'search' => 'search'
    ];
    
    /**
     * Appliquer les filtres à une requête SQL
     */
    public function apply(array $where = []): array
    {
        $conditions = $where;
        
        // Filtres simples (correspondance exacte)
        if ($this->hasFilter('region_id')) {
            $conditions['region_id'] = $this->getFilter('region_id');
        }
        
        // Filtres plus complexes
        // Exposition (nécessite une jointure)
        if ($this->hasFilter('exposure_id')) {
            // À implémenter via une méthode spéciale dans le modèle Sector
        }
        
        // Mois et qualité (nécessite une jointure)
        if ($this->hasFilter('month_id') || $this->hasFilter('quality')) {
            // À implémenter via une méthode spéciale dans le modèle Sector
        }
        
        // Filtres de plage pour l'altitude
        if ($this->hasFilter('altitude_min') || $this->hasFilter('altitude_max')) {
            // À implémenter via une méthode spéciale dans le modèle Sector
        }
        
        // Recherche par nom
        if ($this->hasFilter('search')) {
            // À implémenter via une méthode spéciale dans le modèle Sector
        }
        
        return $conditions;
    }
    
    /**
     * Filtrer un tableau de secteurs
     */
    public function filterResults(array $sectors): array
    {
        if (empty($this->params)) {
            return $sectors;
        }
        
        return array_filter($sectors, function ($sector) {
            // Filtrer par altitude min/max
            if ($this->hasFilter('altitude_min') && $sector->altitude < $this->getFilter('altitude_min')) {
                return false;
            }
            
            if ($this->hasFilter('altitude_max') && $sector->altitude > $this->getFilter('altitude_max')) {
                return false;
            }
            
            // Filtrer par recherche de texte
            if ($this->hasFilter('search')) {
                $search = strtolower($this->getFilter('search'));
                $name = strtolower($sector->name);
                
                if (strpos($name, $search) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }
}