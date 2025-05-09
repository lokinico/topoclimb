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
        'active',
        'created_by',
        'updated_by'
    ];
    
    /**
     * Relation avec les secteurs
     */
    public function sectors(): array
    {
        return $this->hasMany(Sector::class);
    }
    
    /**
     * Relation avec le pays
     */
    public function country(): ?Country
    {
        return $this->belongsTo(Country::class);
    }
}