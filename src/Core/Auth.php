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

            // Connecte l'utilisateur
            $this->login($user, $remember);
            error_log("Connexion réussie pour: $username");
            return true;
        } catch (\Exception $e) {
            // Solution de secours: connexion directe sans classe User
            error_log("Exception lors de la création de l'utilisateur: " . $e->getMessage());
            error_log("Tentative de connexion directe sans classe User");

            try {
                // Créer un objet simple qui implémente les méthodes essentielles
                $user = new class($result) {
                    private array $data;

                    public function __construct(array $data)
                    {
                        $this->data = $data;
                        $this->id = (int) $data['id'];
                        $this->autorisation = $data['autorisation'];
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

                // Connexion manuelle
                $this->user = $user;
                $this->session->regenerate();
                $this->session->set('auth_user_id', $result['id']);

                error_log("Connexion alternative réussie pour: $username avec autorisation: " . $result['autorisation']);
                return true;
            } catch (\Exception $fallbackError) {
                error_log("Échec de la connexion alternative: " . $fallbackError->getMessage());
                return false;
            }
        }
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
        if ($this->user->autorisation == '0') {
            error_log("Auth: Accès admin accordé pour: " . $ability);
            return true;
        }

        // Le rédacteur (autorisation = 1) peut faire les actions de rédaction et consultation
        if ($this->user->autorisation == '1' && (
            in_array($ability, $redactorAbilities) ||
            in_array($ability, $viewerAbilities) ||
            in_array($ability, $restrictedAbilities)
        )) {
            error_log("Auth: Accès rédacteur accordé pour: " . $ability);
            return true;
        }

        // Le viewer (autorisation = 2) peut consulter tout le contenu
        if ($this->user->autorisation == '2' && (
            in_array($ability, $viewerAbilities) ||
            in_array($ability, $restrictedAbilities)
        )) {
            error_log("Auth: Accès viewer accordé pour: " . $ability);
            return true;
        }

        // Accès restreint (autorisation = 3) - compte d'essai
        if ($this->user->autorisation == '3' && in_array($ability, $restrictedAbilities)) {
            error_log("Auth: Accès restreint accordé pour: " . $ability);
            return true;
        }

        // Vérification pour l'édition de contenu créé par l'utilisateur
        if (($ability === 'update-route' || $ability === 'delete-route') &&
            $model && isset($model->created_by) && $model->created_by === $this->user->id
        ) {
            error_log("Auth: Accès accordé pour édition de contenu personnel");
            return true;
        }

        if (($ability === 'update-sector' || $ability === 'delete-sector') &&
            $model && isset($model->created_by) && $model->created_by === $this->user->id
        ) {
            error_log("Auth: Accès accordé pour édition de contenu personnel");
            return true;
        }

        error_log("Auth: Accès refusé pour: " . $ability . " (niveau: " . $this->user->autorisation . ")");
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
            // Requête directe pour éviter les problèmes avec la classe Model
            $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
            $result = $this->db->query($query, [$userId])->fetch();

            if ($result) {
                try {
                    $this->user = new User($result);
                } catch (\Exception $e) {
                    error_log("Erreur création User dans checkSession: " . $e->getMessage());
                    // Création manuelle de l'objet utilisateur si nécessaire
                    $this->user = new class($result) extends User {
                        public function __construct(array $data)
                        {
                            foreach ($data as $key => $value) {
                                $this->$key = $value;
                            }
                            $this->id = (int) $data['id'];
                        }
                    };
                }
            }

            return;
        }

        // Vérifie le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $userId = $this->getUserIdFromRememberToken($token);

            if ($userId) {
                // Requête directe pour éviter les problèmes avec la classe Model
                $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
                $result = $this->db->query($query, [$userId])->fetch();

                if ($result) {
                    try {
                        $user = new User($result);
                        $this->login($user);
                    } catch (\Exception $e) {
                        error_log("Erreur création User dans checkSession remember: " . $e->getMessage());
                        // Ici aussi, création manuelle si nécessaire
                        $user = new class($result) extends User {
                            public function __construct(array $data)
                            {
                                foreach ($data as $key => $value) {
                                    $this->$key = $value;
                                }
                                $this->id = (int) $data['id'];
                            }
                        };
                        $this->login($user);
                    }
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
