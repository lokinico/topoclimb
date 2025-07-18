<?php

declare(strict_types=1);

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;
use TopoclimbCH\Core\Database;

/**
 * Site Model - Sous-zones optionnelles des régions
 * 
 * Un site est une subdivision d'une région qui peut contenir plusieurs secteurs.
 * L'utilisation des sites est optionnelle - les secteurs peuvent être directement
 * attachés à une région.
 */
class Site extends Model
{
    protected static string $table = 'climbing_sites';
    protected static string $primaryKey = 'id';

    protected array $fillable = [
        'region_id',
        'name',
        'code',
        'description',
        'year',
        'publisher',
        'isbn',
        'image',
        'active'
    ];

    protected array $rules = [
        'region_id' => 'required|numeric',
        'name' => 'required|min:2|max:255',
        'code' => 'required|min:1|max:50',
        'description' => 'max:65535'
    ];

    /**
     * Relations
     */

    /**
     * Un site appartient à une région
     */
    public function region(): ?Region
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Un site peut avoir plusieurs secteurs
     */
    public function sectors(): array
    {
        return $this->hasMany(Sector::class, 'site_id');
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Obtenir tous les secteurs de ce site avec leurs statistiques
     */
    public function getSectorsWithStats(): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(r.id) as route_count,
                   AVG(CAST(r.beauty AS UNSIGNED)) as avg_beauty,
                   MIN(r.difficulty) as min_difficulty,
                   MAX(r.difficulty) as max_difficulty
            FROM climbing_sectors s 
            LEFT JOIN climbing_routes r ON s.id = r.sector_id AND r.active = 1
            WHERE s.site_id = ? AND s.active = 1
            GROUP BY s.id
            ORDER BY s.name
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Vérifier si le site a des secteurs
     */
    public function hasSectors(): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_sectors WHERE site_id = ? AND active = 1";
        $result = $this->db->fetchOne($sql, [$this->id]);
        return $result['count'] > 0;
    }

    /**
     * Obtenir le nombre total de voies dans ce site
     */
    public function getTotalRoutes(): int
    {
        $sql = "
            SELECT COUNT(r.id) as count 
            FROM climbing_routes r
            INNER JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE s.site_id = ? AND r.active = 1 AND s.active = 1
        ";

        $result = $this->db->fetchOne($sql, [$this->id]);
        return (int)$result['count'];
    }

    /**
     * Obtenir la difficulté moyenne des voies du site
     */
    public function getAverageDifficulty(): ?string
    {
        $sql = "
            SELECT AVG(dg.numerical_value) as avg_difficulty
            FROM climbing_routes r
            INNER JOIN climbing_sectors s ON r.sector_id = s.id
            INNER JOIN climbing_difficulty_grades dg ON r.difficulty = dg.value
            WHERE s.site_id = ? AND r.active = 1 AND s.active = 1
        ";

        $result = $this->db->fetchOne($sql, [$this->id]);
        return $result['avg_difficulty'] ? round($result['avg_difficulty'], 1) : null;
    }

    /**
     * Rechercher des sites
     */
    public static function search(string $query, ?int $regionId = null): array
    {
        $sql = "
            SELECT s.*, r.name as region_name
            FROM climbing_sites s
            INNER JOIN climbing_regions r ON s.region_id = r.id
            WHERE s.active = 1 
            AND s.name LIKE ?
        ";

        $params = ["%{$query}%"];

        if ($regionId) {
            $sql .= " AND s.region_id = ?";
            $params[] = $regionId;
        }

        $sql .= " ORDER BY s.name";

        $config = [
            'type' => 'sqlite',
            'database' => __DIR__ . '/../../data/topoclimb.db'
        ];
        $db = new Database($config);
        return $db->fetchAll($sql, $params);
    }

    /**
     * Obtenir tous les sites d'une région
     */
    public static function getByRegion(int $regionId): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(sec.id) as sector_count,
                   COUNT(r.id) as route_count
            FROM climbing_sites s
            LEFT JOIN climbing_sectors sec ON s.id = sec.site_id AND sec.active = 1
            LEFT JOIN climbing_routes r ON sec.id = r.sector_id AND r.active = 1
            WHERE s.region_id = ? AND s.active = 1
            GROUP BY s.id
            ORDER BY s.name
        ";

        $db = new \App\Core\Database();
        return $db->fetchAll($sql, [$regionId]);
    }

    /**
     * Accesseurs
     */

    public function getFullNameAttribute(): string
    {
        $region = $this->region();
        if ($region) {
            return $region->name . ' - ' . $this->name;
        }
        return $this->name;
    }

    public function getCodeDisplayAttribute(): string
    {
        return strtoupper($this->code);
    }

    /**
     * Événements du modèle
     */

    protected function onCreating(): void
    {
        // Générer un code unique si pas fourni
        if (empty($this->code)) {
            $this->code = $this->generateUniqueCode();
        }
    }

    /**
     * Générer un code unique pour le site
     */
    private function generateUniqueCode(): string
    {
        $baseName = strtoupper(substr($this->name, 0, 3));
        $counter = 1;
        $code = $baseName;

        while ($this->codeExists($code)) {
            $code = $baseName . sprintf('%02d', $counter);
            $counter++;
        }

        return $code;
    }

    /**
     * Vérifier si un code existe déjà
     */
    private function codeExists(string $code): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_sites WHERE code = ?";
        if ($this->id) {
            $sql .= " AND id != ?";
            $result = $this->db->fetchOne($sql, [$code, $this->id]);
        } else {
            $result = $this->db->fetchOne($sql, [$code]);
        }

        return $result['count'] > 0;
    }
}
