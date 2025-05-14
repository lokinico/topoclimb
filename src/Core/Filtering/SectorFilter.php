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
        'book_id' => 'book_id',
        'exposure_id' => 'exposure_id',
        'altitude_min' => 'altitude_min',
        'altitude_max' => 'altitude_max',
        'month_id' => 'month_id',
        'quality' => 'quality',
        'search' => 'search'
    ];
    
    /**
     * Appliquer les filtres à une requête SQL
     * 
     * @param array $where Conditions existantes
     * @return array Conditions modifiées
     */
    public function apply(array $where = []): array
    {
        $conditions = $where;
        $parameters = [];
        $joins = [];
        
        // Filtres simples (correspondance exacte)
        if ($this->hasFilter('region_id')) {
            $conditions[] = "s.region_id = :region_id";
            $parameters['region_id'] = $this->getFilter('region_id');
        }
        
        if ($this->hasFilter('book_id')) {
            $conditions[] = "s.book_id = :book_id";
            $parameters['book_id'] = $this->getFilter('book_id');
        }
        
        // Exposition (nécessite une jointure)
        if ($this->hasFilter('exposure_id')) {
            $joins['exposure'] = "LEFT JOIN climbing_sector_exposures se ON s.id = se.sector_id";
            $conditions[] = "se.exposure_id = :exposure_id";
            $parameters['exposure_id'] = $this->getFilter('exposure_id');
        }
        
        // Mois et qualité (nécessite une jointure)
        if ($this->hasFilter('month_id') || $this->hasFilter('quality')) {
            $joins['month'] = "LEFT JOIN climbing_sector_months sm ON s.id = sm.sector_id";
            
            if ($this->hasFilter('month_id')) {
                $conditions[] = "sm.month_id = :month_id";
                $parameters['month_id'] = $this->getFilter('month_id');
            }
            
            if ($this->hasFilter('quality')) {
                if ($this->getFilter('quality') === 'excellent') {
                    $conditions[] = "sm.quality = 'excellent'";
                } elseif ($this->getFilter('quality') === 'good') {
                    $conditions[] = "sm.quality IN ('excellent', 'good')";
                } elseif ($this->getFilter('quality') === 'average') {
                    $conditions[] = "sm.quality IN ('excellent', 'good', 'average')";
                } elseif ($this->getFilter('quality') === 'poor') {
                    $conditions[] = "sm.quality = 'poor'";
                } elseif ($this->getFilter('quality') === 'avoid') {
                    $conditions[] = "sm.quality = 'avoid'";
                }
            }
        }
        
        // Filtres de plage pour l'altitude
        if ($this->hasFilter('altitude_min')) {
            $conditions[] = "s.altitude >= :altitude_min";
            $parameters['altitude_min'] = (int)$this->getFilter('altitude_min');
        }
        
        if ($this->hasFilter('altitude_max')) {
            $conditions[] = "s.altitude <= :altitude_max";
            $parameters['altitude_max'] = (int)$this->getFilter('altitude_max');
        }
        
        // Recherche par nom ou description
        if ($this->hasFilter('search')) {
            $searchTerm = '%' . $this->getFilter('search') . '%';
            $conditions[] = "(s.name LIKE :search OR s.description LIKE :search OR s.code LIKE :search)";
            $parameters['search'] = $searchTerm;
        }
        
        return [
            'conditions' => $conditions,
            'parameters' => $parameters,
            'joins' => $joins
        ];
    }
    
    /**
     * Filtrer un tableau de secteurs
     * 
     * @param array $sectors Tableau d'objets ou tableaux de secteurs
     * @return array Secteurs filtrés
     */
    public function filterResults(array $sectors): array
    {
        if (empty($this->params)) {
            return $sectors;
        }
        
        return array_filter($sectors, function ($sector) {
            $isObject = is_object($sector);
            
            // Filtrer par altitude min/max
            if ($this->hasFilter('altitude_min')) {
                $altitude = $isObject ? $sector->altitude : $sector['altitude'];
                if (!isset($altitude) || $altitude < (int)$this->getFilter('altitude_min')) {
                    return false;
                }
            }
            
            if ($this->hasFilter('altitude_max')) {
                $altitude = $isObject ? $sector->altitude : $sector['altitude'];
                if (!isset($altitude) || $altitude > (int)$this->getFilter('altitude_max')) {
                    return false;
                }
            }
            
            // Filtrer par région
            if ($this->hasFilter('region_id')) {
                $regionId = $isObject ? $sector->region_id : $sector['region_id'];
                if ($regionId != $this->getFilter('region_id')) {
                    return false;
                }
            }
            
            // Filtrer par livre/site
            if ($this->hasFilter('book_id')) {
                $bookId = $isObject ? $sector->book_id : $sector['book_id'];
                if ($bookId != $this->getFilter('book_id')) {
                    return false;
                }
            }
            
            // Filtrer par recherche de texte
            if ($this->hasFilter('search')) {
                $search = strtolower($this->getFilter('search'));
                $name = strtolower($isObject ? $sector->name : $sector['name']);
                $description = strtolower($isObject ? ($sector->description ?? '') : ($sector['description'] ?? ''));
                $code = strtolower($isObject ? ($sector->code ?? '') : ($sector['code'] ?? ''));
                
                if (strpos($name, $search) === false && 
                    strpos($description, $search) === false && 
                    strpos($code, $search) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }
    
    /**
     * Vérifier si des filtres sont actifs
     * 
     * @return bool
     */
    public function hasActiveFilters(): bool
    {
        foreach ($this->filters as $key => $value) {
            if ($this->hasFilter($key)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Générer l'URL de suppression d'un filtre
     * 
     * @param string $filter Nom du filtre à supprimer
     * @param string $baseUrl URL de base
     * @return string URL sans le filtre spécifié
     */
    public function getRemoveFilterUrl(string $filter, string $baseUrl): string
    {
        $params = $this->params;
        unset($params[$filter]);
        
        if (empty($params)) {
            return $baseUrl;
        }
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Génère l'URL pour réinitialiser tous les filtres
     * 
     * @param string $baseUrl URL de base
     * @return string URL sans filtres
     */
    public function getResetUrl(string $baseUrl): string
    {
        return $baseUrl;
    }
}