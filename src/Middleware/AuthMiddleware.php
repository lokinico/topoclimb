<?php

namespace TopoclimbCH\Middleware;

use TopoclimbCH\Core\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AuthMiddleware
{
    private Session $session;
    private Database $db;
    private ?Auth $auth = null;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;

        // On ne tente plus d'obtenir Auth via le conteneur ici
    }

    public function handle(Request $request, callable $next): Response
    {
        // Vérification directe basée sur la session, sans interaction DB risquée
        $hasAuthUserId = isset($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'];
        $isAuthenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

        error_log("AuthMiddleware: Vérification auth - ID=" .
            ($hasAuthUserId ? $_SESSION['auth_user_id'] : 'non défini') .
            ", Authentifié=" . ($isAuthenticated ? 'oui' : 'non'));

        // Si authentification présente en session, on accepte et on continue
        if ($hasAuthUserId && $isAuthenticated) {
            // On continue la chaîne de middleware sans tenter d'initialiser Auth
            return $next($request);
        }

        // Si on arrive ici, l'utilisateur n'est pas authentifié
        $currentPath = $request->getPathInfo();

        // Éviter les boucles de redirection
        if ($currentPath === '/login') {
            return $next($request);
        }

        // Stocker l'URL pour redirection après login
        $this->session->set('intended_url', $currentPath);
        $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');

        // Persister la session
        $this->session->persist();

        // Redirection vers login
        return Response::redirect('/login');
    }
}
