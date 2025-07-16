<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Models\Sector;
use TopoclimbCH\Models\Country;

class Region extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_regions';

    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'country_id',
        'name',
        'description',
        'coordinates_lat',        // ← AJOUTÉ
        'coordinates_lng',        // ← AJOUTÉ  
        'altitude',               // ← AJOUTÉ
        'best_season',            // ← AJOUTÉ
        'access_info',            // ← AJOUTÉ
        'parking_info',           // ← AJOUTÉ
        'active',
        'created_by',
        'updated_by'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'country_id' => 'required|numeric',
        'name' => 'required|min:2|max:100',
        'description' => 'nullable|max:2000',
        'coordinates_lat' => 'nullable|numeric|between:-90,90',
        'coordinates_lng' => 'nullable|numeric|between:-180,180',
        'altitude' => 'nullable|numeric|min:0|max:5000',
        'best_season' => 'nullable|in:spring,summer,autumn,winter,year-round',
        'access_info' => 'nullable|max:1000',
        'parking_info' => 'nullable|max:1000'
    ];

    /**
     * Accesseurs pour formater les données
     */

    /**
     * Formater les coordonnées pour l'affichage
     */
    public function getCoordinatesDisplayAttribute(): ?string
    {
        if ($this->coordinates_lat && $this->coordinates_lng) {
            return number_format($this->coordinates_lat, 6) . ', ' . number_format($this->coordinates_lng, 6);
        }
        return null;
    }

    /**
     * Générer l'URL Google Maps
     */
    public function getGoogleMapsUrlAttribute(): ?string
    {
        if ($this->coordinates_lat && $this->coordinates_lng) {
            return "https://www.google.com/maps/search/?api=1&query={$this->coordinates_lat},{$this->coordinates_lng}";
        }
        return null;
    }

    /**
     * Formater l'altitude pour l'affichage
     */
    public function getAltitudeDisplayAttribute(): ?string
    {
        if ($this->altitude) {
            return $this->altitude . 'm';
        }
        return null;
    }

    /**
     * Obtenir la saison optimale en français
     */
    public function getBestSeasonDisplayAttribute(): ?string
    {
        $seasons = [
            'spring' => 'Printemps',
            'summer' => 'Été',
            'autumn' => 'Automne',
            'winter' => 'Hiver',
            'year-round' => 'Toute l\'année'
        ];

        return $seasons[$this->best_season] ?? null;
    }

    /**
     * Vérifier si la région a des coordonnées valides
     */
    public function hasCoordinatesAttribute(): bool
    {
        return !empty($this->coordinates_lat) && !empty($this->coordinates_lng);
    }

    /**
     * Valider les coordonnées suisses
     */
    public function isSwissCoordinatesAttribute(): bool
    {
        if (!$this->hasCoordinates) {
            return false;
        }

        // Bounds approximatives de la Suisse
        return $this->coordinates_lat >= 45.8 && $this->coordinates_lat <= 47.9 &&
            $this->coordinates_lng >= 5.9 && $this->coordinates_lng <= 10.6;
    }

    /**
     * Relations
     */

    /**
     * Relation avec les secteurs
     */
    public function sectors(): array
    {
        return $this->hasMany(Sector::class, 'region_id');
    }

    /**
     * Relation avec le pays
     */
    public function country(): ?Country
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Obtenir les secteurs actifs seulement
     */
    public function activeSectors(): array
    {
        return $this->hasMany(Sector::class, 'region_id', ['active' => 1]);
    }

    /**
     * Méthodes d'aide
     */

    /**
     * Compter le nombre de secteurs
     */
    public function getSectorsCount(): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_sectors WHERE region_id = ? AND active = 1",
            [$this->id]
        );
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Compter le nombre de voies
     */
    public function getRoutesCount(): int
    {
        $result = $this->db->fetchOne("
            SELECT COUNT(r.id) as count 
            FROM climbing_routes r
            JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.region_id = ? AND r.active = 1 AND s.active = 1
        ", [$this->id]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Obtenir la difficulté moyenne
     */
    public function getAverageDifficulty(): ?float
    {
        $result = $this->db->fetchOne("
            SELECT AVG(CAST(r.difficulty AS DECIMAL(4,2))) as avg_difficulty
            FROM climbing_routes r
            JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.region_id = ? AND r.active = 1 AND s.active = 1
            AND r.difficulty IS NOT NULL AND r.difficulty != ''
        ", [$this->id]);

        if ($result && $result['avg_difficulty']) {
            return round((float) $result['avg_difficulty'], 1);
        }
        return null;
    }

    /**
     * Obtenir l'altitude minimale et maximale des secteurs
     */
    public function getAltitudeRange(): array
    {
        $result = $this->db->fetchOne("
            SELECT 
                MIN(altitude) as min_altitude,
                MAX(altitude) as max_altitude
            FROM climbing_sectors 
            WHERE region_id = ? AND active = 1 AND altitude IS NOT NULL
        ", [$this->id]);

        return [
            'min' => $result['min_altitude'] ? (int) $result['min_altitude'] : null,
            'max' => $result['max_altitude'] ? (int) $result['max_altitude'] : null
        ];
    }

    /**
     * Lifecycle hooks
     */

    /**
     * Actions avant sauvegarde
     */
    protected function onSaving(): void
    {
        // Valider les coordonnées si elles sont fournies
        if ($this->coordinates_lat && $this->coordinates_lng) {
            $lat = (float) $this->coordinates_lat;
            $lng = (float) $this->coordinates_lng;

            if ($lat < -90 || $lat > 90) {
                throw new \InvalidArgumentException('Latitude invalide (doit être entre -90 et 90)');
            }

            if ($lng < -180 || $lng > 180) {
                throw new \InvalidArgumentException('Longitude invalide (doit être entre -180 et 180)');
            }
        }

        // Valider l'altitude
        if ($this->altitude && ($this->altitude < 0 || $this->altitude > 5000)) {
            throw new \InvalidArgumentException('Altitude invalide (doit être entre 0 et 5000m)');
        }

        // S'assurer que active est un booléen
        if (isset($this->attributes['active'])) {
            $this->attributes['active'] = $this->attributes['active'] ? 1 : 0;
        }
    }

    /**
     * Convertir en tableau pour l'API
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        // Ajouter les attributs calculés
        $data['coordinates_display'] = $this->getCoordinatesDisplayAttribute();
        $data['google_maps_url'] = $this->getGoogleMapsUrlAttribute();
        $data['altitude_display'] = $this->getAltitudeDisplayAttribute();
        $data['best_season_display'] = $this->getBestSeasonDisplayAttribute();
        $data['has_coordinates'] = $this->hasCoordinatesAttribute();
        $data['is_swiss_coordinates'] = $this->isSwissCoordinatesAttribute();

        // Ajouter les compteurs si l'ID est défini
        if ($this->id) {
            $data['sectors_count'] = $this->getSectorsCount();
            $data['routes_count'] = $this->getRoutesCount();
            $data['average_difficulty'] = $this->getAverageDifficulty();
            $data['altitude_range'] = $this->getAltitudeRange();
        }

        return $data;
    }
}
