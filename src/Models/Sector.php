<?php
// src/Models/Sector.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Exceptions\ModelException;

class Sector extends Model
{
    // Code existant conservé...
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'book_id' => 'required|numeric',
        'name' => 'required|max:255',
        'code' => 'required|max:50',
        'coordinates_lat' => 'nullable|numeric|min:-90|max:90',
        'coordinates_lng' => 'nullable|numeric|min:-180|max:180',
        'altitude' => 'nullable|numeric|min:0|max:9000',
        'active' => 'in:0,1'
    ];
    
    /**
     * Accesseur pour le temps d'accès formaté
     */
    public function getAccessTimeFormattedAttribute(): string
    {
        $time = $this->attributes['access_time'] ?? null;
        
        if ($time === null) {
            return 'Non spécifié';
        }
        
        if ($time < 60) {
            return "{$time} minutes";
        }
        
        $hours = floor($time / 60);
        $minutes = $time % 60;
        
        if ($minutes === 0) {
            return "{$hours} heure" . ($hours > 1 ? 's' : '');
        }
        
        return "{$hours}h{$minutes}";
    }
    
    /**
     * Événement avant la sauvegarde
     * 
     * @throws ModelException
     * @return bool
     */
    protected function onSaving(): bool
    {
        // S'assurer que le code est unique
        if (isset($this->attributes['code'])) {
            $this->attributes['code'] = $this->generateUniqueCode($this->attributes['code']);
        }
        
        return true;
    }
    
    /**
     * Génère un code unique
     * 
     * @param string $baseCode
     * @return string
     * @throws ModelException
     */
    protected function generateUniqueCode(string $baseCode): string
    {
        $code = $baseCode;
        $counter = 1;
        $maxAttempts = 100; // Éviter une boucle infinie
        
        while ($counter <= $maxAttempts) {
            // Vérifier si le code existe déjà
            $sql = "SELECT id FROM " . static::$table . " WHERE code = ?";
            if (isset($this->id)) {
                $sql .= " AND id != ?";
                $params = [$code, $this->id];
            } else {
                $params = [$code];
            }
            
            $existing = self::getConnection()->fetchOne($sql, $params);
            
            // Si le code n'existe pas, l'utiliser
            if (!$existing) {
                return $code;
            }
            
            // Sinon, générer un nouveau code avec un compteur
            $code = "{$baseCode}-{$counter}";
            $counter++;
        }
        
        throw new ModelException("Impossible de générer un code unique après {$maxAttempts} tentatives pour '{$baseCode}'");
    }
    
    /**
     * Valide les coordonnées géographiques
     * 
     * @return bool
     */
    public function validateCoordinates(): bool
    {
        if (!$this->hasCoordinates()) {
            return true; // Pas de coordonnées à valider
        }
        
        $lat = $this->attributes['coordinates_lat'];
        $lng = $this->attributes['coordinates_lng'];
        
        return is_numeric($lat) && is_numeric($lng) && 
               $lat >= -90 && $lat <= 90 && 
               $lng >= -180 && $lng <= 180;
    }
}