<?php

namespace TopoclimbCH\Core;

/**
 * Classe Session simplifiée - la gestion CSRF est maintenant dans CsrfManager
 */
class Session
{
    private bool $started = false;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        } else {
            $this->started = session_status() === PHP_SESSION_ACTIVE;
        }
    }

    public function start(): bool
    {
        if (!$this->started) {
            $this->started = session_start();
        }
        return $this->started;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Définit un message flash
     */
    public function flash(string $type, $message): void
    {
        $flashes = $this->get('_flashes', []);

        if (is_array($message)) {
            $flashes[$type] = $message;
        } else {
            if (!isset($flashes[$type])) {
                $flashes[$type] = [];
            }
            $flashes[$type][] = $message;
        }

        $this->set('_flashes', $flashes);
    }

    /**
     * Récupère les messages flash
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
     * Régénère l'ID de session en préservant les données importantes
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        // Sauvegarder les données importantes
        $authUserId = $this->get('auth_user_id');
        $flashes = $this->get('_flashes');

        // Régénérer
        $result = session_regenerate_id($deleteOldSession);

        // Restaurer les données importantes
        if ($authUserId) {
            $this->set('auth_user_id', $authUserId);
        }
        if ($flashes) {
            $this->set('_flashes', $flashes);
        }

        return $result;
    }

    /**
     * Détruit la session complètement
     */
    public function destroy(): bool
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("Session::destroy - La session était déjà inactive");
                $this->started = false;
                return true;
            }

            $sessionId = session_id();
            error_log("Session::destroy - Destruction de la session: " . $sessionId);

            // Effacer toutes les données
            $_SESSION = [];

            // Supprimer le cookie
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
            }

            $result = session_destroy();
            $this->started = false;

            error_log("Session::destroy - " . ($result ? "Succès" : "Échec"));
            return $result;
        } catch (\Throwable $e) {
            error_log("Session::destroy - Exception: " . $e->getMessage());
            $_SESSION = [];
            $this->started = false;
            return false;
        }
    }

    /**
     * Redémarre une nouvelle session
     */
    public function restart(): bool
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->destroy();
            }

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
                error_log("Session::restart - Nouvelle session: " . session_id());
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("Session::restart - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Persiste la session
     */
    public function persist(): bool
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("Session::persist - Session inactive");
                return false;
            }

            $currentSessionId = session_id();
            $allSessionData = $_SESSION;

            error_log("Session::persist - ID=" . $currentSessionId);

            session_write_close();
            $this->started = false;

            session_id($currentSessionId);
            $startResult = session_start();

            if ($startResult) {
                $_SESSION = $allSessionData;
                $this->started = true;
                error_log("Session::persist - Succès");
                return true;
            } else {
                error_log("Session::persist - Échec redémarrage");
                return false;
            }
        } catch (\Throwable $e) {
            error_log("Session::persist - Exception: " . $e->getMessage());
            return false;
        }
    }

    // ========== MÉTHODES CSRF DÉPRÉCIÉES ==========
    // Ces méthodes sont conservées pour la compatibilité mais dépréciées
    // Il est recommandé d'utiliser CsrfManager directement

    /**
     * @deprecated Utiliser CsrfManager::getToken() à la place
     */
    public function setCsrfToken(bool $forceNew = false): string
    {
        error_log("Session::setCsrfToken - DÉPRÉCIÉ: Utiliser CsrfManager::getToken()");

        if (!$forceNew && $this->has('csrf_token')) {
            return $this->get('csrf_token');
        }

        $token = bin2hex(random_bytes(32));
        $this->set('csrf_token', $token);
        return $token;
    }

    /**
     * @deprecated Utiliser CsrfManager::validateToken() à la place
     */
    public function validateCsrfToken(string $token): bool
    {
        error_log("Session::validateCsrfToken - DÉPRÉCIÉ: Utiliser CsrfManager::validateToken()");

        $csrfToken = $this->get('csrf_token');
        if ($csrfToken === null) {
            return false;
        }

        return hash_equals($csrfToken, $token);
    }

    /**
     * @deprecated Utiliser CsrfManager::getToken() à la place
     */
    public function getCsrfToken(): string
    {
        error_log("Session::getCsrfToken - DÉPRÉCIÉ: Utiliser CsrfManager::getToken()");

        $token = $this->get('csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            $this->set('csrf_token', $token);
        }
        return $token;
    }

    /**
     * @deprecated Plus nécessaire avec CsrfManager
     */
    public function synchronizeTokens(): void
    {
        error_log("Session::synchronizeTokens - DÉPRÉCIÉ: Plus nécessaire avec CsrfManager");
        // Méthode vide pour compatibilité
    }
}
