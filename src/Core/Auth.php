<?php

namespace TopoclimbCH\Core;

use TopoclimbCH\Models\User;

class Auth
{
    private static ?Auth $instance = null;
    private ?User $user = null;
    private ?Session $session = null;
    private ?Database $db = null;
    private bool $initialized = false;

    // Cache pour les données utilisateur
    private ?array $userDataCache = null;

    private function __construct(?Session $session = null, ?Database $db = null)
    {
        if ($session !== null && $db !== null) {
            $this->initialize($session, $db);
        }
    }

    /**
     * Initialise ou réinitialise l'instance avec les dépendances
     */
    private function initialize(Session $session, Database $db): void
    {
        $this->session = $session;
        $this->db = $db;
        $this->initialized = true;

        error_log("Auth::initialize - Initialisation effectuée");

        // Charger l'utilisateur depuis la session
        $this->checkSession();
    }

    /**
     * Récupère l'instance de Auth
     */
    public static function getInstance(?Session $session = null, ?Database $db = null): self
    {
        if (self::$instance === null) {
            if ($session === null || $db === null) {
                throw new \RuntimeException("Container must be provided first time");
            }
            self::$instance = new self($session, $db);
            error_log("Auth::getInstance - Nouvelle instance créée");
        } elseif ($session !== null && $db !== null && !self::$instance->initialized) {
            self::$instance->initialize($session, $db);
            error_log("Auth::getInstance - Instance existante initialisée");
        }

        return self::$instance;
    }

    /**
     * Vérifie si l'instance est initialisée
     */
    public function validate(): bool
    {
        return $this->initialized && $this->user !== null;
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function check(): bool
    {
        if ($this->initialized && $this->user === null && $this->session !== null) {
            $this->checkSession();
        }

        return $this->user !== null;
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function user(): ?User
    {
        if ($this->user === null && $this->initialized) {
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
        if ($this->user === null && $this->initialized) {
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

        if (!isset($result['id']) || !isset($result['password'])) {
            error_log("Données utilisateur incomplètes pour: $username");
            return false;
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $result['password'])) {
            // Tentative avec MD5 pour compatibilité
            if (md5($password) === $result['password']) {
                // Mettre à jour vers bcrypt
                $this->db->query(
                    "UPDATE users SET password = ? WHERE id = ?",
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

        // Récupérer l'ID depuis le cache ou l'objet
        $userId = null;
        if ($this->userDataCache && isset($this->userDataCache['id'])) {
            $userId = (int)$this->userDataCache['id'];
        } elseif (isset($user->id)) {
            $userId = (int)$user->id;
        }

        if (!$userId) {
            throw new \RuntimeException("Impossible de récupérer l'ID utilisateur");
        }

        error_log("Auth::login - ID utilisateur: $userId");

        // Stocker en session
        $this->session->set('auth_user_id', $userId);
        $this->session->set('is_authenticated', true);
        $_SESSION['auth_user_id'] = $userId;
        $_SESSION['is_authenticated'] = true;

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
     * Gestion complète des niveaux 0-5
     */
    public function can(string $ability, $model = null): bool
    {
        if (!$this->check()) {
            error_log("Auth::can - Utilisateur non authentifié pour: $ability");
            return false;
        }

        // Récupérer l'autorisation depuis le cache ou l'utilisateur
        $userAutorisation = null;

        // D'abord essayer le cache
        if ($this->userDataCache && isset($this->userDataCache['autorisation'])) {
            $userAutorisation = $this->userDataCache['autorisation'];
        }
        // Ensuite essayer l'objet User
        elseif ($this->user) {
            if (isset($this->user->autorisation)) {
                $userAutorisation = $this->user->autorisation;
            } elseif (method_exists($this->user, 'getAttribute')) {
                $userAutorisation = $this->user->getAttribute('autorisation');
            }
        }

        // Si toujours pas trouvé, recharger depuis la DB
        if ($userAutorisation === null || $userAutorisation === '') {
            $userId = $this->id();
            if ($userId) {
                $query = "SELECT autorisation FROM users WHERE id = ? LIMIT 1";
                $result = $this->db->query($query, [$userId])->fetch();
                if ($result && isset($result['autorisation'])) {
                    $userAutorisation = $result['autorisation'];
                    // Mettre à jour le cache
                    if ($this->userDataCache) {
                        $this->userDataCache['autorisation'] = $userAutorisation;
                    }
                }
            }
        }

        error_log("Auth::can - Utilisateur ID=" . $this->id() . ", autorisation='" . $userAutorisation . "', ability='" . $ability . "'");

        if ($userAutorisation === null || $userAutorisation === '') {
            error_log("Auth::can - Autorisation vide ou inaccessible");
            return false;
        }

        // Convertir en entier pour comparaison
        $authLevel = (int)$userAutorisation;

        // Niveau 5 (banni) - aucune permission
        if ($authLevel === 5) {
            error_log("Auth::can - Utilisateur banni, accès refusé");
            return false;
        }

        // Définition des capacités par niveau
        $permissions = [
            0 => [ // Admin - toutes permissions
                'create-sector',
                'update-sector',
                'delete-sector',
                'create-route',
                'update-route',
                'delete-route',
                'manage-users',
                'manage-site',
                'view-sector',
                'view-route',
                'view-profile',
                'create-comment',
                'view-details',
                'view-sample-sector',
                'view-sample-route',
                'view-public-content'
            ],
            1 => [ // Rédacteur/Modérateur
                'create-sector',
                'update-sector',
                'create-route',
                'update-route',
                'view-sector',
                'view-route',
                'view-profile',
                'create-comment',
                'view-details',
                'view-sample-sector',
                'view-sample-route',
                'view-public-content'
            ],
            2 => [ // Viewer/Membre actif
                'view-sector',
                'view-route',
                'view-profile',
                'create-comment',
                'view-details',
                'view-sample-sector',
                'view-sample-route',
                'view-public-content'
            ],
            3 => [ // Accès restreint (compte d'essai)
                'view-sample-sector',
                'view-sample-route',
                'view-public-content'
            ],
            4 => [ // Nouveau membre (avec restrictions)
                'view-public-content',
                'view-profile'
            ]
        ];

        // Vérifier si l'utilisateur a la permission
        if (isset($permissions[$authLevel]) && in_array($ability, $permissions[$authLevel])) {
            error_log("Auth::can - Accès accordé (niveau $authLevel)");
            return true;
        }

        // Vérification spéciale pour l'édition de contenu personnel
        if (in_array($ability, ['update-route', 'delete-route', 'update-sector', 'delete-sector'])) {
            if ($model && isset($model->created_by) && $model->created_by == $this->id()) {
                error_log("Auth::can - Accès accordé pour édition de contenu personnel");
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
                    error_log("Auth::checkSession - User chargé avec autorisation: " . $result['autorisation']);
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
        if (!isset($data['id']) || !isset($data['autorisation'])) {
            throw new \RuntimeException("Données utilisateur incomplètes");
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
