<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class ModeratorMiddleware
{
    private Auth $auth;
    private Session $session;

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
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        // Utiliser la même méthode que AccessControl : 0 = admin, 1 = modérateur
        $userRole = $this->auth->role();
        
        if (
            !$user || $userRole === null ||
            (!in_array($userRole, [0, 1]))
        ) {
            $this->session->flash('error', 'Accès non autorisé. Permission modérateur requise.');
            return Response::redirect('/');
        }

        return $next($request);
    }
}
