<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Core\Response;

class AuthMiddleware
{
    private const PUBLIC_ROUTES = [
        '/',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/about',
        '/contact',
        '/privacy',
        '/terms',
        // Temporaire pour les tests
        '/regions',
        '/regions/create',
        '/sites',
        '/sites/create',
        '/sectors',
        '/sectors/create',
        '/routes',
        '/routes/create',
        '/books',
        '/books/create',
        '/profile',
        '/settings',
        '/admin',
        '/admin/users'
    ];

    private Session $session;
    private Database $db;
    private ?Auth $auth = null;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;
        $this->auth = new Auth($session, $db);
    }

    public function handle(Request $request, callable $next): SymfonyResponse
    {
        $currentPath = $request->getPathInfo();

        // Vérifier si c'est une route publique
        if (in_array($currentPath, self::PUBLIC_ROUTES)) {
            return $next($request);
        }

        // Vérification de l'authentification
        $hasAuthUserId = isset($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'];
        $isAuthenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];

        error_log("AuthMiddleware: Vérification auth - ID=" .
            ($hasAuthUserId ? $_SESSION['auth_user_id'] : 'non défini') .
            ", Authentifié=" . ($isAuthenticated ? 'oui' : 'non'));

        if ($hasAuthUserId && $isAuthenticated) {
            return $next($request);
        }

        // Si non authentifié, sauvegarder l'URL et rediriger
        if ($currentPath !== '/login') {
            $this->session->set('intended_url', $currentPath);
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->persist();

            return new SymfonyResponse('', 302, ['Location' => '/login']);
        }

        return $next($request);
    }
}
