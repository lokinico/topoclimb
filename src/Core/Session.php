<?php

namespace TopoclimbCH\Core;

class Session
{
    /**
     * Indique si la session est démarrée
     *
     * @var bool
     */
    private bool $started = false;

    /**
     * Constructeur
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        } else {
            $this->started = session_status() === PHP_SESSION_ACTIVE;
        }
    }

    /**
     * Démarre la session si elle n'est pas déjà démarrée
     *
     * @return bool
     */
    public function start(): bool
    {
        if (!$this->started) {
            $this->started = session_start();
        }
        return $this->started;
    }

    /**
     * Vérifie si la session est démarrée
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Définit une variable de session
     *
     * @param string $key Clé de la variable
     * @param mixed $value Valeur de la variable
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Récupère une variable de session
     *
     * @param string $key Clé de la variable
     * @param mixed $default Valeur par défaut si la variable n'existe pas
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Vérifie si une variable de session existe
     *
     * @param string $key Clé de la variable
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Supprime une variable de session
     *
     * @param string $key Clé de la variable
     * @return void
     */
    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Récupère toutes les variables de session
     *
     * @return array
     */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Définit un message flash qui sera disponible uniquement pour la prochaine requête
     *
     * @param string $type Type de message (success, error, info, warning)
     * @param string|array $message Contenu du message (peut être une chaîne ou un tableau)
     * @return void
     */
    public function flash(string $type, $message): void
    {
        $flashes = $this->get('_flashes', []);

        // Si $message est un tableau, utilisons-le directement
        if (is_array($message)) {
            $flashes[$type] = $message;
        } else {
            // Sinon, ajoutons la chaîne au tableau de ce type
            if (!isset($flashes[$type])) {
                $flashes[$type] = [];
            }
            $flashes[$type][] = $message;
        }

        $this->set('_flashes', $flashes);
    }

    /**
     * Récupère les messages flash pour un type donné
     *
     * @param string|null $type Type de message (null pour tous les types)
     * @return array
     */
    public function getFlashes(?string $type = null): array
    {
        $flashes = $this->get('_flashes', []);

        if ($type === null) {
            $result = $flashes;
            $this->remove('_flashes');
            return $result;
        }

        $result = $flashes[$type] ?? [];
        unset($flashes[$type]);
        $this->set('_flashes', $flashes);

        return $result;
    }

    /**
     * Régénère l'ID de session
     *
     * @param bool $deleteOldSession Supprimer les données de l'ancienne session
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        // Sauvegarder toutes les données importantes avant régénération
        $csrfToken = $this->get('csrf_token');
        $authUserId = $this->get('auth_user_id');
        $flashes = $this->get('_flashes');

        // Régénérer la session
        $result = session_regenerate_id($deleteOldSession);

        // Restaurer les données importantes
        if ($csrfToken) {
            $this->set('csrf_token', $csrfToken);
            error_log("CSRF token préservé après régénération: " . substr($csrfToken, 0, 10) . "...");
        }

        if ($authUserId) {
            $this->set('auth_user_id', $authUserId);
        }

        if ($flashes) {
            $this->set('_flashes', $flashes);
        }

        return $result;
    }


    /**
     * Amélioration de la méthode destroy() existante
     * pour être plus robuste et mieux gérer les erreurs
     */
    public function destroy(): bool
    {
        try {
            // Session déjà inactive
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("Session::destroy - La session était déjà inactive");
                $this->started = false;
                return true;
            }

            // Sauvegarder l'ID de session pour le logging
            $sessionId = session_id();
            error_log("Session::destroy - Destruction de la session: " . $sessionId);

            // Effacer toutes les données de session
            $_SESSION = [];

            // Supprimer le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $params["path"],
                        'domain' => $params["domain"],
                        'secure' => $params["secure"],
                        'httponly' => $params["httponly"],
                        'samesite' => 'Lax'
                    ]
                );
                error_log("Session::destroy - Cookie de session supprimé");
            }

            // Détruire la session
            $result = session_destroy();
            $this->started = false;

            if ($result) {
                error_log("Session::destroy - Session détruite avec succès");
            } else {
                error_log("Session::destroy - Échec de destruction de la session");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("Session::destroy - Exception: " . $e->getMessage());
            // Essayer quand même de nettoyer
            $_SESSION = [];
            $this->started = false;
            return false;
        }
    }

    /**
     * Démarre une nouvelle session après destruction de l'ancienne
     */
    public function restart(): bool
    {
        try {
            // S'assurer que la session est détruite
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->destroy();
            }

            // Configurer et démarrer une nouvelle session
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            $result = session_start();
            $this->started = $result;

            if ($result) {
                error_log("Session::restart - Nouvelle session démarrée: " . session_id());
            } else {
                error_log("Session::restart - Échec du démarrage d'une nouvelle session");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("Session::restart - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Définit un token CSRF ou renvoie celui existant
     *
     * @param bool $forceNew Forcer la création d'un nouveau token
     * @return string Token CSRF généré ou existant
     */
    public function setCsrfToken(bool $forceNew = false): string
    {
        // Si un token existe déjà et qu'on ne force pas la création, on le renvoie
        if (!$forceNew && $this->has('csrf_token')) {
            $token = $this->get('csrf_token');
            error_log("CSRF Token existant réutilisé: " . substr($token, 0, 10) . '...');
            return $token;
        }

        // Sinon on génère un nouveau token
        $token = bin2hex(random_bytes(32));
        $this->set('csrf_token', $token);
        error_log("CSRF Token généré: " . substr($token, 0, 10) . '...');
        return $token;
    }


    /**
     * Valide le token CSRF
     *
     * @param string $token Token à valider
     * @return bool True si valide, false sinon
     */
    public function validateCsrfToken(string $token): bool
    {
        // Vérifier si nous sommes en train de préserver le token (validation en cours)
        if ($this->has('csrf_validation_in_progress')) {
            error_log("CSRF: Validation en cours détectée - utilisation du token sécurisé");
            return true; // Éviter la double validation si déjà en cours par le middleware
        }

        $csrfToken = $this->get('csrf_token');

        if ($csrfToken === null) {
            error_log("CSRF: Aucun jeton trouvé en session");
            return false;
        }

        // Sauvegarder le token original avant validation
        $this->set('_original_csrf_token', $csrfToken);

        $result = hash_equals($csrfToken, $token);
        error_log("CSRF: Comparaison - " . ($result ? "Réussite" : "Échec"));

        // NE PAS générer de nouveau token immédiatement après validation
        return $result;
    }

    /**
     * Synchronise le token CSRF avec l'original
     * pour éviter les problèmes de validation
     */
    public function synchronizeTokens(): void
    {
        // Vérifier si un token original existe
        if ($this->has('_original_csrf_token')) {
            $originalToken = $this->get('_original_csrf_token');
            $currentToken = $this->get('csrf_token');

            // Si le token a changé pendant le traitement, restaurer l'original
            if ($currentToken !== $originalToken) {
                $this->set('csrf_token', $originalToken);
                error_log("CSRF: Token restauré: " . substr($originalToken, 0, 10) . "...");
            }

            // Nettoyer
            $this->remove('_original_csrf_token');
        }
    }


    /**
     * Amélioration de la méthode persist() existante
     * pour mieux gérer les erreurs et cas particuliers
     */
    public function persist(): bool
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("Session::persist - Tentative de persistance d'une session inactive");
                return false;
            }

            // Sauvegarder l'ID de session actuel et les données
            $currentSessionId = session_id();
            $allSessionData = $_SESSION;

            error_log("Session::persist - Persistance session: ID=" . $currentSessionId);

            // Force l'écriture
            session_write_close();
            $this->started = false;

            // Redémarrer LA MÊME session avec les mêmes données
            session_id($currentSessionId);
            $startResult = session_start();

            if ($startResult) {
                // Restaurer les données
                $_SESSION = $allSessionData;
                $this->started = true;

                error_log("Session::persist - Session persistée avec succès");
                return true;
            } else {
                error_log("Session::persist - Échec du redémarrage de session après écriture");
                return false;
            }
        } catch (\Throwable $e) {
            error_log("Session::persist - Exception: " . $e->getMessage());
            return false;
        }
    }
}
