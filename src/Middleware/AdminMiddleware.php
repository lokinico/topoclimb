<?php

namespace TopoclimbCH\Middleware;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Request;
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
        if (!$this->auth->check() || $this->auth->user()->autorisation !== '1') {
            $this->session->flash('error', 'Accès non autorisé. Permission administrateur requise.');
            return Response::redirect('/');
        }
        
        return $next($request);
    }
}
