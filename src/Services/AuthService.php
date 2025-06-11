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

    public function sendPasswordResetEmail(string $email): bool
    {
        $result = $this->db->query("SELECT * FROM users WHERE mail = ? LIMIT 1", [$email]);
        $userData = $result[0] ?? null;

        if (!$userData) {
            return false; // Email non révélé
        }

        $token = bin2hex(random_bytes(10)); // 20 caractères
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 heure

        $this->db->query(
            "UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?",
            [$token, $expiry, $userData['id']]
        );

        $resetUrl = "https://topoclimb.ch/reset-password?token=$token";

        $subject = "Réinitialisation de votre mot de passe";
        $body = "Bonjour,\n\nVous avez demandé à réinitialiser votre mot de passe. Voici le lien pour le faire :\n\n$resetUrl\n\nCe lien est valable pendant 1 heure.\n\nSi vous n'avez pas demandé cela, vous pouvez ignorer ce message.\n\nL'équipe Topoclimb";

        $this->mailer->send($email, $subject, $body);

        return true;
    }

    public function validateResetToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM users WHERE reset_token = ? AND reset_token_expires_at > ?",
            [$token, $now]
        );

        return ($result[0]['count'] ?? 0) > 0;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $now = date('Y-m-d H:i:s');
        $result = $this->db->query(
            "SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > ? LIMIT 1",
            [$token, $now]
        );

        $userData = $result[0] ?? null;

        if (!$userData) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateResult = $this->db->query(
            "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?",
            [$hashedPassword, $userData['id']]
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
}
