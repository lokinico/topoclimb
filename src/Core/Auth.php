<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Models\User;

class Auth
{
    private static $instance;
    private ?User $user = null;
    private Session $session;
    private Database $db;
    
    private function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;
        $this->checkSession();
    }
    
    public static function getInstance(Session $session, Database $db): self
    {
        if (!self::$instance) {
            self::$instance = new self($session, $db);
        }
        return self::$instance;
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function check(): bool
    {
        return $this->user !== null;
    }
    
    /**
     * Récupère l'utilisateur connecté
     */
    public function user(): ?User
    {
        return $this->user;
    }
    
    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public function id(): ?int
    {
        return $this->user ? $this->user->id : null;
    }
    
    /**
     * Authentifie un utilisateur avec username/email et mot de passe
     */
    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        // Recherche l'utilisateur par username ou email
        $user = User::where('username', $username)
            ->orWhere('mail', $username)
            ->first();
        
        if (!$user) {
            return false;
        }
        
        // Vérifie le mot de passe
        if (!password_verify($password, $user->password)) {
            return false;
        }
        
        // Connecte l'utilisateur
        $this->login($user, $remember);
        
        return true;
    }
    
    /**
     * Connecte un utilisateur
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->user = $user;
        
        // Régénère l'ID de session pour éviter les attaques par fixation
        $this->session->regenerate();
        
        // Stocke l'ID utilisateur en session
        $this->session->set('auth_user_id', $user->id);
        
        // Gère la fonctionnalité "Se souvenir de moi"
        if ($remember) {
            $token = $this->generateRememberToken();
            $this->storeRememberToken($user->id, $token);
            
            // Définit un cookie qui expire dans 30 jours
            setcookie(
                'remember_token',
                $token,
                time() + 60 * 60 * 24 * 30,
                '/',
                '',
                true,
                true
            );
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        $this->user = null;
        
        // Supprime les données de session
        $this->session->remove('auth_user_id');
        
        // Régénère l'ID de session
        $this->session->regenerate();
        
        // Supprime le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            // Supprime le token de la base de données
            $this->removeRememberToken($_COOKIE['remember_token']);
            
            // Expire le cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Vérifie si l'utilisateur a une permission spécifique
     */
    public function can(string $ability, $model = null): bool
    {
        if (!$this->check()) {
            return false;
        }
        
        // Implémentation simple des permissions basée sur le niveau d'autorisation
        $adminAbilities = [
            'create-sector',
            'update-sector',
            'delete-sector',
            'create-route',
            'update-route',
            'delete-route',
            'manage-users',
            'manage-site'
        ];
        
        $moderatorAbilities = [
            'create-sector',
            'update-sector',
            'create-route',
            'update-route'
        ];
        
        // L'admin (autorisation = 1) peut tout faire
        if ($this->user->autorisation == '1') {
            return true;
        }
        
        // Le modérateur (autorisation = 2) peut faire certaines actions
        if ($this->user->autorisation == '2' && in_array($ability, $moderatorAbilities)) {
            return true;
        }
        
        // Vérification spécifique pour la mise à jour d'une voie ou d'un secteur par son créateur
        if (($ability === 'update-route' || $ability === 'delete-route') && $model && $model->created_by === $this->user->id) {
            return true;
        }
        
        if (($ability === 'update-sector' || $ability === 'delete-sector') && $model && $model->created_by === $this->user->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Vérifie les données de session pour une connexion
     */
    private function checkSession(): void
    {
        // Vérifie si un utilisateur est déjà en session
        $userId = $this->session->get('auth_user_id');
        
        if ($userId) {
            $this->user = User::find($userId);
            return;
        }
        
        // Vérifie le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $userId = $this->getUserIdFromRememberToken($token);
            
            if ($userId) {
                $user = User::find($userId);
                
                if ($user) {
                    $this->login($user);
                }
            }
        }
    }
    
    /**
     * Génère un token pour "Se souvenir de moi"
     */
    private function generateRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Stocke un token "Se souvenir de moi" en base de données
     */
    private function storeRememberToken(int $userId, string $token): void
    {
        // Stocke dans une table 'remember_tokens' (à créer)
        $hashedToken = hash('sha256', $token);
        
        $this->db->query(
            "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, $hashedToken, date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30)]
        );
    }
    
    /**
     * Récupère l'ID utilisateur à partir d'un token "Se souvenir de moi"
     */
    private function getUserIdFromRememberToken(string $token): ?int
    {
        $hashedToken = hash('sha256', $token);
        
        $result = $this->db->query(
            "SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > ?",
            [$hashedToken, date('Y-m-d H:i:s')]
        )->fetch();
        
        return $result ? (int) $result['user_id'] : null;
    }
    
    /**
     * Supprime un token "Se souvenir de moi"
     */
    private function removeRememberToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        
        $this->db->query(
            "DELETE FROM remember_tokens WHERE token = ?",
            [$hashedToken]
        );
    }
}