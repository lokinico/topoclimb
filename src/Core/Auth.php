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

        // Log d'initialisation
        error_log("Auth::initialize - Initialisation effectuée");

        // Charger l'utilisateur depuis la session
        $this->checkSession();
    }

    /**
     * Récupère l'instance de Auth
     * @param Session|null $session
     * @param Database|null $db
     * @return Auth
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
            // Initialiser uniquement si ce n'est pas déjà fait
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
        // Si pas initialisé ou pas d'utilisateur, recharger depuis la session
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
        // Recharger l'utilisateur si nécessaire
        if ($this->user === null && $this->initialized) {
            $this->checkSession();
        }

        return $this->user;
    }

    /**
     * Méthode pour extraire de façon fiable l'ID d'un objet User - OPTIMISÉE
     * NOUVELLE MÉTHODE OPTIMISÉE pour éviter les requêtes SQL inutiles
     */
    private function extractUserId(User $user): int
    {
        // 1. PRIORITÉ: Vérifier d'abord l'ID directement accessible
        if (isset($user->id) && is_numeric($user->id) && $user->id > 0) {
            return (int)$user->id;
        }

        // 2. Vérifier si l'attribut 'attributes' est accessible via réflexion
        $reflection = new \ReflectionObject($user);
        $idValue = null;

        if ($reflection->hasProperty('attributes')) {
            $attributesProperty = $reflection->getProperty('attributes');
            $attributesProperty->setAccessible(true);
            $attributes = $attributesProperty->getValue($user);

            if (isset($attributes['id']) && is_numeric($attributes['id']) && $attributes['id'] > 0) {
                $idValue = (int)$attributes['id'];
                error_log("extractUserId: Trouvé via réflexion attributes['id'] = " . $idValue);
                return $idValue;
            }
        }

        // 3. Si non trouvé, essayer la réflexion directe sur 'id'
        if ($reflection->hasProperty('id')) {
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idValue = $idProperty->getValue($user);

            if (is_numeric($idValue) && $idValue > 0) {
                error_log("extractUserId: Trouvé via réflexion propriété id = " . $idValue);
                return (int)$idValue;
            }
        }

        // 4. Essayer la méthode __get si disponible
        if (method_exists($user, '__get')) {
            try {
                $idValue = $user->__get('id');
                if (is_numeric($idValue) && $idValue > 0) {
                    error_log("extractUserId: Trouvé via __get = " . $idValue);
                    return (int)$idValue;
                }
            } catch (\Exception $e) {
                error_log("extractUserId: Erreur __get: " . $e->getMessage());
            }
        }

        // 5. OPTIMISATION: Vérifier d'abord en session avant la requête SQL
        if ($this->session) {
            $sessionUserId = $this->session->get('auth_user_id') ?? $_SESSION['auth_user_id'] ?? null;
            if ($sessionUserId && is_numeric($sessionUserId) && $sessionUserId > 0) {
                error_log("extractUserId: Trouvé en session = " . $sessionUserId);
                return (int)$sessionUserId;
            }
        }

        // 6. DERNIER RECOURS: requête SQL seulement si on a le mail ou username
        if ($this->db && (isset($user->mail) || isset($user->username))) {
            $field = isset($user->mail) && !empty($user->mail) ? 'mail' : 'username';
            $value = $field === 'mail' ? $user->mail : $user->username;

            if (!empty($value)) {
                try {
                    $query = "SELECT id FROM users WHERE $field = ? LIMIT 1";
                    $result = $this->db->query($query, [$value])->fetch();

                    if ($result && isset($result['id']) && is_numeric($result['id']) && $result['id'] > 0) {
                        $idValue = (int)$result['id'];
                        error_log("extractUserId: Trouvé via requête SQL = " . $idValue);
                        return $idValue;
                    }
                } catch (\Exception $e) {
                    error_log("extractUserId: Erreur SQL: " . $e->getMessage());
                }
            }
        }

        // En cas d'échec complet, lancer une exception
        error_log("ERREUR CRITIQUE: Impossible d'extraire un ID utilisateur valide");
        error_log("User object debug: " . print_r($user, true));
        throw new \RuntimeException("Impossible d'extraire un ID utilisateur valide");
    }

    /**
     * Récupère l'ID de l'utilisateur connecté - OPTIMISÉE
     * Modification: Accès plus robuste à l'ID avec priorité session
     */
    public function id(): ?int
    {
        // OPTIMISATION: Vérifier d'abord directement en session
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

        if (!$this->user) {
            error_log("Auth::id - Aucun utilisateur connecté.");
            return null;
        }

        try {
            return $this->extractUserId($this->user);
        } catch (\Throwable $e) {
            error_log("Auth::id - Exception lors de l'extraction de l'ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Authentifie un utilisateur avec username/email et mot de passe
     */
    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        // Recherche directement avec un query SQL qui correspond mieux à votre BDD
        $query = "SELECT * FROM users WHERE username = ? OR mail = ? LIMIT 1";
        $result = $this->db->query($query, [$username, $username])->fetch();

        if (!$result) {
            error_log("Utilisateur non trouvé: $username");
            return false;
        }

        // Vérifier si les champs nécessaires existent
        if (!isset($result['id']) || !isset($result['password'])) {
            error_log("Données utilisateur incomplètes pour: $username");
            error_log("Champs disponibles: " . json_encode(array_keys($result)));
            return false;
        }

        // Vérifier le mot de passe (s'assurer que $result['password'] existe)
        if (empty($result['password'])) {
            error_log("Mot de passe manquant pour l'utilisateur: $username");
            return false;
        }

        // Ajouter un log pour afficher le format du mot de passe stocké
        error_log("Format du mot de passe stocké: " . substr($result['password'], 0, 13) . "...");

        // Vérifier le mot de passe avec différentes méthodes
        $passwordVerified = false;

        // Méthode 1: Vérification standard avec password_verify
        if (password_verify($password, $result['password'])) {
            error_log("Mot de passe vérifié avec password_verify pour: $username");
            $passwordVerified = true;
        }
        // Méthode 2: Pour les mots de passe non hachés ou hachés différemment
        // Uniquement pour la migration, à retirer ensuite
        else if ($password === $result['password']) {
            error_log("Mot de passe vérifié avec comparaison directe pour: $username");
            $passwordVerified = true;
            // Mettre à jour le hash du mot de passe pour les futures connexions
            $this->db->query(
                "UPDATE users SET password = ? WHERE id = ?",
                [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $result['id']]
            );
        }
        // Méthode 3: Essayer MD5 pour la compatibilité avec d'anciens systèmes
        else if (md5($password) === $result['password']) {
            error_log("Mot de passe vérifié avec MD5 pour: $username");
            $passwordVerified = true;
            // Mettre à jour le hash
            $this->db->query(
                "UPDATE users SET password = ? WHERE id = ?",
                [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $result['id']]
            );
        }

        if (!$passwordVerified) {
            error_log("Échec de vérification du mot de passe pour: $username");
            return false;
        }

        // Créer l'objet User à partir du résultat
        error_log("Création de l'objet User avec les données: " . json_encode(array_keys($result)));
        try {
            // Ajouter ce log pour voir les données exactes
            error_log("Données User complètes: " . json_encode($result));

            // SOLUTION: Mode sécurisé pour créer l'objet User
            // Enlever le champ reset_token_expires_at s'il existe et n'est pas dans le schéma
            if (isset($result['reset_token_expires_at'])) {
                error_log("Suppression du champ reset_token_expires_at non conforme");
                unset($result['reset_token_expires_at']);
            }

            // Création de l'objet User avec les données filtrées
            $user = new User($result);

            // MODIFICATION: Vérification de l'ID avant login
            error_log("Vérification de l'ID: user->id = " . ($user->id ?? 'non défini') . ", result['id'] = " . $result['id']);

            // Connecte l'utilisateur
            $this->login($user, $remember);
            error_log("Connexion réussie pour: $username");
            return true;
        } catch (\Exception $e) {
            // Solution de secours: connexion directe sans classe User
            error_log("Exception lors de la création de l'utilisateur: " . $e->getMessage());
            error_log("Tentative de connexion directe sans classe User");

            try {
                // MODIFICATION: S'assurer que l'ID est correct dans l'objet créé
                $userId = (int)$result['id'];

                // Créer un objet simple qui implémente les méthodes essentielles
                $user = new class($result, $userId) {
                    private array $data;
                    public int $id;
                    public string $autorisation;

                    public function __construct(array $data, int $userId)
                    {
                        $this->data = $data;
                        $this->id = $userId; // ID explicite
                        $this->autorisation = $data['autorisation'];
                        error_log("Objet alternative créé avec ID: " . $this->id);
                    }

                    public function __get($name)
                    {
                        return $this->data[$name] ?? null;
                    }

                    public function __isset($name)
                    {
                        return isset($this->data[$name]);
                    }

                    // Méthodes essentielles pour la compatibilité
                    public function isAdmin(): bool
                    {
                        // CORRECTION pour reconnaître le niveau 0 comme admin
                        return $this->autorisation === '0';
                    }

                    public function isModerator(): bool
                    {
                        // CORRECTION pour reconnaître les niveaux corrects
                        return in_array($this->autorisation, ['0', '1']);
                    }
                };

                // Connexion manuelle SANS régénération de session
                $this->user = $user;

                // Stocker l'ID utilisateur en session - DIRECTEMENT dans $_SESSION aussi
                $this->session->set('auth_user_id', $userId);
                $_SESSION['auth_user_id'] = $userId;
                $_SESSION['is_authenticated'] = true;

                error_log("Connexion alternative réussie pour: $username avec autorisation: " . $result['autorisation']);
                error_log("ID utilisateur stocké en session: $userId");

                return true;
            } catch (\Exception $fallbackError) {
                error_log("Échec de la connexion alternative: " . $fallbackError->getMessage());
                return false;
            }
        }
    }

    /**
     * Connecte un utilisateur
     * MÉTHODE MODIFIÉE
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->user = $user;

        // MODIFICATION CRITIQUE: Récupérer l'ID correctement à partir de l'objet User
        try {
            // Utiliser la méthode d'extraction robuste
            $userId = $this->extractUserId($user);

            error_log("Auth::login - ID extrait correctement: $userId");

            // Double stockage pour plus de sécurité
            $this->session->set('auth_user_id', $userId);
            $this->session->set('is_authenticated', true);

            // Stockage direct dans $_SESSION pour garantir la disponibilité immédiate
            $_SESSION['auth_user_id'] = $userId;
            $_SESSION['is_authenticated'] = true;

            error_log("Auth::login - ID utilisateur $userId stocké en session");

            // Gère la fonctionnalité "Se souvenir de moi"
            if ($remember) {
                $token = $this->generateRememberToken();
                $this->storeRememberToken($userId, $token);

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

                error_log("Auth::login - Cookie 'remember_token' défini (30 jours)");
            }
        } catch (\Exception $e) {
            error_log("ERREUR CRITIQUE dans Auth::login: " . $e->getMessage());

            // Tentative de récupération avec ID direct depuis la base de données
            if ($user->mail || $user->username) {
                $field = $user->mail ? 'mail' : 'username';
                $value = $user->mail ?: $user->username;

                try {
                    $query = "SELECT id FROM users WHERE $field = ? LIMIT 1";
                    $result = $this->db->query($query, [$value])->fetch();

                    if ($result && isset($result['id'])) {
                        $userId = (int)$result['id'];

                        // Stockage d'urgence
                        $this->session->set('auth_user_id', $userId);
                        $_SESSION['auth_user_id'] = $userId;
                        $this->session->set('is_authenticated', true);
                        $_SESSION['is_authenticated'] = true;

                        error_log("Auth::login - ID récupéré en urgence et stocké: $userId");
                    }
                } catch (\Exception $innerEx) {
                    error_log("Auth::login - Échec de récupération d'urgence: " . $innerEx->getMessage());
                    throw new \RuntimeException("Impossible de stocker l'ID utilisateur en session");
                }
            } else {
                throw $e; // Relancer l'exception si on ne peut pas récupérer
            }
        }
    }

    /**
     * Déconnecte l'utilisateur avec gestion d'erreurs
     * 
     * @return bool
     */
    public function logout(): bool
    {
        try {
            // Capturer l'ID utilisateur avant nettoyage
            $userId = null;
            if ($this->user) {
                try {
                    $userId = $this->extractUserId($this->user);
                    error_log("Auth::logout - Déconnexion de l'utilisateur: " . $userId);
                } catch (\Throwable $e) {
                    error_log("Auth::logout - Impossible d'extraire l'ID: " . $e->getMessage());
                }
            }

            // Nettoyer l'objet utilisateur
            $this->user = null;

            // Nettoyer les données de session liées à l'auth
            unset($_SESSION['auth_user_id']);
            unset($_SESSION['is_authenticated']);
            unset($_SESSION['user_authenticated']);

            if ($this->session) {
                $this->session->remove('auth_user_id');
                $this->session->remove('is_authenticated');
                $this->session->remove('user_authenticated');
            }

            // Supprimer le cookie "Se souvenir de moi"
            if (isset($_COOKIE['remember_token'])) {
                try {
                    $this->removeRememberToken($_COOKIE['remember_token']);
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    error_log("Auth::logout - Cookie 'remember_token' supprimé");
                } catch (\Throwable $e) {
                    error_log("Auth::logout - Erreur suppression remember_token: " . $e->getMessage());
                }
            }

            error_log("Auth::logout - Données d'authentification nettoyées");
            return true;
        } catch (\Throwable $e) {
            error_log("Auth::logout - Exception: " . $e->getMessage());
            // Nettoyage minimum en cas d'erreur
            $this->user = null;
            return false;
        }
    }
    /**
     * Vérifie si l'utilisateur a une permission spécifique
     */
    public function can(string $ability, $model = null): bool
    {
        if (!$this->check()) {
            error_log("Auth::can - Utilisateur non authentifié pour: $ability");
            return false;
        }

        // Debug: Afficher les informations de l'utilisateur
        $userAutorisation = null;
        try {
            // Essayer d'accéder à l'autorisation de différentes manières
            if (isset($this->user->autorisation)) {
                $userAutorisation = $this->user->autorisation;
            } elseif (method_exists($this->user, '__get')) {
                $userAutorisation = $this->user->__get('autorisation');
            } elseif (method_exists($this->user, 'getAttribute')) {
                $userAutorisation = $this->user->getAttribute('autorisation');
            }

            error_log("Auth::can - Debug utilisateur: ID=" . $this->id() . ", autorisation='" . $userAutorisation . "'");
        } catch (\Exception $e) {
            error_log("Auth::can - Erreur accès autorisation: " . $e->getMessage());
        }

        // Si on ne peut pas accéder à l'autorisation, refuser
        if ($userAutorisation === null || $userAutorisation === '') {
            error_log("Auth::can - Autorisation vide ou inaccessible pour: $ability");
            return false;
        }

        // Définition des capacités par niveau
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

        $redactorAbilities = [
            'create-sector',
            'update-sector',
            'create-route',
            'update-route'
        ];

        $viewerAbilities = [
            'view-sector',
            'view-route',
            'view-profile',
            'create-comment',
            'view-details'
        ];

        $restrictedAbilities = [
            'view-sample-sector',
            'view-sample-route',
            'view-public-content'
        ];

        // L'admin (autorisation = 0) peut tout faire
        if ($userAutorisation === '0' || $userAutorisation === 0) {
            error_log("Auth: Accès admin accordé pour: " . $ability);
            return true;
        }

        // Le rédacteur (autorisation = 1) peut faire les actions de rédaction et consultation
        if (($userAutorisation === '1' || $userAutorisation === 1) && (
            in_array($ability, $redactorAbilities) ||
            in_array($ability, $viewerAbilities) ||
            in_array($ability, $restrictedAbilities)
        )) {
            error_log("Auth: Accès rédacteur accordé pour: " . $ability);
            return true;
        }

        // Le viewer (autorisation = 2) peut consulter tout le contenu
        if (($userAutorisation === '2' || $userAutorisation === 2) && (
            in_array($ability, $viewerAbilities) ||
            in_array($ability, $restrictedAbilities)
        )) {
            error_log("Auth: Accès viewer accordé pour: " . $ability);
            return true;
        }

        // Accès restreint (autorisation = 3) - compte d'essai
        if (($userAutorisation === '3' || $userAutorisation === 3) && in_array($ability, $restrictedAbilities)) {
            error_log("Auth: Accès restreint accordé pour: " . $ability);
            return true;
        }

        // Vérification pour l'édition de contenu créé par l'utilisateur
        if (($ability === 'update-route' || $ability === 'delete-route') &&
            $model && isset($model->created_by) && $model->created_by === $this->id()
        ) {
            error_log("Auth: Accès accordé pour édition de contenu personnel");
            return true;
        }

        if (($ability === 'update-sector' || $ability === 'delete-sector') &&
            $model && isset($model->created_by) && $model->created_by === $this->id()
        ) {
            error_log("Auth: Accès accordé pour édition de contenu personnel");
            return true;
        }

        error_log("Auth: Accès refusé pour: " . $ability . " (niveau: " . $userAutorisation . ")");
        return false;
    }

    /**
     * Vérifie les données de session pour une connexion
     * MÉTHODE MODIFIÉE
     */
    private function checkSession(): void
    {
        // DEBUG TEMPORAIRE
        if ($userId = ($_SESSION['auth_user_id'] ?? $this->session->get('auth_user_id'))) {
            $query = "SELECT id, username, autorisation FROM users WHERE id = ? LIMIT 1";
            $result = $this->db->query($query, [$userId])->fetch();
            error_log("DEBUG Auth::checkSession - Données utilisateur brutes: " . json_encode($result));
        }

        // Vérifier directement dans $_SESSION pour plus de fiabilité
        $sessionUserId = $_SESSION['auth_user_id'] ?? null;
        $userId = $sessionUserId !== null ? $sessionUserId : $this->session->get('auth_user_id');

        // Debug détaillé
        error_log("Auth::checkSession - Vérification session: _SESSION[auth_user_id]=" .
            (isset($_SESSION['auth_user_id']) ? $_SESSION['auth_user_id'] : 'non défini') .
            ", session.get(auth_user_id)=" . $this->session->get('auth_user_id'));

        if ($userId) {
            error_log("Auth::checkSession - ID utilisateur trouvé en session: $userId");

            // Requête directe pour éviter les problèmes avec la classe Model
            $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
            $result = $this->db->query($query, [$userId])->fetch();

            if ($result) {
                try {
                    $this->user = new User($result);

                    // MODIFICATION: Vérification critique de l'ID après création de l'objet
                    $loadedId = null;
                    try {
                        $loadedId = $this->extractUserId($this->user);
                        error_log("Auth::checkSession - User créé avec ID: $loadedId");
                    } catch (\Exception $e) {
                        error_log("Auth::checkSession - Erreur extraction ID: " . $e->getMessage());
                    }

                    // Vérifier si l'ID correspond à celui attendu
                    if ($loadedId != $userId) {
                        error_log("ALERTE: ID chargé ($loadedId) différent de celui en session ($userId)");
                    }
                } catch (\Exception $e) {
                    error_log("Auth::checkSession - Erreur création User: " . $e->getMessage());
                    // Création manuelle de l'objet utilisateur si nécessaire
                    $this->user = new class($result, (int)$userId) {
                        public int $id;
                        private array $data;

                        public function __construct(array $data, int $userId)
                        {
                            $this->data = $data;
                            $this->id = $userId; // ID explicite

                            // Copier toutes les propriétés dans l'objet
                            foreach ($data as $key => $value) {
                                $this->$key = $value;
                            }

                            error_log("Auth::checkSession - User alternatif créé avec ID explicite: " . $this->id);
                        }

                        public function __get($name)
                        {
                            return $this->data[$name] ?? null;
                        }

                        public function isAdmin(): bool
                        {
                            return $this->autorisation === '0';
                        }

                        public function isModerator(): bool
                        {
                            return in_array($this->autorisation, ['0', '1']);
                        }
                    };
                }
            } else {
                error_log("Auth::checkSession - Utilisateur non trouvé en base de données pour ID: $userId");
            }
            return;
        } else {
            error_log("Auth::checkSession - Aucun ID utilisateur trouvé en session");
        }

        // Vérifie le cookie "Se souvenir de moi" - Reste inchangé
        if (isset($_COOKIE['remember_token'])) {
            // ... code inchangé ...
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
