<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class PermissionMiddleware
{
    private Auth $auth;
    private Session $session;

    public function __construct(Session $session, Database $db)
    {
        $this->auth = Auth::getInstance($session, $db);
        $this->session = $session;
    }

    // ✅ CORRECTION: Changement du type de retour pour accepter les réponses Symfony
    public function handle(Request $request, callable $next, array $permissions = []): SymfonyResponse
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->auth->check()) {
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->set('intended_url', $request->getPathInfo());
            return Response::redirect('/login');
        }

        $user = $this->auth->user();
        $userRole = (int)($user->autorisation ?? 4); // Défaut : nouveau membre

        // Vérifier si l'utilisateur est banni
        if ($userRole === 5) {
            $this->session->flash('error', 'Votre compte a été suspendu. Contactez l\'administration.');
            return Response::redirect('/banned');
        }

        // Utilisateur en attente (niveau 4) - accès très limité
        if ($userRole === 4) {
            $allowedPaths = ['/profile', '/settings', '/pending', '/logout'];
            $currentPath = $request->getPathInfo();

            if (!in_array($currentPath, $allowedPaths)) {
                $this->session->flash('warning', 'Votre compte est en attente de validation.');
                return Response::redirect('/pending');
            }
        }

        // Si des permissions spécifiques sont requises
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$this->hasPermission($userRole, $permission)) {
                    // ✅ CORRECTION: Pour les requêtes API, retourner JSON
                    if ($this->isApiRequest($request)) {
                        return Response::json([
                            'success' => false,
                            'error' => 'Insufficient permissions',
                            'message' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource.'
                        ], 403);
                    }

                    $this->session->flash('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
                    return Response::redirect('/');
                }
            }
        }

        // ✅ CORRECTION: Le $next() peut retourner différents types de Response
        return $next($request);
    }

    /**
     * ✅ NOUVELLE MÉTHODE: Détecte si la requête est une requête API
     */
    private function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_starts_with($path, '/api/') ||
            $request->headers->get('Accept') === 'application/json' ||
            $request->headers->get('Content-Type') === 'application/json';
    }

    /**
     * Vérifie si un rôle a une permission spécifique
     */
    private function hasPermission(int $role, string $permission): bool
    {
        // Définition des permissions par rôle
        $rolePermissions = [
            0 => [ // Admin - toutes permissions
                'view-content',
                'view-details',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-content',
                'edit-content',
                'delete-content',
                'manage-users',
                'ban-users',
                'validate-users',
                'admin-panel',
                'api-access' // ✅ AJOUT: Permission API pour admin
            ],
            1 => [ // Modérateur/Éditeur
                'view-content',
                'view-details',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-content',
                'edit-content',
                'delete-content',
                'validate-users',
                'ban-users',
                'api-access' // ✅ AJOUT: Permission API pour modérateur
            ],
            2 => [ // Utilisateur accepté (abonnement complet)
                'view-content',
                'view-details',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-ascent',
                'edit-own-ascent',
                'create-comment',
                'api-access' // ✅ AJOUT: Permission API pour utilisateur
            ],
            3 => [ // Accès restreint (selon achat)
                'view-content-limited',
                'view-profile',
                'view-ascents',
                'view-favorites',
                'create-ascent',
                'edit-own-ascent',
                'create-comment',
                'api-access-limited' // ✅ AJOUT: Permission API limitée
            ],
            4 => [ // Nouveau membre (en attente)
                'view-profile-own'
            ],
            5 => [] // Banni - aucune permission
        ];

        return isset($rolePermissions[$role]) && in_array($permission, $rolePermissions[$role]);
    }

    /**
     * Vérifie l'accès aux pages de contenu selon le rôle
     */
    public function checkContentAccess(Request $request): bool
    {
        if (!$this->auth->check()) {
            return false;
        }

        $user = $this->auth->user();
        $userRole = (int)($user->autorisation ?? 4);
        $path = $request->getPathInfo();

        // Admin et modérateur : accès total
        if (in_array($userRole, [0, 1])) {
            return true;
        }

        // Utilisateur accepté : accès complet
        if ($userRole === 2) {
            return true;
        }

        // Accès restreint : accès limité selon les achats
        if ($userRole === 3) {
            // Ici on pourrait vérifier les achats/abonnements de l'utilisateur
            // Pour l'instant, accès de base accordé
            return true;
        }

        // Nouveau membre et banni : pas d'accès
        return false;
    }
}
