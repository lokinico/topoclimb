<?php
// src/Models/Country.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Country extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'climbing_countries';
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'name', 'code', 'description', 'active'
    ];
    
    /**
     * Règles de validation
     */
    protected array $rules = [
        'name' => 'required|max:100',
        'code' => 'max:2',
        'active' => 'in:0,1'
    ];
    
    /**
     * Relation avec les régions
     */
    public function regions(): array
    {
        return $this->hasMany(Region::class, 'country_id');
    }
    
    /**
     * Obtenir le nombre de régions actives
     */
    public function getActiveRegionsCount(): int
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT COUNT(*) FROM climbing_regions 
                 WHERE country_id = ? AND active = 1"
            );
            $stmt->execute([$this->id]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new ModelException("Error counting active regions: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir le nombre total de sites
     */
    public function getSitesCount(): int
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT COUNT(*) FROM climbing_sites s
                 JOIN climbing_regions r ON s.region_id = r.id
                 WHERE r.country_id = ? AND s.active = 1"
            );
            $stmt->execute([$this->id]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new ModelException("Error counting sites: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir le nombre total de secteurs
     */
    public function getSectorsCount(): int
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT COUNT(*) FROM climbing_sectors s
                 JOIN climbing_regions r ON s.region_id = r.id
                 WHERE r.country_id = ? AND s.active = 1"
            );
            $stmt->execute([$this->id]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new ModelException("Error counting sectors: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir le nombre total de voies
     */
    public function getRoutesCount(): int
    {
        try {
            $stmt = static::getConnection()->prepare(
                "SELECT COUNT(*) FROM climbing_routes r
                 JOIN climbing_sectors s ON r.sector_id = s.id
                 JOIN climbing_regions reg ON s.region_id = reg.id
                 WHERE reg.country_id = ? AND r.active = 1"
            );
            $stmt->execute([$this->id]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new ModelException("Error counting routes: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir des statistiques générales pour le pays
     */
    public function getStatistics(): array
    {
        return [
            'regions_count' => $this->getActiveRegionsCount(),
            'sites_count' => $this->getSitesCount(),
            'sectors_count' => $this->getSectorsCount(),
            'routes_count' => $this->getRoutesCount(),
        ];
    }
    
    /**
     * Obtenir tous les pays actifs
     */
    public static function getActive(): array
    {
        return static::where(['active' => 1]);
    }
    
    /**
     * Accesseur pour l'emoji du drapeau du pays
     */
    public function getFlagEmojiAttribute(): string
    {
        if (empty($this->attributes['code'])) {
            return '';
        }
        
        // Conversion du code ISO en caractères Unicode pour l'emoji
        $codePoints = [];
        $chars = str_split(strtoupper($this->attributes['code']));
        
        foreach ($chars as $char) {
            $codePoints[] = ord($char) + 127397; // 127397 est l'offset pour convertir A-Z en drapeaux régionaux
        }
        
        return implode('', array_map(function($cp) {
            return mb_chr($cp, 'UTF-8');
        }, $codePoints));
    }
}