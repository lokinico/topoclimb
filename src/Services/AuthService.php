<?php
// src/Services/AuthService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Services\Mailer;
use TopoclimbCH\Models\User;

class AuthService
{
    private Auth $auth;
    private Session $session;
    private Database $db;
    private Mailer $mailer;

    public function __construct(Auth $auth, Session $session, Database $db, Mailer $mailer)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->db = $db;
        $this->mailer = $mailer;
    }

    /**
     * Récupère l'instance Auth pour compatibilité avec BaseController
     */
    public function getAuth(): Auth
    {
        return $this->auth;
    }

    public function check(): bool
    {
        return $this->auth->check();
    }

    public function id(): ?int
    {
        return $this->auth->id();
    }

    public function user(): ?User
    {
        return $this->auth->user();
    }

    public function can(string $ability, $model = null): bool
    {
        return $this->auth->can($ability, $model);
    }

    public function login(User $user): bool
    {
        return $this->auth->login($user);
    }

    public function logout(): void
    {
        $this->auth->logout();
    }

    /**
     * Tentative de connexion avec email et mot de passe
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        try {
            // Récupérer l'utilisateur par email
            $result = $this->db->fetchOne("SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1", [$email]);

            if (!$result) {
                return false;
            }

            // Vérifier le mot de passe
            if (!password_verify($password, $result['password_hash'])) {
                return false;
            }

            // Créer l'objet User avec l'ID
            $user = User::fromDatabase($result);

            // Connecter l'utilisateur - Auth::login() gère maintenant correctement l'ID
            $this->auth->login($user, $remember);

            // Vérifier que la connexion a réussi
            if ($this->auth->check()) {
                error_log("Connexion réussie pour: $email");
                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log("Erreur lors de la tentative de connexion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inscription d'un nouvel utilisateur
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User
    {
        try {
            // Vérifier si l'email existe déjà
            $emailExists = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE mail = ?", [$data['email']]);
            if ($emailExists && $emailExists['count'] > 0) {
                throw new \Exception('Cet email est déjà utilisé');
            }

            // Vérifier si le username existe déjà
            $usernameExists = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE username = ?", [$data['username']]);
            if ($usernameExists && $usernameExists['count'] > 0) {
                throw new \Exception('Ce nom d\'utilisateur est déjà utilisé');
            }

            // Créer l'utilisateur
            $userId = $this->db->insert('users', [
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'mail' => $data['email'],
                'username' => $data['username'],
                'ville' => $data['ville'] ?? '',
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                'autorisation' => '3', // Utilisateur standard
                'date_registered' => date('Y-m-d H:i:s')
            ]);

            // Récupérer l'utilisateur créé
            $userData = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

            $user = new User();
            $user->fill($userData);

            return $user;
        } catch (\Exception $e) {
            error_log("Erreur lors de l'inscription: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Définir un token "Se souvenir de moi"
     *
     * @param User $user
     */
    private function setRememberToken(User $user): void
    {
        try {
            // Générer un token sécurisé
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 jours

            // Sauvegarder en base
            $this->db->insert('remember_tokens', [
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'expires_at' => $expires
            ]);

            // Définir le cookie
            setcookie(
                'remember_token',
                $token,
                time() + (30 * 24 * 60 * 60), // 30 jours
                '/',
                '',
                true, // Secure
                true  // HttpOnly
            );
        } catch (\Exception $e) {
            error_log("Erreur lors de la création du token de souvenir: " . $e->getMessage());
        }
    }

    /**
     * Vérifier et utiliser un token "Se souvenir de moi"
     *
     * @param string $token
     * @return bool
     */
    public function loginFromRememberToken(string $token): bool
    {
        try {
            $hashedToken = hash('sha256', $token);

            $result = $this->db->fetchOne("
                SELECT u.*, rt.id as token_id 
                FROM users u 
                JOIN remember_tokens rt ON u.id = rt.user_id 
                WHERE rt.token = ? AND rt.expires_at > NOW()
                LIMIT 1
            ", [$hashedToken]);

            if (!$result) {
                return false;
            }

            // Créer l'objet User
            $user = new User();
            $user->fill($result);

            // Connecter l'utilisateur
            $loginSuccess = $this->auth->login($user);

            if ($loginSuccess) {
                // Renouveler le token pour sécurité
                $this->refreshRememberToken($result['token_id'], $user);
            }

            return $loginSuccess;
        } catch (\Exception $e) {
            error_log("Erreur lors de la connexion via token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renouveler un token "Se souvenir de moi"
     */
    private function refreshRememberToken(int $oldTokenId, User $user): void
    {
        try {
            // Supprimer l'ancien token
            $this->db->delete('remember_tokens', ['id' => $oldTokenId]);

            // Créer un nouveau token
            $this->setRememberToken($user);
        } catch (\Exception $e) {
            error_log("Erreur lors du renouvellement du token: " . $e->getMessage());
        }
    }

    public function sendPasswordResetEmail(string $email): bool
    {
        $result = $this->db->fetchOne("SELECT * FROM users WHERE mail = ? AND actif = 1 LIMIT 1", [$email]);

        if (!$result) {
            return false; // Email non révélé
        }

        $token = bin2hex(random_bytes(32)); // 64 caractères
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 heure

        $this->db->query(
            "UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?",
            [$token, $expiry, $result['id']]
        );

        $resetUrl = "https://topoclimb.ch/reset-password?token=$token";

        $subject = "Réinitialisation de votre mot de passe - TopoclimbCH";
        $body = "Bonjour {$result['prenom']},\n\n";
        $body .= "Vous avez demandé à réinitialiser votre mot de passe sur TopoclimbCH.\n\n";
        $body .= "Cliquez sur le lien suivant pour définir un nouveau mot de passe :\n";
        $body .= "$resetUrl\n\n";
        $body .= "Ce lien est valable pendant 1 heure.\n\n";
        $body .= "Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.\n\n";
        $body .= "Cordialement,\nL'équipe TopoclimbCH";

        return $this->mailer->send($email, $subject, $body);
    }

    public function validateResetToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE reset_token = ? AND reset_token_expires_at > ?",
            [$token, $now]
        );

        return ($result['count'] ?? 0) > 0;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $now = date('Y-m-d H:i:s');
        $result = $this->db->fetchOne(
            "SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > ? LIMIT 1",
            [$token, $now]
        );

        if (!$result) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateResult = $this->db->query(
            "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?",
            [$hashedPassword, $result['id']]
        );

        return $updateResult !== false;
    }

    public function requireAuth(): void
    {
        if (!$this->check()) {
            $currentUrl = $_SERVER['REQUEST_URI'];
            $this->session->set('intended_url', $currentUrl);
            $this->session->persist();
            Response::redirect('/login');
            exit;
        }
    }

    /**
     * Nettoie les tokens expirés
     */
    public function cleanExpiredTokens(): int
    {
        try {
            $result = $this->db->query("DELETE FROM remember_tokens WHERE expires_at <= NOW()");
            return $result ? $this->db->getAffectedRows() : 0;
        } catch (\Exception $e) {
            error_log("Erreur lors du nettoyage des tokens: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Déconnecte l'utilisateur de tous les appareils
     */
    public function logoutAllDevices(int $userId): void
    {
        try {
            // Supprimer tous les tokens de souvenir
            $this->db->delete('remember_tokens', ['user_id' => $userId]);

            // Si c'est l'utilisateur actuel, le déconnecter aussi
            if ($this->id() === $userId) {
                $this->logout();
            }
        } catch (\Exception $e) {
            error_log("Erreur lors de la déconnexion de tous les appareils: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si l'utilisateur est banni
     */
    public function isBanned(): bool
    {
        $user = $this->user();
        return $user && $user->autorisation === '0';
    }

    /**
     * Change le mot de passe de l'utilisateur connecté
     */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        try {
            $user = $this->user();
            if (!$user) {
                return false;
            }

            // Vérifier le mot de passe actuel
            $userData = $this->db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$user->id]);
            if (!password_verify($currentPassword, $userData['password_hash'])) {
                return false;
            }

            // Mettre à jour le mot de passe
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->db->update('users', ['password_hash' => $hashedPassword], ['id' => $user->id]);

            // Supprimer tous les tokens de souvenir pour forcer une nouvelle connexion
            $this->db->delete('remember_tokens', ['user_id' => $user->id]);

            return true;
        } catch (\Exception $e) {
            error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
            return false;
        }
    }
}
