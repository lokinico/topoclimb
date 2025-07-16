<?php

declare(strict_types=1);

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

/**
 * BookSector Model - Liaison entre Books et Secteurs
 * 
 * Permet de définir quels secteurs sont inclus dans un topo/book donné
 */
class BookSector extends Model
{
    protected static string $table = 'climbing_book_sectors';
    protected static string $primaryKey = 'id';

    protected array $fillable = [
        'book_id',
        'sector_id',
        'sort_order',
        'is_complete',
        'notes'
    ];

    protected array $rules = [
        'book_id' => 'required|numeric',
        'sector_id' => 'required|numeric',
        'sort_order' => 'numeric'
    ];

    /**
     * Relations
     */

    public function book(): ?Book
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function sector(): ?Sector
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }
}
