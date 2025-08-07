<?php
// src/Core/Pagination/Paginator.php

namespace TopoclimbCH\Core\Pagination;

class Paginator
{
    /**
     * Nombre total d'items
     */
    protected int $total;
    
    /**
     * Nombre d'items par page
     */
    protected int $perPage;
    
    /**
     * Page courante
     */
    protected int $currentPage;
    
    /**
     * Nombre total de pages
     */
    protected int $lastPage;
    
    /**
     * URL de base pour les liens de pagination
     */
    protected string $baseUrl;
    
    /**
     * Items de la page courante
     */
    protected array $items = [];
    
    /**
     * Paramètres de filtrage à conserver dans les liens
     */
    protected array $queryParams = [];
    
    /**
     * Nombre de pages à afficher de chaque côté de la page courante
     */
    protected int $onEachSide = 2;
    
    /**
     * Constructeur
     *
     * @param array $items Items de la page courante
     * @param int $total Nombre total d'items
     * @param int $perPage Nombre d'items par page
     * @param int $currentPage Page courante
     * @param array $queryParams Paramètres de filtrage
     */
    public function __construct(array $items, int $total, int $perPage, int $currentPage, array $queryParams = [])
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = max(1, $currentPage);
        $this->queryParams = $queryParams;
        
        // Calculer le nombre total de pages
        $this->lastPage = max(1, ceil($this->total / $this->perPage));
        
        // Définir l'URL de base
        $this->baseUrl = $this->getBaseUrl();
    }
    
    /**
     * Obtenir l'URL de base pour la pagination
     */
    protected function getBaseUrl(): string
    {
        // Obtenir l'URL courante sans paramètres
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $url = strtok($requestUri, '?');
        
        return $url ?: '/';
    }
    
    /**
     * Obtenir les items de la page courante
     */
    public function getItems(): array
    {
        return $this->items;
    }
    
    /**
     * Obtenir le nombre total d'items
     */
    public function getTotal(): int
    {
        return $this->total;
    }
    
    /**
     * Obtenir le nombre d'items par page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }
    
    /**
     * Obtenir la page courante
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
    
    /**
     * Obtenir le nombre total de pages
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }
    
    /**
     * Vérifier s'il y a des pages à afficher
     */
    public function hasPages(): bool
    {
        return $this->total > $this->perPage;
    }
    
    /**
     * Vérifier s'il y a une page précédente
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }
    
    /**
     * Vérifier s'il y a une page suivante
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
    
    /**
     * Obtenir le numéro de la page précédente
     */
    public function getPreviousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }
    
    /**
     * Obtenir le numéro de la page suivante
     */
    public function getNextPage(): int
    {
        return min($this->lastPage, $this->currentPage + 1);
    }
    
    /**
     * Construire l'URL pour une page
     */
    public function getPageUrl(int $page): string
    {
        $params = $this->queryParams;
        $params['page'] = $page;
        
        return $this->baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * Obtenir les liens de pagination
     */
    public function getLinks(): array
    {
        $links = [];
        
        // Lien vers la première page
        $links[] = [
            'page' => 1,
            'url' => $this->getPageUrl(1),
            'label' => '«',
            'active' => false,
            'disabled' => !$this->hasPreviousPage()
        ];
        
        // Lien vers la page précédente
        $links[] = [
            'page' => $this->getPreviousPage(),
            'url' => $this->getPageUrl($this->getPreviousPage()),
            'label' => '‹',
            'active' => false,
            'disabled' => !$this->hasPreviousPage()
        ];
        
        // Liens vers les pages numériques
        $pages = $this->getPageRange();
        foreach ($pages as $page) {
            $links[] = [
                'page' => $page,
                'url' => $this->getPageUrl($page),
                'label' => (string) $page,
                'active' => $page === $this->currentPage,
                'disabled' => false
            ];
        }
        
        // Lien vers la page suivante
        $links[] = [
            'page' => $this->getNextPage(),
            'url' => $this->getPageUrl($this->getNextPage()),
            'label' => '›',
            'active' => false,
            'disabled' => !$this->hasNextPage()
        ];
        
        // Lien vers la dernière page
        $links[] = [
            'page' => $this->lastPage,
            'url' => $this->getPageUrl($this->lastPage),
            'label' => '»',
            'active' => false,
            'disabled' => !$this->hasNextPage()
        ];
        
        return $links;
    }
    
    /**
     * Obtenir la plage de pages à afficher
     */
    protected function getPageRange(): array
    {
        $start = max(1, $this->currentPage - $this->onEachSide);
        $end = min($this->lastPage, $this->currentPage + $this->onEachSide);
        
        return range($start, $end);
    }
    
    /**
     * Obtenir les informations sur la pagination
     */
    public function getInfo(): array
    {
        $from = ($this->currentPage - 1) * $this->perPage + 1;
        $to = min($this->total, $this->currentPage * $this->perPage);
        
        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'from' => $from,
            'to' => $to
        ];
    }
    
    /**
     * Options autorisées pour le nombre d'éléments par page
     */
    public const ALLOWED_PER_PAGE = [15, 30, 60];
    
    /**
     * Valider et nettoyer le nombre d'éléments par page
     */
    public static function validatePerPage(?int $perPage): int
    {
        if ($perPage === null || !in_array($perPage, self::ALLOWED_PER_PAGE)) {
            return self::ALLOWED_PER_PAGE[0]; // Par défaut 15
        }
        
        return $perPage;
    }
    
    /**
     * Méthode statique pour paginer des résultats
     *
     * @param array $query Résultats de requête à paginer
     * @param int $page Page courante
     * @param int $perPage Nombre d'éléments par page
     * @param array $queryParams Paramètres de filtrage
     * @return self
     */
    public static function paginate(array $query, int $page = 1, int $perPage = 15, array $queryParams = []): self
    {
        $perPage = self::validatePerPage($perPage);
        $total = count($query);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($query, $offset, $perPage);
        
        return new self($items, $total, $perPage, $page, $queryParams);
    }
    
    /**
     * Extension de la classe Model pour la pagination
     *
     * @param string $modelClass Classe de modèle
     * @param array $where Conditions WHERE
     * @param string|null $orderBy Colonne de tri
     * @param string $direction Direction de tri
     * @param int $page Page courante
     * @param int $perPage Nombre d'éléments par page
     * @param array $queryParams Paramètres de filtrage
     * @return self
     */
    public static function paginateModel(string $modelClass, array $where = [], ?string $orderBy = null, string $direction = 'ASC', int $page = 1, int $perPage = 15, array $queryParams = []): self
    {
        $allResults = $modelClass::where($where, $orderBy, $direction);
        return self::paginate($allResults, $page, $perPage, $queryParams);
    }
}