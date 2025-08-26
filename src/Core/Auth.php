<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Models\User;

class Auth
{
    private ?User $user = null;
    private ?Session $session = null;
    private ?Database $db = null;

    // Cache pour les données utilisateur
    private ?array $userDataCache = null;

    /**
     * Constructeur public pour l'injection de dépendances
     */
    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;

        error_log("Auth::__construct - Initialisation effectuée");

        // Charger l'utilisateur depuis la session
        $this->checkSession();
    }

    /**
     * Récupère l'instance de Auth (legacy - kept for backward compatibility)
     * @deprecated Use dependency injection instead
     */
    public static function getInstance(?Session $session = null, ?Database $db = null): self
    {
        if ($session === null || $db === null) {
            throw new \RuntimeException("Session and Database must be provided");
        }
        // Return new instance for backward compatibility during transition
        return new self($session, $db);
    }


    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function check(): bool
    {
        // BYPASS: Si c'est un test bypass, toujours retourner true
        if (isset($_SESSION['dev_bypass_auth']) && $_SESSION['dev_bypass_auth'] === true) {
            error_log("Auth::check - BYPASS DÉTECTÉ - retour true");
            return true;
        }
        
        if ($this->user === null && $this->session !== null) {
            $this->checkSession();
        }

        return $this->user !== null;
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function user(): ?User
    {
        // BYPASS: Si c'est un test bypass, créer un utilisateur de test
        if (isset($_SESSION['dev_bypass_auth']) && $_SESSION['dev_bypass_auth'] === true) {
            if ($this->user === null) {
                // Créer un utilisateur de test simple
                $userData = [
                    'id' => 1,
                    'username' => $_SESSION['username'] ?? 'test-bypass',
                    'email' => $_SESSION['email'] ?? 'test@localhost',
                    'access_level' => $_SESSION['access_level'] ?? 5
                ];
                $this->user = $this->createUserObject($userData);
                error_log("Auth::user - BYPASS user créé: " . $this->user->username);
            }
            return $this->user;
        }
        
        if ($this->user === null) {
            $this->checkSession();
        }

        return $this->user;
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public function id(): ?int
    {
        // Vérifier d'abord en session
        if ($this->session) {
            $sessionUserId = $this->session->get('auth_user_id') ?? $_SESSION['auth_user_id'] ?? null;
            if ($sessionUserId && is_numeric($sessionUserId) && $sessionUserId > 0) {
                return (int)$sessionUserId;
            }
        }

        // Si pas d'utilisateur chargé, essayer de le charger
        if ($this->user === null) {
            $this->checkSession();
        }

        // Utiliser le cache si disponible
        if ($this->userDataCache && isset($this->userDataCache['id'])) {
            return (int)$this->userDataCache['id'];
        }

        if (!$this->user) {
            return null;
        }

        // Essayer d'accéder à l'ID de différentes manières
        if (isset($this->user->id)) {
            return (int)$this->user->id;
        }

        return null;
    }

    /**
     * Récupère le rôle de l'utilisateur connecté
     */
    public function role(): int
    {
        if (!$this->check()) {
            return 4; // Nouveau membre par défaut
        }

        // Récupérer l'autorisation depuis le cache ou l'utilisateur
        $userAutorisation = null;

        if ($this->userDataCache && isset($this->userDataCache['autorisation'])) {
            $userAutorisation = $this->userDataCache['autorisation'];
        } elseif ($this->userDataCache && isset($this->userDataCache['role_id'])) {
            $userAutorisation = $this->userDataCache['role_id'];
        } elseif ($this->user && isset($this->user->autorisation)) {
            $userAutorisation = $this->user->autorisation;
        } elseif ($this->user && isset($this->user->role_id)) {
            $userAutorisation = $this->user->role_id;
        }

        return (int)($userAutorisation ?? 4);
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->role() === 0;
    }

    /**
     * Vérifie si l'utilisateur est modérateur ou plus
     */
    public function isModerator(): bool
    {
        return in_array($this->role(), [0, 1]);
    }

    /**
     * Vérifie si l'utilisateur est accepté (niveau 2 ou plus)
     */
    public function isAccepted(): bool
    {
        return in_array($this->role(), [0, 1, 2]);
    }

    /**
     * Vérifie si l'utilisateur est en attente
     */
    public function isPending(): bool
    {
        return $this->role() === 4;
    }

    /**
     * Vérifie si l'utilisateur est banni
     */
    public function isBanned(): bool
    {
        return $this->role() === 5;
    }

    /**
     * Authentifie un utilisateur avec username/email et mot de passe
     */
    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        $query = "SELECT * FROM users WHERE username = ? OR mail = ? LIMIT 1";
        $result = $this->db->query($query, [$username, $username])->fetch();

        if (!$result) {
            error_log("Utilisateur non trouvé: $username");
            return false;
        }

        if (!isset($result['id'])) {
            error_log("Données utilisateur incomplètes pour: $username - ID manquant");
            return false;
        }

        if (!isset($result['password_hash']) && !isset($result['password'])) {
            error_log("Données utilisateur incomplètes pour: $username - Mot de passe manquant");
            return false;
        }

        // Vérifier le mot de passe (utiliser password_hash ou password selon disponibilité)
        $passwordField = $result['password_hash'] ?? $result['password'];
        if (!password_verify($password, $passwordField)) {
            // Tentative avec MD5 pour compatibilité
            if (md5($password) === $passwordField) {
                // Mettre à jour vers bcrypt
                $this->db->query(
                    "UPDATE users SET password_hash = ? WHERE id = ?",
                    [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $result['id']]
                );
            } else {
                error_log("Échec de vérification du mot de passe pour: $username");
                return false;
            }
        }

        // Stocker les données dans le cache
        $this->userDataCache = $result;

        // Créer l'objet User
        try {
            $this->user = $this->createUserObject($result);
            $this->login($this->user, $remember);
            error_log("Connexion réussie pour: $username");
            return true;
        } catch (\Exception $e) {
            error_log("Exception lors de la connexion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Connecte un utilisateur
     */

    public function login(User $user, bool $remember = false): void
    {
        $this->user = $user;

        // Récupérer l'ID de différentes manières - Version améliorée
        $userId = null;

        // Méthode 1 : cache (si disponible)
        if ($this->userDataCache && isset($this->userDataCache['id'])) {
            $userId = (int)$this->userDataCache['id'];
        }
        // Méthode 2 : via getAttribute (Model.php)
        elseif (method_exists($user, 'getAttribute') && $user->getAttribute('id')) {
            $userId = (int)$user->getAttribute('id');
        }
        // Méthode 3 : via propriété publique id
        elseif (isset($user->id)) {
            $userId = (int)$user->id;
        }
        // Méthode 4 : via __get magic method
        elseif (property_exists($user, 'id') && $user->id) {
            $userId = (int)$user->id;
        }
        // Méthode 5 : via la méthode getId() si elle existe
        elseif (method_exists($user, 'getId') && $user->getId()) {
            $userId = (int)$user->getId();
        }
        // Méthode 6 : via réflexion pour accéder aux attributs protégés
        else {
            try {
                $reflection = new \ReflectionObject($user);
                if ($reflection->hasProperty('attributes')) {
                    $attributesProperty = $reflection->getProperty('attributes');
                    $attributesProperty->setAccessible(true);
                    $attributes = $attributesProperty->getValue($user);
                    if (isset($attributes['id']) && $attributes['id']) {
                        $userId = (int)$attributes['id'];
                    }
                }
            } catch (\Exception $e) {
                error_log("Auth::login - Erreur réflexion: " . $e->getMessage());
            }
        }

        if (!$userId || $userId <= 0) {
            error_log("Auth::login - Impossible de récupérer l'ID utilisateur. Objet User: " . print_r($user, true));
            throw new \RuntimeException("Impossible de récupérer l'ID utilisateur pour la connexion");
        }

        error_log("Auth::login - ID utilisateur récupéré: $userId");

        // Stocker en session
        $this->session->set('auth_user_id', $userId);
        $this->session->set('is_authenticated', true);
        $_SESSION['auth_user_id'] = $userId;
        $_SESSION['is_authenticated'] = true;

        error_log("Auth::login - Session définie: auth_user_id=$userId, is_authenticated=true");

        // Cookie remember me
        if ($remember) {
            $token = $this->generateRememberToken();
            $this->storeRememberToken($userId, $token);
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
    public function logout(): bool
    {
        try {
            $this->user = null;
            $this->userDataCache = null;

            // Nettoyer la session
            unset($_SESSION['auth_user_id']);
            unset($_SESSION['is_authenticated']);

            if ($this->session) {
                $this->session->remove('auth_user_id');
                $this->session->remove('is_authenticated');
            }

            // Supprimer le cookie remember me
            if (isset($_COOKIE['remember_token'])) {
                $this->removeRememberToken($_COOKIE['remember_token']);
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Auth::logout - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique
     * Système de rôles mis à jour selon les spécifications
     */
    public function can(string $ability, $model = null): bool
    {
        if (!$this->check()) {
            error_log("Auth::can - Utilisateur non authentifié pour: $ability");
            return false;
        }

        $userRole = $this->role();

        error_log("Auth::can - Utilisateur ID=" . $this->id() . ", rôle=$userRole, ability='$ability'");

        // Utilisateur banni - aucune permission
        if ($userRole === 5) {
            error_log("Auth::can - Utilisateur banni, accès refusé");
            return false;
        }

        // Définition des permissions par rôle
        $permissions = [
            0 => [ // Admin - toutes permissions
                'view-content',
                'view-details',
                'view-sector',
                'view-route',
                'view-region',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-sector',
                'create-route',
                'create-region',
                'create-content',
                'update-sector',
                'update-route',
                'update-region',
                'edit-content',
                'delete-sector',
                'delete-route',
                'delete-region',
                'delete-content',
                'manage-users',
                'ban-users',
                'validate-users',
                'admin-panel',
                'create-ascent',
                'edit-ascent',
                'create-comment'
            ],
            1 => [ // Modérateur/Éditeur
                'view-content',
                'view-details',
                'view-sector',
                'view-route',
                'view-region',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-sector',
                'create-route',
                'create-region',
                'create-content',
                'update-sector',
                'update-route',
                'update-region',
                'edit-content',
                'delete-sector',
                'delete-route',
                'delete-region',
                'delete-content',
                'validate-users',
                'ban-users',
                'create-ascent',
                'edit-ascent',
                'create-comment'
            ],
            2 => [ // Utilisateur accepté (abonnement complet)
                'view-content',
                'view-details',
                'view-sector',
                'view-route',
                'view-region',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-ascent',
                'edit-own-ascent',
                'create-comment'
            ],
            3 => [ // Accès restreint (selon achat)
                'view-content-limited',
                'view-sample-sector',
                'view-sample-route',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-ascent',
                'edit-own-ascent',
                'create-comment'
            ],
            4 => [ // Nouveau membre (en attente)
                'view-profile-own',
                'view-pending'
            ]
        ];

        // Vérifier si l'utilisateur a la permission
        if (isset($permissions[$userRole]) && in_array($ability, $permissions[$userRole])) {
            error_log("Auth::can - Accès accordé (rôle $userRole)");
            return true;
        }

        // Vérification spéciale pour l'édition de contenu personnel
        if (in_array($ability, ['update-route', 'delete-route', 'update-sector', 'delete-sector', 'edit-own-ascent'])) {
            if ($model && isset($model->created_by) && $model->created_by == $this->id()) {
                error_log("Auth::can - Accès accordé pour édition de contenu personnel");
                return true;
            }
            if ($model && isset($model->user_id) && $model->user_id == $this->id()) {
                error_log("Auth::can - Accès accordé pour édition de contenu personnel (user_id)");
                return true;
            }
        }

        error_log("Auth::can - Accès refusé");
        return false;
    }

    /**
     * Vérifie les données de session pour une connexion
     */
    private function checkSession(): void
    {
        $userId = $_SESSION['auth_user_id'] ?? $this->session->get('auth_user_id') ?? null;

        if ($userId) {
            error_log("Auth::checkSession - ID utilisateur trouvé en session: $userId");

            $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
            $result = $this->db->query($query, [$userId])->fetch();

            if ($result) {
                // Stocker dans le cache
                $this->userDataCache = $result;

                try {
                    $this->user = $this->createUserObject($result);
                    $role = $result['autorisation'] ?? $result['role_id'] ?? 'non défini';
                    error_log("Auth::checkSession - User chargé avec rôle: " . $role);
                } catch (\Exception $e) {
                    error_log("Auth::checkSession - Erreur création User: " . $e->getMessage());
                    $this->user = null;
                    $this->userDataCache = null;
                }
            }
        }

        // Vérifier le cookie remember me si pas d'utilisateur
        if (!$this->user && isset($_COOKIE['remember_token'])) {
            $userId = $this->getUserIdFromRememberToken($_COOKIE['remember_token']);
            if ($userId) {
                $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
                $result = $this->db->query($query, [$userId])->fetch();
                if ($result) {
                    $this->userDataCache = $result;
                    $this->user = $this->createUserObject($result);
                    $this->login($this->user, true);
                }
            }
        }
    }

    /**
     * Crée un objet User unifié depuis les données de la base
     */
    private function createUserObject(array $data): User
    {
        // S'assurer que toutes les données nécessaires sont présentes
        if (!isset($data['id'])) {
            throw new \RuntimeException("Données utilisateur incomplètes - ID manquant");
        }

        // Vérifier le rôle/autorisation (peut être autorisation ou role_id)
        if (!isset($data['autorisation']) && !isset($data['role_id'])) {
            throw new \RuntimeException("Données utilisateur incomplètes - Rôle manquant");
        }

        // Créer l'objet User standard
        $user = new User();

        // Utiliser la réflexion pour forcer l'assignation des attributs protégés
        $reflection = new \ReflectionObject($user);

        if ($reflection->hasProperty('attributes')) {
            $attributesProperty = $reflection->getProperty('attributes');
            $attributesProperty->setAccessible(true);
            $attributesProperty->setValue($user, $data);
        }

        // Assigner aussi les propriétés publiques si elles existent
        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $property->setAccessible(true);
                $property->setValue($user, $value);
            }
        }

        return $user;
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
        // Créer la table si elle n'existe pas
        $this->db->query("
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user (user_id)
            )
        ");

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
        $this->db->query("DELETE FROM remember_tokens WHERE token = ?", [$hashedToken]);
    }
}
