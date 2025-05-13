<?php

namespace TopoclimbCH\Middleware;

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Request;
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
        if (!$this->auth->check()) {
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');
            $this->session->set('intended_url', $request->getServer('REQUEST_URI'));
            
            return Response::redirect('/login');
        }
        
        return $next($request);
    }
}
