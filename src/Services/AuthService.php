<?php
// src/Services/AuthService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;

class AuthService
{
    private Auth $auth;
    private Session $session;
    private Database $db;

    public function __construct(Auth $auth, Session $session, Database $db)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->db = $db;
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function check(): bool
    {
        return $this->auth->check();
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public function id(): ?int
    {
        return $this->auth->id();
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function user(): ?User
    {
        return $this->auth->user();
    }

    /**
     * Vérifie les permissions
     */
    public function can(string $ability, $model = null): bool
    {
        return $this->auth->can($ability, $model);
    }

    /**
     * Connecte un utilisateur
     */
    public function login(User $user): bool
    {
        return $this->auth->login($user);
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        $this->auth->logout();
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(string $email): bool
    {
        // Utiliser une requête SQL directe pour éviter les problèmes de modèles
        $result = $this->db->query("SELECT * FROM users WHERE mail = ? LIMIT 1", [$email]);
        $userData = $result[0] ?? null;

        if (!$userData) {
            // Ne pas révéler que l'email n'existe pas
            return false;
        }

        // Génère un token de réinitialisation
        $token = bin2hex(random_bytes(10)); // Token de 20 caractères

        // Met à jour le token dans la base de données
        $this->db->query("UPDATE users SET reset_token = ? WHERE id = ?", [$token, $userData['id']]);

        // Pour l'instant, simplement logger le token (à remplacer par un vrai envoi d'email)
        error_log("Token de réinitialisation pour {$email}: {$token}");

        return true;
    }

    /**
     * Valide un token de réinitialisation
     */
    public function validateResetToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $result = $this->db->query("SELECT COUNT(*) as count FROM users WHERE reset_token = ?", [$token]);
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Réinitialise le mot de passe avec un token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $result = $this->db->query("SELECT * FROM users WHERE reset_token = ? LIMIT 1", [$token]);
        $userData = $result[0] ?? null;

        if (!$userData) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $updateResult = $this->db->query(
            "UPDATE users SET password = ?, reset_token = NULL WHERE id = ?",
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
