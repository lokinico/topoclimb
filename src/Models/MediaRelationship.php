<?php
// src/Models/MediaRelationship.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class MediaRelationship extends Model
{
    /**
     * @var string
     */
    protected string $table = 'climbing_media_relationships';

    /**
     * @var array
     */
    protected array $fillable = [
        'media_id',
        'entity_type',
        'entity_id',
        'relationship_type',
        'sort_order',
        'coordinates_x',
        'coordinates_y',
        'notes'
    ];

    /**
     * @var array
     */
    protected array $casts = [
        'media_id' => 'int',
        'entity_id' => 'int',
        'sort_order' => 'int',
        'coordinates_x' => 'float',
        'coordinates_y' => 'float'
    ];

    /**
     * Relation avec le média
     */
    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Validation rules
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'media_id' => 'required|numeric',
            'entity_type' => 'required|in:country,region,site,sector,route,user,event',
            'entity_id' => 'required|numeric',
            'relationship_type' => 'required|in:main,gallery,topo,profile,cover,other',
            'sort_order' => 'numeric'
        ];
    }
}
