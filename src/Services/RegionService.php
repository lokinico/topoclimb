<?php
// src/Services/RegionService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Region;

class RegionService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAllRegions(): array
    {
        return Region::where('active', 1)->all();
    }

    public function getRegionsByCountry(int $countryId): array
    {
        return Region::where('country_id', $countryId)
            ->where('active', 1)
            ->all();
    }

    // Implement other methods used in the controller
}
