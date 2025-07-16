<?php
// src/Core/Filtering/RouteFilter.php

namespace TopoclimbCH\Core\Filtering;

class RouteFilter extends Filter
{
    /**
     * Définition des filtres disponibles pour les routes
     */
    protected array $filters = [
        'sector_id' => 'sector_id',
        'difficulty' => 'difficulty',
        'style' => 'style',
        'beauty' => 'beauty',
        'length_min' => 'length_min',
        'length_max' => 'length_max',
        'difficulty_system_id' => 'difficulty_system_id',
        'search' => 'search'
    ];
    
    /**
     * Appliquer les filtres à une requête SQL
     */
    public function apply(array $where = []): array
    {
        $conditions = $where;
        
        // Filtres simples (correspondance exacte)
        if ($this->hasFilter('sector_id')) {
            $conditions['sector_id'] = $this->getFilter('sector_id');
        }
        
        if ($this->hasFilter('difficulty')) {
            $conditions['difficulty'] = $this->getFilter('difficulty');
        }
        
        if ($this->hasFilter('style')) {
            $conditions['style'] = $this->getFilter('style');
        }
        
        if ($this->hasFilter('beauty')) {
            $conditions['beauty'] = $this->getFilter('beauty');
        }
        
        if ($this->hasFilter('difficulty_system_id')) {
            $conditions['difficulty_system_id'] = $this->getFilter('difficulty_system_id');
        }
        
        // Filtres de plage pour la longueur
        if ($this->hasFilter('length_min')) {
            $table = \TopoclimbCH\Models\Route::getTable();
            
            // Cette partie nécessitera une requête SQL personnalisée
            // Pour l'instant, nous allons simplement filtrer après récupération
            // Dans un cas réel, vous voudrez probablement implémenter une méthode findWithRawWhere()
            // dans votre classe Model
        }
        
        if ($this->hasFilter('length_max')) {
            // Même chose ici pour length_max
        }
        
        // Recherche par nom (à implémenter dans le modèle)
        if ($this->hasFilter('search')) {
            // Implémentation de la recherche par nom
        }
        
        return $conditions;
    }
    
    /**
     * Filtrer un tableau de routes
     */
    public function filterResults(array $routes): array
    {
        if (empty($this->params)) {
            return $routes;
        }
        
        return array_filter($routes, function ($route) {
            // Filtrer par longueur min/max
            if ($this->hasFilter('length_min') && $route->length < $this->getFilter('length_min')) {
                return false;
            }
            
            if ($this->hasFilter('length_max') && $route->length > $this->getFilter('length_max')) {
                return false;
            }
            
            // Filtrer par recherche de texte
            if ($this->hasFilter('search')) {
                $search = strtolower($this->getFilter('search'));
                $name = strtolower($route->name);
                
                if (strpos($name, $search) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }
}