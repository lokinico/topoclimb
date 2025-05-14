<?php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class Country extends Model
{
    /**
     * Table associée au modèle
     */
    protected string $table = 'climbing_countries';

    /**
     * Champs remplissables en masse
     */
    protected array $fillable = [
        'name', 'code', 'description', 'active'
    ];

    /**
     * Règles de validation
     */
    protected array $rules = [
        'name' => 'required|max:100',
        'code' => 'max:2'
    ];

    /**
     * Relation avec les régions
     */
    public function regions()
    {
        return $this->hasMany(Region::class, 'country_id');
    }

    /**
     * Obtenir le nombre de régions actives
     */
    public function getActiveRegionsCount(): int
    {
        return $this->db()->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_regions 
             WHERE country_id = ? AND active = 1",
            [$this->id]
        )['count'] ?? 0;
    }

    /**
     * Obtenir le nombre total de sites
     */
    public function getSitesCount(): int
    {
        return $this->db()->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_sites s
             JOIN climbing_regions r ON s.region_id = r.id
             WHERE r.country_id = ? AND s.active = 1",
            [$this->id]
        )['count'] ?? 0;
    }

    /**
     * Obtenir le nombre total de secteurs
     */
    public function getSectorsCount(): int
    {
        return $this->db()->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_sectors s
             JOIN climbing_regions r ON s.region_id = r.id
             WHERE r.country_id = ? AND s.active = 1",
            [$this->id]
        )['count'] ?? 0;
    }

    /**
     * Obtenir le nombre total de voies
     */
    public function getRoutesCount(): int
    {
        return $this->db()->fetchOne(
            "SELECT COUNT(*) as count FROM climbing_routes r
             JOIN climbing_sectors s ON r.sector_id = s.id
             JOIN climbing_regions reg ON s.region_id = reg.id
             WHERE reg.country_id = ? AND r.active = 1",
            [$this->id]
        )['count'] ?? 0;
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
        return self::where('active', 1)->all(['name' => 'ASC']);
    }
    
    /**
     * Obtenir l'emoji du drapeau du pays
     */
    public function getFlagEmoji(): string
    {
        if (empty($this->code)) {
            return '';
        }
        
        // Conversion du code ISO en caractères Unicode pour l'emoji
        $codePoints = [];
        $chars = str_split(strtoupper($this->code));
        
        foreach ($chars as $char) {
            $codePoints[] = ord($char) + 127397; // 127397 est l'offset pour convertir A-Z en drapeaux régionaux
        }
        
        return implode('', array_map(fn($cp) => mb_chr($cp, 'UTF-8'), $codePoints));
    }
}