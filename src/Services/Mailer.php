<?php

namespace TopoclimbCH\Services;

use TopoclimbCH\Core\Database;

class Mailer
{
    private Database $db;
    private array $config;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->config = [
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@topoclimb.ch',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'TopoclimbCH',
            'mailer' => $_ENV['MAIL_MAILER'] ?? 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => $_ENV['MAIL_PORT'] ?? 587,
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls'
        ];
    }

    /**
     * Envoie un email
     *
     * @param string $to Adresse email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email (texte brut)
     * @param string|null $htmlBody Corps de l'email (HTML optionnel)
     * @return bool Succès de l'envoi
     */
    public function send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        try {
            // Log de l'envoi
            $this->logEmail($to, $subject, 'attempting');

            // Validation des paramètres
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Adresse email invalide: $to");
            }

            if (empty($subject) || empty($body)) {
                throw new \InvalidArgumentException("Le sujet et le corps de l'email sont requis");
            }

            // Selon la configuration, utiliser la méthode appropriée
            switch ($this->config['mailer']) {
                case 'smtp':
                    return $this->sendViaSmtp($to, $subject, $body, $htmlBody);

                case 'mail':
                    return $this->sendViaMail($to, $subject, $body, $htmlBody);

                case 'log':
                    return $this->sendToLog($to, $subject, $body, $htmlBody);

                default:
                    throw new \InvalidArgumentException("Mailer non supporté: " . $this->config['mailer']);
            }
        } catch (\Exception $e) {
            $this->logEmail($to, $subject, 'failed', $e->getMessage());
            error_log("Échec d'envoi d'email à $to: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoi via SMTP (recommandé pour la production)
     */
    private function sendViaSmtp(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        // Si les extensions PHP pour SMTP ne sont pas disponibles, fallback vers mail()
        if (!function_exists('mail')) {
            throw new \RuntimeException("La fonction mail() n'est pas disponible");
        }

        // Headers pour SMTP
        $headers = $this->buildHeaders($htmlBody !== null);

        // Corps de l'email
        $emailBody = $htmlBody ?? $body;

        $success = mail($to, $subject, $emailBody, $headers);

        if ($success) {
            $this->logEmail($to, $subject, 'sent');
        } else {
            $this->logEmail($to, $subject, 'failed', 'mail() returned false');
        }

        return $success;
    }

    /**
     * Envoi via la fonction mail() de PHP (simple)
     */
    private function sendViaMail(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $headers = $this->buildHeaders($htmlBody !== null);
        $emailBody = $htmlBody ?? $body;

        $success = mail($to, $subject, $emailBody, $headers);

        if ($success) {
            $this->logEmail($to, $subject, 'sent');
        } else {
            $this->logEmail($to, $subject, 'failed', 'mail() returned false');
        }

        return $success;
    }

    /**
     * Envoi vers les logs (pour développement/test)
     */
    private function sendToLog(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $logMessage = "===== EMAIL LOG =====\n";
        $logMessage .= "À: $to\n";
        $logMessage .= "Sujet: $subject\n";
        $logMessage .= "Corps texte:\n$body\n";

        if ($htmlBody) {
            $logMessage .= "Corps HTML:\n$htmlBody\n";
        }

        $logMessage .= "=====================\n";

        error_log($logMessage);
        $this->logEmail($to, $subject, 'logged');

        return true;
    }

    /**
     * Construction des headers d'email
     */
    private function buildHeaders(bool $isHtml = false): string
    {
        $headers = [];

        // From
        $headers[] = "From: {$this->config['from_name']} <{$this->config['from_email']}>";

        // Reply-To
        $headers[] = "Reply-To: {$this->config['from_email']}";

        // Content-Type
        if ($isHtml) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        // Autres headers de sécurité
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "X-Mailer: TopoclimbCH";
        $headers[] = "X-Priority: 3";

        return implode("\r\n", $headers);
    }

    /**
     * Log des envois d'emails dans la base de données
     */
    private function logEmail(string $to, string $subject, string $status, ?string $error = null): void
    {
        try {
            // Vérifier si la table existe
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'email_logs'");

            if (!$tableExists) {
                // Créer la table si elle n'existe pas
                $this->createEmailLogsTable();
            }

            $this->db->insert('email_logs', [
                'to_email' => $to,
                'subject' => $subject,
                'status' => $status,
                'error_message' => $error,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Ne pas faire échouer l'envoi si le logging échoue
            error_log("Impossible de logger l'email: " . $e->getMessage());
        }
    }

    /**
     * Crée la table de logs d'emails si elle n'existe pas
     */
    private function createEmailLogsTable(): void
    {
        $sql = "
            CREATE TABLE email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                status ENUM('attempting', 'sent', 'failed', 'logged') NOT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_to_email (to_email),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->db->query($sql);
    }

    /**
     * Teste la configuration email
     */
    public function testConfiguration(): array
    {
        return [
            'mailer' => $this->config['mailer'],
            'from_email' => $this->config['from_email'],
            'from_name' => $this->config['from_name'],
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'mail_function_available' => function_exists('mail'),
            'openssl_available' => extension_loaded('openssl')
        ];
    }

    /**
     * Envoi d'email de test
     */
    public function sendTestEmail(string $to): bool
    {
        $subject = "Test d'email TopoclimbCH";
        $body = "Ceci est un email de test envoyé depuis TopoclimbCH.\n\n";
        $body .= "Si vous recevez cet email, la configuration fonctionne correctement.\n\n";
        $body .= "Date d'envoi: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Serveur: " . ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return $this->send($to, $subject, $body);
    }

    /**
     * Récupère les logs d'emails récents
     */
    public function getRecentLogs(int $limit = 50): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Statistiques d'envoi
     */
    public function getStats(int $days = 7): array
    {
        try {
            $since = date('Y-m-d H:i:s', strtotime("-$days days"));

            $stats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'logged' THEN 1 ELSE 0 END) as logged
                FROM email_logs 
                WHERE created_at >= ?
            ", [$since]);

            return $stats ?: ['total' => 0, 'sent' => 0, 'failed' => 0, 'logged' => 0];
        } catch (\Exception $e) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'logged' => 0];
        }
    }
}
