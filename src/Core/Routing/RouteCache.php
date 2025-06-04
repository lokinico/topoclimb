<?php
// src/Core/Routing/RouteCache.php

namespace TopoclimbCH\Core\Routing;

class RouteCache
{
    /**
     * Chemin du fichier de cache
     *
     * @var string
     */
    private string $cachePath;

    /**
     * Cache en mémoire
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Indique si le cache a été chargé
     *
     * @var bool
     */
    private bool $loaded = false;

    /**
     * TTL du cache en secondes (1 heure par défaut)
     *
     * @var int
     */
    private int $ttl;

    /**
     * RouteCache constructor
     *
     * @param string $cachePath
     * @param int $ttl
     */
    public function __construct(string $cachePath, int $ttl = 3600)
    {
        $this->cachePath = $cachePath;
        $this->ttl = $ttl;
    }

    /**
     * Récupérer une valeur du cache
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key): mixed
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        if (!isset($this->cache[$key])) {
            return null;
        }

        $entry = $this->cache[$key];

        // Vérifier l'expiration
        if (isset($entry['expires']) && $entry['expires'] < time()) {
            unset($this->cache[$key]);
            return null;
        }

        return $entry['data'] ?? null;
    }

    /**
     * Stocker une valeur dans le cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        $this->cache[$key] = [
            'data' => $value,
            'created' => time(),
            'expires' => time() + ($ttl ?? $this->ttl)
        ];

        // Sauvegarder automatiquement après chaque modification
        $this->saveCache();
    }

    /**
     * Vérifier si une clé existe dans le cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Supprimer une clé du cache
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        unset($this->cache[$key]);
        $this->saveCache();
    }

    /**
     * Vider tout le cache
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->saveCache();
    }

    /**
     * Charger le cache depuis le fichier
     *
     * @return void
     */
    private function loadCache(): void
    {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->cachePath)) {
            try {
                $data = file_get_contents($this->cachePath);
                if ($data !== false) {
                    $decoded = json_decode($data, true);
                    if (is_array($decoded)) {
                        $this->cache = $decoded;
                    }
                }
            } catch (\Exception $e) {
                // En cas d'erreur, continuer avec un cache vide
                $this->cache = [];
            }
        }

        $this->loaded = true;
    }

    /**
     * Sauvegarder le cache dans le fichier
     *
     * @return void
     */
    private function saveCache(): void
    {
        try {
            // Créer le répertoire si nécessaire
            $dir = dirname($this->cachePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Nettoyer les entrées expirées avant la sauvegarde
            $this->cleanExpiredEntries();

            // Sauvegarder
            $json = json_encode($this->cache, JSON_PRETTY_PRINT);
            file_put_contents($this->cachePath, $json, LOCK_EX);
        } catch (\Exception $e) {
            // En cas d'erreur de sauvegarde, continuer silencieusement
            error_log("Failed to save route cache: " . $e->getMessage());
        }
    }

    /**
     * Nettoyer les entrées expirées
     *
     * @return void
     */
    private function cleanExpiredEntries(): void
    {
        $now = time();

        foreach ($this->cache as $key => $entry) {
            if (isset($entry['expires']) && $entry['expires'] < $now) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Obtenir des statistiques sur le cache
     *
     * @return array
     */
    public function getStats(): array
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        $now = time();
        $total = count($this->cache);
        $expired = 0;
        $valid = 0;

        foreach ($this->cache as $entry) {
            if (isset($entry['expires']) && $entry['expires'] < $now) {
                $expired++;
            } else {
                $valid++;
            }
        }

        return [
            'total_entries' => $total,
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'cache_file_exists' => file_exists($this->cachePath),
            'cache_file_size' => file_exists($this->cachePath) ? filesize($this->cachePath) : 0,
            'cache_file_modified' => file_exists($this->cachePath) ? filemtime($this->cachePath) : null
        ];
    }

    /**
     * Pré-chauffer le cache avec des routes communes
     *
     * @param array $commonRoutes
     * @return void
     */
    public function warmUp(array $commonRoutes): void
    {
        foreach ($commonRoutes as $key => $route) {
            $this->set($key, $route);
        }
    }

    /**
     * Optimiser le cache en supprimant les entrées anciennes
     *
     * @param int $maxAge Âge maximum en secondes
     * @return int Nombre d'entrées supprimées
     */
    public function optimize(int $maxAge = 86400): int
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        $removed = 0;
        $cutoff = time() - $maxAge;

        foreach ($this->cache as $key => $entry) {
            if (isset($entry['created']) && $entry['created'] < $cutoff) {
                unset($this->cache[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->saveCache();
        }

        return $removed;
    }

    /**
     * Obtenir la taille du cache en mémoire
     *
     * @return int
     */
    public function getMemoryUsage(): int
    {
        return strlen(serialize($this->cache));
    }

    /**
     * Exporter le cache pour débogage
     *
     * @return array
     */
    public function export(): array
    {
        if (!$this->loaded) {
            $this->loadCache();
        }

        return $this->cache;
    }

    /**
     * Importer des données de cache
     *
     * @param array $data
     * @return void
     */
    public function import(array $data): void
    {
        $this->cache = $data;
        $this->loaded = true;
        $this->saveCache();
    }

    /**
     * Obtenir le chemin du fichier de cache
     *
     * @return string
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Définir un nouveau TTL
     *
     * @param int $ttl
     * @return void
     */
    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Obtenir le TTL actuel
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
