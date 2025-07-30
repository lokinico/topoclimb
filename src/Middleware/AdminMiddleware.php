<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AdminMiddleware
{
    private Auth $auth;
    private Session $session;

    // Définition des permissions granulaires par niveau et chemin
    private array $permissions = [
        // Super Admin (niveau 0) - Accès total
        '0' => [
            '/admin' => true,
            '/admin/dashboard' => true,
            '/admin/settings' => true,
            '/admin/backup' => true,
            '/admin/monitoring' => true,
            '/admin/cache/clear' => true,
            '/admin/analytics' => true,
            '/admin/system' => true,
            '/admin/database' => true,
            '/admin/security' => true,
            '/admin/users/create' => true,
            '/admin/permissions' => true,
            '/admin/users' => true,
            '/admin/users/*/edit' => true,
            '/admin/users/*/ban' => true,
            '/admin/comments' => true,
            '/admin/comments/*/approve' => true,
            '/admin/comments/*/delete' => true,
            '/admin/reports' => true,
            '/admin/logs' => true,
        ],
        
        // Admin (niveau 1) - Accès limité
        '1' => [
            '/admin' => true,
            '/admin/dashboard' => true,
            '/admin/settings' => true,
            '/admin/backup' => true,
            '/admin/monitoring' => true,
            '/admin/cache/clear' => true,
            '/admin/analytics' => true,
            // Pas d'accès aux fonctions système (niveau 0 uniquement)
            '/admin/system' => false,
            '/admin/database' => false,
            '/admin/security' => false,
            '/admin/users/create' => false,
            '/admin/permissions' => false,
            // Accès modération
            '/admin/users' => true,
            '/admin/users/*/edit' => true,
            '/admin/users/*/ban' => true,
            '/admin/comments' => true,
            '/admin/comments/*/approve' => true,
            '/admin/comments/*/delete' => true,
            '/admin/reports' => true,
            '/admin/logs' => true,
        ],
        
        // Modérateur (niveau 2) - Accès modération uniquement
        '2' => [
            // Pas d'accès au panel admin principal
            '/admin' => false,
            '/admin/dashboard' => false,
            '/admin/settings' => false,
            '/admin/backup' => false,
            '/admin/monitoring' => false,
            '/admin/cache/clear' => false,
            '/admin/analytics' => false,
            '/admin/system' => false,
            '/admin/database' => false,
            '/admin/security' => false,
            '/admin/users/create' => false,
            '/admin/permissions' => false,
            // Accès modération seulement
            '/admin/users' => true,
            '/admin/users/*/edit' => true,
            '/admin/users/*/ban' => true,
            '/admin/comments' => true,
            '/admin/comments/*/approve' => true,
            '/admin/comments/*/delete' => true,
            '/admin/reports' => true,
            '/admin/logs' => true,
        ]
    ];

    public function __construct(Session $session, Database $db)
    {
        $this->auth = new Auth($session, $db);
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->auth->check()) {
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->set('intended_url', $request->getPathInfo());
            error_log("AdminMiddleware: Utilisateur non connecté tentant d'accéder à " . $request->getPathInfo());
            return Response::redirect('/login');
        }

        $user = $this->auth->user();
        $userLevel = $user->autorisation ?? null;
        $requestPath = $request->getPathInfo();

        // Log de sécurité pour toute tentative d'accès admin
        error_log("AdminMiddleware: Utilisateur niveau $userLevel (ID: {$this->auth->id()}) tente d'accéder à $requestPath");

        // Vérifier si l'utilisateur est banni
        if ($userLevel === '5') {
            $this->session->flash('error', 'Votre compte a été suspendu.');
            error_log("AdminMiddleware: Utilisateur banni (niveau 5) bloqué - ID: {$this->auth->id()}");
            return Response::redirect('/');
        }

        // Vérifier les permissions granulaires
        if (!$this->hasPermission($userLevel, $requestPath)) {
            $this->session->flash('error', 'Accès non autorisé. Permissions insuffisantes.');
            error_log("AdminMiddleware: Accès refusé - Utilisateur niveau $userLevel vers $requestPath");
            
            // Redirection sécurisée selon le niveau
            $redirectUrl = match($userLevel) {
                '0', '1' => '/admin', // Admin vers dashboard
                '2' => '/admin/users',  // Modérateur vers modération
                default => '/'  // Autres vers accueil
            };
            
            return Response::redirect($redirectUrl);
        }

        // Log succès pour audit
        error_log("AdminMiddleware: Accès autorisé - Utilisateur niveau $userLevel vers $requestPath");
        
        return $next($request);
    }

    /**
     * Vérifie si l'utilisateur a la permission d'accéder au chemin demandé
     */
    private function hasPermission(?string $userLevel, string $path): bool
    {
        // Utilisateurs non autorisés (niveau 3, 4, null)
        if (!in_array($userLevel, ['0', '1', '2'])) {
            return false;
        }

        // Vérifier permissions exactes d'abord
        if (isset($this->permissions[$userLevel][$path])) {
            return $this->permissions[$userLevel][$path];
        }

        // Vérifier patterns avec wildcards (ex: /admin/users/*/edit)
        foreach ($this->permissions[$userLevel] as $pattern => $allowed) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace('*', '[0-9]+', $pattern);
                $regex = '#^' . $regex . '$#';
                if (preg_match($regex, $path)) {
                    return $allowed;
                }
            }
        }

        // Par défaut, refuser l'accès pour sécurité
        return false;
    }

    /**
     * Vérifie si l'utilisateur a au moins le niveau requis
     */
    public function requireMinLevel(string $minLevel): bool
    {
        $user = $this->auth->user();
        $userLevel = $user->autorisation ?? '5';
        
        // Utilisateur banni = accès refusé
        if ($userLevel === '5') {
            return false;
        }
        
        // Plus le chiffre est bas, plus le niveau est élevé
        return (int)$userLevel <= (int)$minLevel;
    }
}
