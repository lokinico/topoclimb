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
                $conditions[] = "sm.quality = :quality";
                $parameters['quality'] = $this->getFilter('quality');
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
        
        // Recherche par nom
        if ($this->hasFilter('search')) {
            $conditions[] = "s.name LIKE :search OR s.description LIKE :search";
            $parameters['search'] = '%' . $this->getFilter('search') . '%';
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
            
            // Filtrer par recherche de texte
            if ($this->hasFilter('search')) {
                $search = strtolower($this->getFilter('search'));
                $name = strtolower($isObject ? $sector->name : $sector['name']);
                $description = strtolower($isObject ? ($sector->description ?? '') : ($sector['description'] ?? ''));
                
                if (strpos($name, $search) === false && strpos($description, $search) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }
}