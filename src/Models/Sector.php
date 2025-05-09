<?php
// src/Models/Sector.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Sector extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_sectors';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'book_id', 'region_id', 'name', 'code', 'description', 'access_info', 
        'color', 'access_time', 'altitude', 'approach', 'height', 
        'parking_info', 'coordinates_lat', 'coordinates_lng',
        'coordinates_swiss_e', 'coordinates_swiss_n', 'active'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'book_id' => 'required|numeric',
        'name' => 'required|max:255',
        'code' => 'required|max:50',
        'coordinates_lat' => 'numeric',
        'coordinates_lng' => 'numeric',
        'altitude' => 'numeric',
        'active' => 'in:0,1'
    ];
    
    /**
     * Relation avec les voies d'escalade
     */
    public function routes(): array
    {
        return $this->hasMany(Route::class);
    }
    
    /**
     * Relation avec la région
     */
    public function region(): ?Region
    {
        return $this->belongsTo(Region::class);
    }
    
    /**
     * Relation avec les expositions
     */
    public function exposures(): array
    {
        return $this->belongsToMany(
            Exposure::class, 
            'climbing_sector_exposures', 
            'sector_id', 
            'exposure_id'
        );
    }
    
    /**
     * Relation avec les mois (qualité par mois)
     */
    public function months(): array
    {
        return $this->belongsToMany(
            Month::class, 
            'climbing_sector_months', 
            'sector_id', 
            'month_id'
        );
    }
    
    /**
     * Récupère les parkings associés au secteur
     */
    public function parkings(): array
    {
        return $this->belongsToMany(
            Parking::class, 
            'parking_secteur', 
            'secteur_id', 
            'parking_id'
        );
    }
    
    /**
     * Accesseur pour le temps d'accès formaté
     */
    public function getAccessTimeFormattedAttribute($value): string
    {
        $time = $this->getAttribute('access_time');
        
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
     * Mutateur pour le champ active
     */
    public function setActiveAttribute($value): bool
    {
        return (bool) $value;
    }
    
    /**
     * Récupère les secteurs actifs
     */
    public static function active(): array
    {
        return static::where(['active' => 1]);
    }
    
    /**
     * Méthode pour vérifier si le secteur a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return isset($this->attributes['coordinates_lat']) && 
               isset($this->attributes['coordinates_lng']) &&
               $this->attributes['coordinates_lat'] !== null &&
               $this->attributes['coordinates_lng'] !== null;
    }
    
    /**
     * Méthode pour obtenir l'URL Google Maps
     */
    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->hasCoordinates()) {
            return null;
        }
        
        $lat = $this->attributes['coordinates_lat'];
        $lng = $this->attributes['coordinates_lng'];
        
        return "https://www.google.com/maps?q={$lat},{$lng}";
    }
    
    /**
     * Événement avant la sauvegarde
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
     */
    protected function generateUniqueCode(string $baseCode): string
    {
        $code = $baseCode;
        $counter = 1;
        
        while (true) {
            // Vérifier si le code existe déjà
            $existing = self::findWhere(['code' => $code]);
            
            // Si le code n'existe pas ou s'il appartient à ce secteur, l'utiliser
            if ($existing === null || ($existing->id === ($this->id ?? null))) {
                return $code;
            }
            
            // Sinon, générer un nouveau code avec un compteur
            $code = "{$baseCode}-{$counter}";
            $counter++;
        }
    }
}