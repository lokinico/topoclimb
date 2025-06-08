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

    public function __construct(Session $session, Database $db)
    {
        $this->auth = Auth::getInstance($session, $db);
        $this->session = $session;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Vérifier si l'utilisateur est connecté et a les autorisations admin
        if (!$this->auth->check()) {
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->set('intended_url', $request->getPathInfo());
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        // Vérifier si l'utilisateur a les droits d'administrateur
        // '0' = Super Admin, '1' = Modérateur/Éditeur
        if (!$user || !isset($user->autorisation) || !in_array($user->autorisation, ['0', '1'])) {
            $this->session->flash('error', 'Accès non autorisé. Permission administrateur requise.');
            return Response::redirect('/');
        }

        return $next($request);
    }
}
