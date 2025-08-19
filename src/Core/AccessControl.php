<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;

/**
 * Système de contrôle d'accès hiérarchique strict
 * Implémente la hiérarchie définie dans ACCESS_HIERARCHY.md
 */
class AccessControl
{
    private ?Auth $auth;
    private Session $session;
    
    // Niveaux d'accès (de plus restrictif à moins restrictif)
    const LEVEL_PUBLIC = 'public';           // Non connecté
    const LEVEL_PENDING = 'pending';         // Rôle 4 - En attente
    const LEVEL_RESTRICTED = 'restricted';   // Rôle 3 - Accès limité 
    const LEVEL_MEMBER = 'member';          // Rôle 2 - Membre complet
    const LEVEL_MODERATOR = 'moderator';    // Rôle 1 - Modérateur
    const LEVEL_ADMIN = 'admin';            // Rôle 0 - Administrateur
    const LEVEL_BANNED = 'banned';          // Rôle 5 - Banni

    // Configuration des pages par niveau d'accès
    private array $pageAccess = [
        self::LEVEL_PUBLIC => [
            '/',
            '/login',
            '/register',
            '/demo',
            '/demo/regions',
            '/demo/sites',
            '/demo/sectors', 
            '/demo/routes',
            '/preview/region/{id}',
            '/preview/site/{id}',
            '/preview/sector/{id}',
            '/about',
            '/contact',
            '/privacy',
            '/terms'
        ],
        
        self::LEVEL_PENDING => [
            // Hérite du niveau public +
            '/profile',
            '/profile/edit',
            '/pending'
        ],
        
        self::LEVEL_RESTRICTED => [
            // Hérite des niveaux précédents +
            '/regions',
            '/sites',
            '/sectors',
            '/routes',
            '/favorites',
            '/ascents/own'
        ],
        
        self::LEVEL_MEMBER => [
            // Hérite des niveaux précédents +
            '/books',
            '/weather',
            '/map',
            '/ascents',
            '/forum',
            '/forum/create',
            '/forum/reply',
            '/routes/{id}/comment',
            '/routes/{id}/log-ascent'
        ],
        
        self::LEVEL_MODERATOR => [
            // Hérite des niveaux précédents +
            '/regions/create',
            '/sites/create', 
            '/sectors/create',
            '/routes/create',
            '/admin/moderate',
            '/admin/users',
            '/admin/reports',
            '/admin/validate-users'
        ],
        
        self::LEVEL_ADMIN => [
            // Accès complet - toutes les pages
            '*'
        ]
    ];

    // Configuration du contenu limité par niveau
    private array $contentLimits = [
        self::LEVEL_PUBLIC => [
            'regions_max' => 3,
            'sites_per_region' => 2,
            'sectors_per_site' => 1,
            'routes_per_sector' => 5,
            'gps_precision' => 'none',
            'weather_access' => false,
            'guides_access' => false,
            'watermark' => true
        ],
        
        self::LEVEL_PENDING => [
            'regions_max' => 3,
            'sites_per_region' => 2,
            'sectors_per_site' => 1, 
            'routes_per_sector' => 5,
            'gps_precision' => 'none',
            'weather_access' => false,
            'guides_access' => false,
            'watermark' => true
        ],
        
        self::LEVEL_RESTRICTED => [
            'regions_max' => -1,  // Toutes
            'sites_per_region' => 'subscription_based',
            'sectors_per_site' => 'subscription_based',
            'routes_per_sector' => 'subscription_based',
            'gps_precision' => 'approximate',
            'weather_access' => 'basic',
            'guides_access' => false,
            'watermark' => false
        ],
        
        self::LEVEL_MEMBER => [
            'regions_max' => -1,
            'sites_per_region' => -1,
            'sectors_per_site' => -1,
            'routes_per_sector' => -1,
            'gps_precision' => 'exact',
            'weather_access' => 'full',
            'guides_access' => true,
            'watermark' => false
        ],
        
        self::LEVEL_MODERATOR => [
            // Même que membre + permissions de modération
            'regions_max' => -1,
            'sites_per_region' => -1,
            'sectors_per_site' => -1,
            'routes_per_sector' => -1,
            'gps_precision' => 'exact',
            'weather_access' => 'full',
            'guides_access' => true,
            'watermark' => false
        ],
        
        self::LEVEL_ADMIN => [
            // Accès illimité à tout
            'unlimited' => true
        ]
    ];

    public function __construct(?Auth $auth = null, ?Session $session = null)
    {
        $this->auth = $auth;
        $this->session = $session ?? new Session();
    }

    /**
     * Détermine le niveau d'accès actuel de l'utilisateur
     */
    public function getCurrentAccessLevel(): string
    {
        // Utilisateur non connecté
        if (!$this->auth || !$this->auth->check()) {
            return self::LEVEL_PUBLIC;
        }

        $role = $this->auth->role();

        return match($role) {
            0 => self::LEVEL_ADMIN,
            1 => self::LEVEL_MODERATOR, 
            2 => self::LEVEL_MEMBER,
            3 => self::LEVEL_RESTRICTED,
            4 => self::LEVEL_PENDING,
            5 => self::LEVEL_BANNED,
            default => self::LEVEL_PUBLIC
        };
    }

    /**
     * Vérifie si l'utilisateur a accès à une page spécifique
     */
    public function canAccessPage(string $path): bool
    {
        $level = $this->getCurrentAccessLevel();

        // Utilisateur banni - aucun accès
        if ($level === self::LEVEL_BANNED) {
            return in_array($path, ['/banned', '/logout']);
        }

        // Admin a accès à tout
        if ($level === self::LEVEL_ADMIN) {
            return true;
        }

        // Vérifier l'accès hiérarchique
        return $this->checkHierarchicalAccess($path, $level);
    }

    /**
     * Vérifie l'accès hiérarchique (niveaux supérieurs héritent des inférieurs)
     */
    private function checkHierarchicalAccess(string $path, string $userLevel): bool
    {
        $hierarchy = [
            self::LEVEL_PUBLIC,
            self::LEVEL_PENDING,
            self::LEVEL_RESTRICTED,
            self::LEVEL_MEMBER,
            self::LEVEL_MODERATOR,
            self::LEVEL_ADMIN
        ];

        $userLevelIndex = array_search($userLevel, $hierarchy);
        if ($userLevelIndex === false) {
            return false;
        }

        // Vérifier dans tous les niveaux jusqu'au niveau utilisateur
        for ($i = 0; $i <= $userLevelIndex; $i++) {
            $level = $hierarchy[$i];
            if (isset($this->pageAccess[$level])) {
                if ($this->matchesPath($path, $this->pageAccess[$level])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifie si un chemin correspond aux patterns autorisés
     */
    private function matchesPath(string $path, array $allowedPaths): bool
    {
        foreach ($allowedPaths as $pattern) {
            if ($pattern === '*') {
                return true;
            }
            
            // Pattern exact
            if ($pattern === $path) {
                return true;
            }
            
            // Pattern avec paramètres {id}
            $regexPattern = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
            $regexPattern = '#^' . str_replace('/', '\/', $regexPattern) . '$#';
            
            if (preg_match($regexPattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtient les limites de contenu pour le niveau actuel
     */
    public function getContentLimits(): array
    {
        $level = $this->getCurrentAccessLevel();
        return $this->contentLimits[$level] ?? $this->contentLimits[self::LEVEL_PUBLIC];
    }

    /**
     * Applique les limitations de contenu à une requête de données
     */
    public function applyContentLimits(string $entityType, array $queryParams = []): array
    {
        $limits = $this->getContentLimits();
        
        if (isset($limits['unlimited']) && $limits['unlimited']) {
            return $queryParams; // Pas de limites pour admin
        }

        switch ($entityType) {
            case 'regions':
                if ($limits['regions_max'] > 0) {
                    $queryParams['limit'] = min($queryParams['limit'] ?? PHP_INT_MAX, $limits['regions_max']);
                }
                break;
                
            case 'sites':
                if ($limits['sites_per_region'] !== -1 && is_numeric($limits['sites_per_region'])) {
                    $queryParams['limit'] = min($queryParams['limit'] ?? PHP_INT_MAX, $limits['sites_per_region']);
                }
                break;
                
            case 'sectors':
                if ($limits['sectors_per_site'] !== -1 && is_numeric($limits['sectors_per_site'])) {
                    $queryParams['limit'] = min($queryParams['limit'] ?? PHP_INT_MAX, $limits['sectors_per_site']);
                }
                break;
                
            case 'routes':
                if ($limits['routes_per_sector'] !== -1 && is_numeric($limits['routes_per_sector'])) {
                    $queryParams['limit'] = min($queryParams['limit'] ?? PHP_INT_MAX, $limits['routes_per_sector']);
                }
                break;
        }

        return $queryParams;
    }

    /**
     * Génère une URL de redirection appropriée selon le niveau d'accès
     */
    public function getRedirectUrlForLevel(string $attemptedPath): string
    {
        $level = $this->getCurrentAccessLevel();

        return match($level) {
            self::LEVEL_BANNED => '/banned',
            self::LEVEL_PUBLIC => '/login?redirect=' . urlencode($attemptedPath),
            self::LEVEL_PENDING => '/pending',
            default => '/'
        };
    }

    /**
     * Obtient les informations d'affichage pour l'interface utilisateur
     */
    public function getUIDisplayInfo(): array
    {
        $level = $this->getCurrentAccessLevel();
        $limits = $this->getContentLimits();

        $displayInfo = [
            'level' => $level,
            'level_name' => $this->getLevelDisplayName($level),
            'watermark' => $limits['watermark'] ?? false,
            'upgrade_prompts' => false,
            'restrictions_notice' => false
        ];

        switch ($level) {
            case self::LEVEL_PUBLIC:
                $displayInfo['banner'] = 'Créer un compte pour accès complet';
                $displayInfo['upgrade_prompts'] = true;
                break;
                
            case self::LEVEL_PENDING:
                $displayInfo['badge'] = 'En attente de validation';
                $displayInfo['badge_color'] = 'orange';
                break;
                
            case self::LEVEL_RESTRICTED:
                $displayInfo['restrictions_notice'] = true;
                $displayInfo['upgrade_prompts'] = true;
                break;
        }

        return $displayInfo;
    }

    /**
     * Nom d'affichage du niveau d'accès
     */
    private function getLevelDisplayName(string $level): string
    {
        return match($level) {
            self::LEVEL_PUBLIC => 'Visiteur',
            self::LEVEL_PENDING => 'En attente',
            self::LEVEL_RESTRICTED => 'Accès limité',
            self::LEVEL_MEMBER => 'Membre',
            self::LEVEL_MODERATOR => 'Modérateur',
            self::LEVEL_ADMIN => 'Administrateur',
            self::LEVEL_BANNED => 'Banni',
            default => 'Inconnu'
        };
    }
}