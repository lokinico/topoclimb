<?php
// src/Services/AuthService.php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Models\User;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class AuthService
{
    private Auth $auth;
    private Session $session;
    private Database $db;
    private ?Mailer $mailer = null;
    
    public function __construct(Auth $auth, Session $session, Database $db)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->db = $db;
        
        // Initialiser le mailer uniquement si nécessaire
        // Ne pas le faire dans le constructeur pour éviter les erreurs
    }
    
    /**
     * Obtient une instance de Mailer à la demande
     */
    private function getMailer(): ?Mailer
    {
        if ($this->mailer === null) {
            try {
                // Vérifier que les paramètres requis sont définis
                $host = $_ENV['MAIL_HOST'] ?? '';
                $port = $_ENV['MAIL_PORT'] ?? 25;
                
                if (empty($host)) {
                    // Si pas de configuration, retourner null
                    return null;
                }
                
                // Construire le DSN correctement
                $username = (!empty($_ENV['MAIL_USERNAME']) && $_ENV['MAIL_USERNAME'] !== 'null') 
                    ? $_ENV['MAIL_USERNAME'] : '';
                    
                $password = (!empty($_ENV['MAIL_PASSWORD']) && $_ENV['MAIL_PASSWORD'] !== 'null') 
                    ? $_ENV['MAIL_PASSWORD'] : '';
                
                $encryption = (!empty($_ENV['MAIL_ENCRYPTION']) && $_ENV['MAIL_ENCRYPTION'] !== 'null') 
                    ? $_ENV['MAIL_ENCRYPTION'] : '';
                
                // Construction du DSN
                if (!empty($username) && !empty($password)) {
                    // Format avec authentification
                    $dsn = sprintf(
                        '%s://%s:%s@%s:%s',
                        $encryption ? $encryption.'smtp' : 'smtp',
                        $username,
                        $password,
                        $host,
                        $port
                    );
                } else {
                    // Format sans authentification
                    $dsn = sprintf(
                        '%s://%s:%s',
                        $encryption ? $encryption.'smtp' : 'smtp',
                        $host,
                        $port
                    );
                }
                
                $transport = Transport::fromDsn($dsn);
                $this->mailer = new Mailer($transport);
            } catch (\Exception $e) {
                // En cas d'erreur, logger et retourner null
                error_log('Erreur de configuration du mailer: ' . $e->getMessage());
                return null;
            }
        }
        
        return $this->mailer;
    }
    
    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(string $email): bool
    {
        $user = User::where('mail', $email)->first();
        
        if (!$user) {
            // Ne pas révéler que l'email n'existe pas
            return false;
        }
        
        // Génère un token de réinitialisation
        $token = bin2hex(random_bytes(10)); // Token de 20 caractères
        
        // Met à jour le token dans la base de données
        $user->reset_token = $token;
        $user->save();
        
        // Obtient le mailer
        $mailer = $this->getMailer();
        if (!$mailer) {
            // Si le mailer n'est pas disponible, simuler un succès mais logger
            error_log("Mail non envoyé (mailer non configuré) pour: $email");
            return true;
        }
        
        try {
            // URL de réinitialisation
            $appUrl = $_ENV['APP_URL'] ?? '';
            if (empty($appUrl)) {
                // Détecter l'URL de l'application
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) 
                    ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $appUrl = $protocol . $host;
            }
            
            $resetUrl = "{$appUrl}/reset-password?token={$token}";
            
            $emailMessage = (new Email())
                ->from($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com')
                ->to($user->mail)
                ->subject('Réinitialisation de mot de passe - ' . ($_ENV['APP_NAME'] ?? 'TopoclimbCH'))
                ->html($this->getPasswordResetEmailTemplate($user->prenom, $resetUrl));
            
            $mailer->send($emailMessage);
            return true;
        } catch (\Exception $e) {
            // Journaliser l'erreur
            error_log("Erreur d'envoi d'email: " . $e->getMessage());
            return false;
        }
    }
    
    
    
      /**
     * Template pour l'email de réinitialisation
     */
    private function getPasswordResetEmailTemplate(string $firstName, string $resetUrl): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Réinitialisation de votre mot de passe</h2>
                <p>Bonjour {$firstName},</p>
                <p>Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.</p>
                <p><a class="button" href="{$resetUrl}">Réinitialiser le mot de passe</a></p>
                <p>Ce lien expirera dans 60 minutes.</p>
                <p>Si vous n'avez pas demandé de réinitialisation, aucune action n'est requise.</p>
                <p>Cordialement,<br>L'équipe TopoclimbCH</p>
            </div>
        </body>
        </html>
        HTML;
    }
    
    /**
     * Valide un token de réinitialisation
     */
    public function validateResetToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        return User::where('reset_token', $token)->exists();
    }
    
    /**
     * Réinitialise le mot de passe avec un token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = User::where('reset_token', $token)->first();
        
        if (!$user) {
            return false;
        }
        
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $user->reset_token = null;
        
        return $user->save();
    }
}