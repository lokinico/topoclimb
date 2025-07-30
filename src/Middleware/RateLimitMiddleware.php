<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;

class RateLimitMiddleware
{
    private Session $session;
    private string $storageDir;
    
    // Configuration par défaut
    private array $config = [
        'login' => [
            'max_attempts' => 5,      // 5 tentatives max
            'window_minutes' => 15,   // Sur 15 minutes
            'block_minutes' => 30     // Blocage 30 minutes
        ],
        'api' => [
            'max_attempts' => 100,    // 100 requêtes max
            'window_minutes' => 60,   // Sur 60 minutes  
            'block_minutes' => 60     // Blocage 60 minutes
        ],
        'register' => [
            'max_attempts' => 3,      // 3 inscriptions max
            'window_minutes' => 60,   // Sur 60 minutes
            'block_minutes' => 120    // Blocage 2 heures
        ]
    ];

    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->storageDir = __DIR__ . '/../../storage/rate_limits';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function handle(Request $request, callable $next, string $type = 'default'): Response
    {
        $identifier = $this->getIdentifier($request);
        $config = $this->config[$type] ?? $this->config['api'];
        
        // Vérifier si l'utilisateur est bloqué
        if ($this->isBlocked($identifier, $type)) {
            $blockedUntil = $this->getBlockedUntil($identifier, $type);
            $remainingMinutes = ceil(($blockedUntil - time()) / 60);
            
            error_log("RateLimitMiddleware: Requête bloquée pour $identifier ($type) - $remainingMinutes minutes restantes");
            
            return Response::json([
                'error' => 'Trop de tentatives. Réessayez dans ' . $remainingMinutes . ' minutes.',
                'blocked_until' => date('Y-m-d H:i:s', $blockedUntil),
                'remaining_minutes' => $remainingMinutes
            ], 429); // HTTP 429 Too Many Requests
        }
        
        // Enregistrer la tentative
        $this->recordAttempt($identifier, $type);
        
        // Vérifier si la limite est atteinte
        $attempts = $this->getAttempts($identifier, $type, $config['window_minutes']);
        
        if ($attempts >= $config['max_attempts']) {
            // Bloquer l'utilisateur
            $this->blockUser($identifier, $type, $config['block_minutes']);
            
            error_log("RateLimitMiddleware: Limite atteinte pour $identifier ($type) - $attempts/{$config['max_attempts']} tentatives");
            
            return Response::json([
                'error' => 'Limite de tentatives atteinte. Vous êtes temporairement bloqué.',
                'max_attempts' => $config['max_attempts'],
                'window_minutes' => $config['window_minutes'],
                'block_minutes' => $config['block_minutes']
            ], 429);
        }
        
        // Continuer avec la requête
        $response = $next($request);
        
        // Ajouter headers informatifs
        $response->headers->set('X-RateLimit-Limit', $config['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', max(0, $config['max_attempts'] - $attempts));
        $response->headers->set('X-RateLimit-Reset', time() + ($config['window_minutes'] * 60));
        
        return $response;
    }

    /**
     * Générer un identifiant unique pour la requête (IP + User-Agent)
     */
    private function getIdentifier(Request $request): string
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $userAgent = $request->headers->get('User-Agent', 'unknown');
        return hash('sha256', $ip . '|' . $userAgent);
    }

    /**
     * Enregistrer une tentative
     */
    private function recordAttempt(string $identifier, string $type): void
    {
        $file = $this->getAttemptFile($identifier, $type);
        $timestamp = time();
        
        // Ajouter le timestamp à la fin du fichier
        file_put_contents($file, $timestamp . "\n", FILE_APPEND | LOCK_EX);
        
        // Nettoyer les anciennes entrées (plus de 24h)
        $this->cleanOldAttempts($file);
    }

    /**
     * Récupérer le nombre de tentatives dans la fenêtre de temps
     */
    private function getAttempts(string $identifier, string $type, int $windowMinutes): int
    {
        $file = $this->getAttemptFile($identifier, $type);
        
        if (!file_exists($file)) {
            return 0;
        }
        
        $cutoff = time() - ($windowMinutes * 60);
        $attempts = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Compter seulement les tentatives dans la fenêtre
        $validAttempts = array_filter($attempts, function($timestamp) use ($cutoff) {
            return (int)$timestamp > $cutoff;
        });
        
        return count($validAttempts);
    }

    /**
     * Vérifier si l'utilisateur est bloqué
     */
    private function isBlocked(string $identifier, string $type): bool
    {
        $file = $this->getBlockFile($identifier, $type);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $blockedUntil = (int)file_get_contents($file);
        
        if ($blockedUntil <= time()) {
            // Le blocage a expiré, supprimer le fichier
            unlink($file);
            return false;
        }
        
        return true;
    }

    /**
     * Récupérer le timestamp jusqu'auquel l'utilisateur est bloqué
     */
    private function getBlockedUntil(string $identifier, string $type): int
    {
        $file = $this->getBlockFile($identifier, $type);
        
        if (!file_exists($file)) {
            return 0;
        }
        
        return (int)file_get_contents($file);
    }

    /**
     * Bloquer un utilisateur
     */
    private function blockUser(string $identifier, string $type, int $blockMinutes): void
    {
        $file = $this->getBlockFile($identifier, $type);
        $blockedUntil = time() + ($blockMinutes * 60);
        
        file_put_contents($file, $blockedUntil, LOCK_EX);
        
        // Log de sécurité
        error_log("RateLimitMiddleware: Utilisateur bloqué - ID: $identifier, Type: $type, Jusqu'à: " . date('Y-m-d H:i:s', $blockedUntil));
    }

    /**
     * Nettoyer les anciennes tentatives (plus de 24h)
     */
    private function cleanOldAttempts(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }
        
        $cutoff = time() - (24 * 60 * 60); // 24 heures
        $attempts = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        $validAttempts = array_filter($attempts, function($timestamp) use ($cutoff) {
            return (int)$timestamp > $cutoff;
        });
        
        // Réécrire le fichier avec seulement les tentatives valides
        if (count($validAttempts) !== count($attempts)) {
            file_put_contents($file, implode("\n", $validAttempts) . "\n", LOCK_EX);
        }
    }

    /**
     * Chemin du fichier des tentatives
     */
    private function getAttemptFile(string $identifier, string $type): string
    {
        return $this->storageDir . "/attempts_{$type}_{$identifier}.txt";
    }

    /**
     * Chemin du fichier de blocage
     */
    private function getBlockFile(string $identifier, string $type): string
    {
        return $this->storageDir . "/blocks_{$type}_{$identifier}.txt";
    }

    /**
     * Réinitialiser les tentatives pour un utilisateur (pour les tests ou déblocage admin)
     */
    public function resetAttempts(string $identifier, string $type): void
    {
        $attemptFile = $this->getAttemptFile($identifier, $type);
        $blockFile = $this->getBlockFile($identifier, $type);
        
        if (file_exists($attemptFile)) {
            unlink($attemptFile);
        }
        
        if (file_exists($blockFile)) {
            unlink($blockFile);
        }
        
        error_log("RateLimitMiddleware: Tentatives réinitialisées pour $identifier ($type)");
    }

    /**
     * Statistiques du rate limiting
     */
    public function getStats(): array
    {
        $stats = [
            'total_blocked_users' => 0,
            'total_attempts_today' => 0,
            'blocks_by_type' => []
        ];
        
        if (!is_dir($this->storageDir)) {
            return $stats;
        }
        
        $files = glob($this->storageDir . '/*');
        $today = strtotime('today');
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            if (str_starts_with($filename, 'blocks_')) {
                $stats['total_blocked_users']++;
                
                // Extraire le type
                preg_match('/blocks_([^_]+)_/', $filename, $matches);
                $type = $matches[1] ?? 'unknown';
                $stats['blocks_by_type'][$type] = ($stats['blocks_by_type'][$type] ?? 0) + 1;
            }
            
            if (str_starts_with($filename, 'attempts_')) {
                $attempts = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($attempts as $timestamp) {
                    if ((int)$timestamp >= $today) {
                        $stats['total_attempts_today']++;
                    }
                }
            }
        }
        
        return $stats;
    }
}