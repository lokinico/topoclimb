<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Response;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AuthMiddleware
{
    private Auth $auth;
    private Session $session;

    public function __construct(Session $session, Database $db)
    {
        $this->auth = Auth::getInstance($session, $db);
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->auth->check()) {
            // Enregistrer l'URL prévue pour redirection après connexion
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->set('intended_url', $request->getPathInfo());

            return Response::redirect('/login');
        }

        // Vérifier si l'authentification est toujours valide (session expirée, etc.)
        if (!$this->auth->validate()) {
            $this->session->flash('error', 'Votre session a expiré. Veuillez vous reconnecter.');
            $this->auth->logout();
            $this->session->set('intended_url', $request->getPathInfo());

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
