<?php

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
    private Mailer $mailer;
    
    public function __construct(Auth $auth, Session $session, Database $db)
    {
        $this->auth = $auth;
        $this->session = $session;
        $this->db = $db;
        
        // Configuration du mailer
        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $_ENV['MAIL_ENCRYPTION'] ? $_ENV['MAIL_ENCRYPTION'] . '+smtp' : 'smtp',
            $_ENV['MAIL_USERNAME'],
            $_ENV['MAIL_PASSWORD'],
            $_ENV['MAIL_HOST'],
            $_ENV['MAIL_PORT']
        );
        
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
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
        
        // URL de réinitialisation
        $appUrl = $_ENV['APP_URL'];
        $resetUrl = "{$appUrl}/reset-password?token={$token}";
        
        try {
            $email = (new Email())
                ->from($_ENV['MAIL_FROM_ADDRESS'])
                ->to($user->mail)
                ->subject('Réinitialisation de mot de passe - ' . $_ENV['APP_NAME'])
                ->html($this->getPasswordResetEmailTemplate($user->prenom, $resetUrl));
            
            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            // Journaliser l'erreur
            error_log("Erreur d'envoi d'email: " . $e->getMessage());
            return false;
        }
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
}