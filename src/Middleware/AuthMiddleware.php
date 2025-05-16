<?php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

class AuthMiddleware
{
    private Session $session;
    private Database $db;
    private Auth $auth;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;
        $this->auth = Auth::getInstance($session, $db);
    }

    public function handle(Request $request, callable $next): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->auth->check()) {
            // Sauvegarder l'URL pour redirection après login
            $this->session->set('intended_url', $request->getPathInfo());
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');

            // Important: persister la session avant redirection
            $this->session->persist();

            return Response::redirect('/login');
        }

        // Vérifier si l'authentification est toujours valide
        if (!$this->auth->validate()) {
            $this->session->flash('error', 'Votre session a expiré. Veuillez vous reconnecter.');
            $this->session->set('intended_url', $request->getPathInfo());

            // Important: persister la session avant redirection
            $this->session->persist();

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
