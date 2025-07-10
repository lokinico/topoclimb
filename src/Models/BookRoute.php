<?php

declare(strict_types=1);

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * BookRoute Model - Liaison entre Books et Voies
 * 
 * Permet de définir quelles voies spécifiques sont incluses dans un topo/book
 */
class BookRoute extends Model
{
    protected string $table = 'climbing_book_routes';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'book_id',
        'route_id',
        'sort_order',
        'page_number',
        'notes'
    ];

    protected array $rules = [
        'book_id' => 'required|numeric',
        'route_id' => 'required|numeric',
        'sort_order' => 'numeric',
        'page_number' => 'numeric'
    ];

    /**
     * Relations
     */

    public function book(): ?Book
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function route(): ?Route
    {
        return $this->belongsTo(Route::class, 'route_id');
    }
}

/**
 * Book Model - Version étendue pour gestion des topos
 */
class Book extends Model
{
    protected string $table = 'climbing_books';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'region_id',
        'name',
        'code',
        'year',
        'publisher',
        'isbn',
        'active'
    ];

    protected array $rules = [
        'region_id' => 'required|numeric',
        'name' => 'required|min:2|max:100',
        'code' => 'max:50',
        'year' => 'numeric',
        'publisher' => 'max:100',
        'isbn' => 'max:20'
    ];

    /**
     * Relations
     */

    public function region(): ?Region
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Secteurs inclus dans ce book via la table pivot
     */
    public function sectors(): array
    {
        $sql = "
            SELECT s.*, bs.sort_order, bs.is_complete, bs.notes as book_notes
            FROM climbing_sectors s
            INNER JOIN climbing_book_sectors bs ON s.id = bs.sector_id
            WHERE bs.book_id = ? AND s.active = 1
            ORDER BY bs.sort_order, s.name
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Voies incluses dans ce book via la table pivot
     */
    public function routes(): array
    {
        $sql = "
            SELECT r.*, br.sort_order, br.page_number, br.notes as book_notes,
                   s.name as sector_name
            FROM climbing_routes r
            INNER JOIN climbing_book_routes br ON r.id = br.route_id
            INNER JOIN climbing_sectors s ON r.sector_id = s.id
            WHERE br.book_id = ? AND r.active = 1
            ORDER BY br.sort_order, br.page_number, r.number
        ";

        return $this->db->fetchAll($sql, [$this->id]);
    }

    /**
     * Méthodes utilitaires
     */

    /**
     * Ajouter un secteur au book
     */
    public function addSector(int $sectorId, bool $isComplete = true, int $sortOrder = 0, ?string $notes = null): bool
    {
        $sql = "
            INSERT INTO climbing_book_sectors (book_id, sector_id, sort_order, is_complete, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                sort_order = VALUES(sort_order),
                is_complete = VALUES(is_complete),
                notes = VALUES(notes)
        ";

        return $this->db->query($sql, [$this->id, $sectorId, $sortOrder, $isComplete ? 1 : 0, $notes]);
    }

    /**
     * Ajouter une voie au book
     */
    public function addRoute(int $routeId, int $sortOrder = 0, ?int $pageNumber = null, ?string $notes = null): bool
    {
        $sql = "
            INSERT INTO climbing_book_routes (book_id, route_id, sort_order, page_number, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                sort_order = VALUES(sort_order),
                page_number = VALUES(page_number),
                notes = VALUES(notes)
        ";

        return $this->db->query($sql, [$this->id, $routeId, $sortOrder, $pageNumber, $notes]);
    }

    /**
     * Retirer un secteur du book
     */
    public function removeSector(int $sectorId): bool
    {
        $sql = "DELETE FROM climbing_book_sectors WHERE book_id = ? AND sector_id = ?";
        return $this->db->query($sql, [$this->id, $sectorId]);
    }

    /**
     * Retirer une voie du book
     */
    public function removeRoute(int $routeId): bool
    {
        $sql = "DELETE FROM climbing_book_routes WHERE book_id = ? AND route_id = ?";
        return $this->db->query($sql, [$this->id, $routeId]);
    }

    /**
     * Obtenir les statistiques du book
     */
    public function getStats(): array
    {
        // Statistiques des secteurs
        $sectorStats = $this->db->fetchOne("
            SELECT COUNT(*) as sector_count
            FROM climbing_book_sectors bs
            WHERE bs.book_id = ?
        ", [$this->id]);

        // Statistiques des voies
        $routeStats = $this->db->fetchOne("
            SELECT COUNT(*) as route_count
            FROM climbing_book_routes br
            WHERE br.book_id = ?
        ", [$this->id]);

        // Voies totales dans secteurs complets
        $completeSectorRoutes = $this->db->fetchOne("
            SELECT COUNT(r.id) as complete_sector_routes
            FROM climbing_routes r
            INNER JOIN climbing_sectors s ON r.sector_id = s.id
            INNER JOIN climbing_book_sectors bs ON s.id = bs.sector_id
            WHERE bs.book_id = ? AND bs.is_complete = 1 AND r.active = 1
        ", [$this->id]);

        return [
            'sector_count' => (int)$sectorStats['sector_count'],
            'route_count' => (int)$routeStats['route_count'],
            'complete_sector_routes' => (int)$completeSectorRoutes['complete_sector_routes'],
            'total_routes' => (int)$routeStats['route_count'] + (int)$completeSectorRoutes['complete_sector_routes']
        ];
    }

    /**
     * Vérifier si un secteur est dans ce book
     */
    public function hasSector(int $sectorId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_book_sectors WHERE book_id = ? AND sector_id = ?";
        $result = $this->db->fetchOne($sql, [$this->id, $sectorId]);
        return $result['count'] > 0;
    }

    /**
     * Vérifier si une voie est dans ce book
     */
    public function hasRoute(int $routeId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM climbing_book_routes WHERE book_id = ? AND route_id = ?";
        $result = $this->db->fetchOne($sql, [$this->id, $routeId]);
        return $result['count'] > 0;
    }
}
