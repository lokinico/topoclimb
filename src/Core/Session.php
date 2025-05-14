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
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Détruit la session
     *
     * @return bool
     */
    public function destroy(): bool
    {
        if ($this->started) {
            $this->started = false;
            $_SESSION = [];
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            return session_destroy();
        }
        
        return true;
    }

    /**
     * Définit un token CSRF
     *
     * @return string Token CSRF généré
     */
    public function setCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        error_log("CSRF Token généré: " . substr($token, 0, 10) . '...');
        return $token;
    }

    /**
     * Vérifie si un token CSRF est valide
     *
     * @param string $token Token CSRF à vérifier
     * @return bool
     */
    public function validateCsrfToken(string $token): bool
    {
        $csrfToken = $this->get('_csrf_token');
        
        if ($csrfToken === null) {
            error_log("CSRF: Aucun jeton trouvé en session");
            return false;
        }
        
        $result = hash_equals($csrfToken, $token);
        error_log("CSRF: Comparaison - " . ($result ? "Réussite" : "Échec"));
        
        return $result;
    }
}