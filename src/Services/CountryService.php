<?php
// src/Services/CountryService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\Country;

class CountryService
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAllCountries(): array
    {
        return Country::where('active', 1)->all();
    }

    public function getCountry(int $id): ?Country
    {
        return Country::find($id);
    }

    public function createCountry(array $data): Country
    {
        $country = new Country();
        $country->fill($data);
        $country->save();
        return $country;
    }

    public function updateCountry(Country $country, array $data): Country
    {
        $country->fill($data);
        $country->save();
        return $country;
    }

    public function deleteCountry(Country $country): bool
    {
        return $country->delete();
    }

    public function getCountryWithStats(int $id): ?array
    {
        $country = $this->getCountry($id);
        if (!$country) {
            return null;
        }

        return [
            'country' => $country,
            'stats' => $country->getStatistics()
        ];
    }
}
