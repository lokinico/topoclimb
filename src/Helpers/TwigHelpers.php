<?php

namespace TopoclimbCH\Helpers;

use TopoclimbCH\Core\Auth;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour les helpers d'authentification et de rôles
 */
class TwigHelpers extends AbstractExtension
{
    private ?Auth $auth = null;

    public function __construct(?Auth $auth = null)
    {
        $this->auth = $auth;
    }

    /**
     * Fonctions Twig disponibles
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('auth', [$this, 'auth']),
            new TwigFunction('auth_user', [$this, 'authUser']),
            new TwigFunction('auth_id', [$this, 'authId']),
            new TwigFunction('auth_role', [$this, 'authRole']),
            new TwigFunction('is_admin', [$this, 'isAdmin']),
            new TwigFunction('is_moderator', [$this, 'isModerator']),
            new TwigFunction('is_accepted', [$this, 'isAccepted']),
            new TwigFunction('is_pending', [$this, 'isPending']),
            new TwigFunction('is_banned', [$this, 'isBanned']),
            new TwigFunction('can', [$this, 'can']),
            new TwigFunction('role_name', [$this, 'roleName']),
            new TwigFunction('role_badge', [$this, 'roleBadge']),
            new TwigFunction('has_access_to', [$this, 'hasAccessTo']),
            new TwigFunction('is_own_content', [$this, 'isOwnContent']),
            new TwigFunction('csrf_token', [$this, 'csrfToken']),
            new TwigFunction('url', [$this, 'url']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('is_active', [$this, 'isActive']),
            // TODO: Ajouté - Fonctions de formatage pour routes
            new TwigFunction('format_length', [$this, 'formatLength']),
            new TwigFunction('format_beauty', [$this, 'formatBeauty']),
            new TwigFunction('format_style', [$this, 'formatStyle'])
        ];
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function auth(): bool
    {
        return $this->auth && $this->auth->check();
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function authUser()
    {
        return $this->auth ? $this->auth->user() : null;
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public function authId(): ?int
    {
        return $this->auth ? $this->auth->id() : null;
    }

    /**
     * Récupère le rôle de l'utilisateur connecté
     */
    public function authRole(): int
    {
        return $this->auth ? $this->auth->role() : 4;
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->auth && $this->auth->isAdmin();
    }

    /**
     * Vérifie si l'utilisateur est modérateur ou plus
     */
    public function isModerator(): bool
    {
        return $this->auth && $this->auth->isModerator();
    }

    /**
     * Vérifie si l'utilisateur est accepté
     */
    public function isAccepted(): bool
    {
        return $this->auth && $this->auth->isAccepted();
    }

    /**
     * Vérifie si l'utilisateur est en attente
     */
    public function isPending(): bool
    {
        return $this->auth && $this->auth->isPending();
    }

    /**
     * Vérifie si l'utilisateur est banni
     */
    public function isBanned(): bool
    {
        return $this->auth && $this->auth->isBanned();
    }

    /**
     * Vérifie si l'utilisateur a une permission
     */
    public function can(string $ability, $model = null): bool
    {
        return $this->auth && $this->auth->can($ability, $model);
    }

    /**
     * Récupère le nom du rôle
     */
    public function roleName(int $role = null): string
    {
        if ($role === null) {
            $role = $this->authRole();
        }

        $roles = [
            0 => 'Administrateur',
            1 => 'Modérateur',
            2 => 'Utilisateur accepté',
            3 => 'Accès restreint',
            4 => 'Nouveau membre',
            5 => 'Banni'
        ];

        return $roles[$role] ?? 'Inconnu';
    }

    /**
     * Génère un badge HTML pour le rôle
     */
    public function roleBadge(int $role = null): string
    {
        if ($role === null) {
            $role = $this->authRole();
        }

        $badges = [
            0 => '<span class="badge bg-danger">Admin</span>',
            1 => '<span class="badge bg-warning">Modérateur</span>',
            2 => '<span class="badge bg-success">Accepté</span>',
            3 => '<span class="badge bg-info">Restreint</span>',
            4 => '<span class="badge bg-secondary">En attente</span>',
            5 => '<span class="badge bg-dark">Banni</span>'
        ];

        return $badges[$role] ?? '<span class="badge bg-light">Inconnu</span>';
    }

    /**
     * Vérifie si l'utilisateur a accès à un type de contenu
     */
    public function hasAccessTo(string $contentType): bool
    {
        if (!$this->auth()) {
            return false;
        }

        $role = $this->authRole();

        switch ($contentType) {
            case 'regions':
            case 'sectors':
            case 'routes':
                return in_array($role, [0, 1, 2]) || ($role === 3 && $this->hasLimitedAccess());

            case 'ascents':
            case 'favorites':
                return in_array($role, [0, 1, 2, 3]);

            case 'create':
            case 'edit':
            case 'delete':
                return in_array($role, [0, 1]);

            case 'admin':
                return $role === 0;

            case 'moderation':
                return in_array($role, [0, 1]);

            default:
                return false;
        }
    }

    /**
     * Vérifie si le contenu appartient à l'utilisateur connecté
     */
    public function isOwnContent($content): bool
    {
        if (!$this->auth() || !$content) {
            return false;
        }

        $userId = $this->authId();

        // Vérifier différentes propriétés possibles
        if (isset($content->user_id) && $content->user_id == $userId) {
            return true;
        }
        if (isset($content->created_by) && $content->created_by == $userId) {
            return true;
        }
        if (isset($content->id) && $content->id == $userId) {
            return true;
        }

        return false;
    }

    /**
     * Génère un token CSRF (placeholder - à implémenter selon votre système)
     */
    public function csrfToken(): string
    {
        // Cette fonction devrait retourner le token CSRF depuis votre système
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Génère une URL
     */
    public function url(string $path = ''): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Génère une URL pour un asset
     */
    public function asset(string $path): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Vérifie si une route est active
     */
    public function isActive(string $path): bool
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        $currentPath = parse_url($currentPath, PHP_URL_PATH) ?: '/';

        // Correspondance exacte
        if ($currentPath === $path) {
            return true;
        }

        // Correspondance de début pour les sections
        if ($path !== '/' && str_starts_with($currentPath, $path)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur restreint a un accès limité
     * (ici on pourrait vérifier les achats/abonnements)
     */
    private function hasLimitedAccess(): bool
    {
        // Pour l'instant, on accorde un accès limité à tous les utilisateurs restreints
        // Dans le futur, cette méthode vérifiera les achats/abonnements
        return true;
    }

    /**
     * Méthodes utilitaires pour les templates
     */

    /**
     * Formate une date
     */
    public function formatDate($date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date instanceof \DateTime ? $date->format($format) : '';
    }

    /**
     * Tronque un texte
     */
    public function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . $suffix;
    }

    /**
     * Génère une classe CSS selon le rôle
     */
    public function roleClass(int $role = null): string
    {
        if ($role === null) {
            $role = $this->authRole();
        }

        $classes = [
            0 => 'user-admin',
            1 => 'user-moderator',
            2 => 'user-accepted',
            3 => 'user-restricted',
            4 => 'user-pending',
            5 => 'user-banned'
        ];

        return $classes[$role] ?? 'user-unknown';
    }

    /**
     * Vérifie si l'utilisateur peut voir le contenu premium
     */
    public function canViewPremium(): bool
    {
        $role = $this->authRole();
        return in_array($role, [0, 1, 2]); // Admin, Modérateur, Accepté
    }

    /**
     * Vérifie si l'utilisateur peut voir le contenu restreint
     */
    public function canViewRestricted(): bool
    {
        $role = $this->authRole();
        return in_array($role, [0, 1, 2, 3]); // Tous sauf nouveau membre et banni
    }

    /**
     * Génère un message d'accès selon le rôle
     */
    public function getAccessMessage(): string
    {
        if (!$this->auth()) {
            return 'Connectez-vous pour accéder au contenu.';
        }

        $role = $this->authRole();

        switch ($role) {
            case 4:
                return 'Votre compte est en attente de validation.';
            case 5:
                return 'Votre compte a été suspendu.';
            case 3:
                return 'Accès limité selon votre abonnement.';
            default:
                return 'Accès complet au contenu.';
        }
    }
    
    /**
     * TODO: Ajouté - Formate la longueur d'une route
     */
    public function formatLength($length): string
    {
        if (!$length || $length <= 0) {
            return '-';
        }
        
        return $length . 'm';
    }
    
    /**
     * TODO: Ajouté - Formate la note de beauté en étoiles
     */
    public function formatBeauty($beauty): string
    {
        if (!$beauty || $beauty <= 0) {
            return '';
        }
        
        $rating = (float) $beauty;
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        $stars = str_repeat('★', $fullStars);
        if ($halfStar) {
            $stars .= '☆';
        }
        $stars .= str_repeat('☆', $emptyStars);
        
        return '<span class="beauty-rating" title="Beauté: ' . $rating . '/5">' . $stars . '</span>';
    }
    
    /**
     * TODO: Ajouté - Formate le style d'escalade
     */
    public function formatStyle($style): string
    {
        if (!$style) {
            return '-';
        }
        
        $styles = [
            'sport' => 'Sportive',
            'trad' => 'Trad',
            'mixed' => 'Mixte',
            'boulder' => 'Bloc',
            'multipitch' => 'Grandes voies',
            'aid' => 'Artificielle'
        ];
        
        return $styles[$style] ?? ucfirst($style);
    }
}
