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
    private ?Auth $auth = null;

    public function __construct(Session $session, Database $db)
    {
        $this->session = $session;
        $this->db = $db;

        // Initialiser Auth de manière sécurisée
        try {
            $this->auth = Auth::getInstance($session, $db);
        } catch (\Exception $e) {
            error_log("Erreur lors de l'initialisation d'Auth dans le middleware: " . $e->getMessage());
            // Ne pas lancer d'exception - le middleware gérera lui-même l'absence d'authentification
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        // Fallback de sécurité si auth n'est pas initialisé mais qu'un ID est en session
        if ($this->auth === null && $this->session->has('auth_user_id')) {
            error_log("AuthMiddleware: Tentative de récupération d'auth via session");
            try {
                $this->auth = Auth::getInstance($this->session, $this->db);
            } catch (\Exception $e) {
                error_log("Échec de récupération d'auth: " . $e->getMessage());
            }
        }

        // Vérifier l'authentification
        $isAuthenticated = $this->auth !== null && $this->auth->check();

        if (!$isAuthenticated) {
            error_log("AuthMiddleware: Utilisateur non authentifié, redirection vers login");
            // Stocker l'URL actuelle pour y revenir après login
            $this->session->set('intended_url', $request->getPathInfo());
            $this->session->flash('error', 'Vous devez être connecté pour accéder à cette page');

            // IMPORTANT: persister la session
            $this->session->persist();

            // Redirection immédiate
            $response = new Response('', 302);
            $response->headers->set('Location', '/login');
            return $response;
        }

        // Utilisateur authentifié, continuer
        return $next($request);
    }
}
