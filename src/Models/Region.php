<?php
// src/Models/Region.php

namespace TopoclimbCH\Models;

class Region extends Model
{
    /**
     * Nom de la table
     *
     * @var string
     */
    protected string $table = 'climbing_regions';
    
    /**
     * Champs autorisés
     *
     * @var array
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
     * Récupère les secteurs de cette région
     *
     * @return array
     */
    public function sectors(): array
    {
        $sectorModel = new Sector($this->db);
        return $this->db->fetchAll(
            "SELECT * FROM {$sectorModel->getTable()} WHERE region_id = ?",
            [$this->attributes[$this->primaryKey]]
        );
    }
    
    /**
     * Récupère le pays associé à cette région
     *
     * @return Country|null
     */
    public function country(): ?Country
    {
        if (!isset($this->attributes['country_id'])) {
            return null;
        }
        
        $countryModel = new Country($this->db);
        return $countryModel->find($this->attributes['country_id']);
    }
    
    /**
     * Accesseur pour la table
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
}