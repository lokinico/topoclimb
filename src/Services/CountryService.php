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

    /**
     * Récupère tous les pays actifs
     */
    public function getAllCountries(): array
    {
        // Correction: Country::where() retourne déjà un tableau, pas besoin de all()
        return Country::where(['active' => 1]);
    }

    /**
     * Récupère un pays par son ID
     */
    public function getCountry(int $id): ?Country
    {
        return Country::find($id);
    }

    /**
     * Crée un nouveau pays
     */
    public function createCountry(array $data): Country
    {
        $country = new Country();
        $country->fill($data);
        $country->save();
        return $country;
    }

    /**
     * Met à jour un pays existant
     */
    public function updateCountry(Country $country, array $data): Country
    {
        $country->fill($data);
        $country->save();
        return $country;
    }

    /**
     * Supprime un pays
     */
    public function deleteCountry(Country $country): bool
    {
        return $country->delete();
    }

    /**
     * Récupère un pays avec ses statistiques
     */
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
